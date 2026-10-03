<?php

namespace Tihloh\Prefab\Auth\Services;

use Throwable;
use Tihloh\Prefab\Auth\Contracts\AuthUserProviderInterface;
use Tihloh\Prefab\Auth\Contracts\SocialAccountStoreInterface;
use Tihloh\Prefab\Auth\Contracts\SocialStateStoreInterface;
use Tihloh\Prefab\Auth\Contracts\SocialUserResolverInterface;
use Tihloh\Prefab\Auth\DTOs\AuthResult;
use Tihloh\Prefab\Auth\Social\SocialAccountConflictException;
use Tihloh\Prefab\Auth\Social\SocialIdentity;
use Tihloh\Prefab\Auth\Social\SocialProviderRegistry;

final class SocialAuthManager
{
    public function __construct(
        private SocialProviderRegistry $providers,
        private SocialAccountStoreInterface $accounts,
        private SocialStateStoreInterface $states,
        private AuthUserProviderInterface $users,
        private SocialUserResolverInterface $resolver,
        private AuthManager $auth,
    ) {}

    public function authorizationUrl(string $provider): string
    {
        $provider = $this->normalizeProvider($provider);
        $state = $this->states->issue($this->stateKey('signin', $provider));

        return $this->providers->get($provider)->authorizationUrl($state);
    }

    /**
     * Start a social-account connection for the currently authenticated user.
     *
     * The callback should be completed with linkCurrentUser(), not callback(),
     * so an unlinked external identity is never treated as a new sign-in.
     */
    public function linkAuthorizationUrl(string $provider): string
    {
        if (!$this->auth->check()) {
            throw new \RuntimeException(
                'A user must be authenticated before linking a social account.',
            );
        }

        $provider = $this->normalizeProvider($provider);
        $state = $this->states->issue($this->stateKey('link', $provider));

        return $this->providers->get($provider)->authorizationUrl($state);
    }

    public function callback(
        string $provider,
        array $query,
        array $context = [],
    ): AuthResult {
        $provider = $this->normalizeProvider($provider);
        $state = (string) ($query['state'] ?? '');

        if (
            $state === ''
            || !$this->states->validate(
                $this->stateKey('signin', $provider),
                $state,
            )
        ) {
            return $this->failed(
                'auth.social_failed',
                null,
                $provider,
                $context,
                'invalid_state',
            );
        }

        try {
            $identity = $this->providers
                ->get($provider)
                ->identityFromCallback($query);
        } catch (Throwable $e) {
            return $this->failed(
                'auth.social_failed',
                null,
                $provider,
                $context,
                'provider_error',
                ['exception' => $e::class],
            );
        }

        if (!$this->validIdentity($provider, $identity)) {
            return $this->failed(
                'auth.social_failed',
                null,
                $provider,
                $context,
                'invalid_identity',
            );
        }

        $userId = $this->accounts->findUserId(
            $identity->provider,
            $identity->providerUserId,
        );

        if ($userId !== null) {
            $user = $this->users->findById($userId);

            if (!$user) {
                return $this->failed(
                    'auth.social_failed',
                    $userId,
                    $provider,
                    $context,
                    'linked_user_missing',
                );
            }
        } else {
            /*
             * The resolver owns registration policy. It may create a new user
             * or return a user only after the host application has explicitly
             * verified that linking is allowed. It should not silently match
             * an existing local account by email.
             */
            $user = $this->resolver->resolve($identity);

            if (!$user) {
                return $this->failed(
                    'auth.social_failed',
                    null,
                    $provider,
                    $context,
                    'unresolved_user',
                    [
                        'email' => $identity->email,
                        'email_verified' => $identity->emailVerified,
                    ],
                );
            }
        }

        try {
            // Idempotent for an existing link and refreshes provider metadata.
            $this->accounts->link($user->authId(), $identity);
        } catch (SocialAccountConflictException) {
            return $this->failed(
                'auth.social_failed',
                $user->authId(),
                $provider,
                $context,
                'account_conflict',
            );
        }

        $result = $this->auth->login($user, $context);

        if (!$result->success) {
            return $result;
        }

        return new AuthResult(
            true,
            $user,
            $this->log(
                'auth.social_login',
                $user->authId(),
                $provider,
                $context,
                [
                    'provider_user_id' => $identity->providerUserId,
                    'email' => $identity->email,
                    'email_verified' => $identity->emailVerified,
                ],
            ),
        );
    }

    public function linkCurrentUser(
        string $provider,
        array $query,
        array $context = [],
    ): array {
        $userId = $this->auth->id();

        if ($userId === null) {
            return [
                'success' => false,
                'reason' => 'unauthenticated',
                'log' => $this->log(
                    'auth.social_link_failed',
                    null,
                    $this->normalizeProvider($provider),
                    $context,
                    ['reason' => 'unauthenticated'],
                ),
            ];
        }

        return $this->link($userId, $provider, $query, $context);
    }

    public function link(
        int|string $userId,
        string $provider,
        array $query,
        array $context = [],
    ): array {
        $provider = $this->normalizeProvider($provider);
        $state = (string) ($query['state'] ?? '');

        if (
            $state === ''
            || !$this->states->validate(
                $this->stateKey('link', $provider),
                $state,
            )
        ) {
            return [
                'success' => false,
                'reason' => 'invalid_state',
                'log' => $this->log(
                    'auth.social_link_failed',
                    $userId,
                    $provider,
                    $context,
                    ['reason' => 'invalid_state'],
                ),
            ];
        }

        $user = $this->users->findById($userId);
        if (!$user || !$user->authIsActive()) {
            return [
                'success' => false,
                'reason' => 'invalid_user',
                'log' => $this->log(
                    'auth.social_link_failed',
                    $userId,
                    $provider,
                    $context,
                    ['reason' => 'invalid_user'],
                ),
            ];
        }

        try {
            $identity = $this->providers
                ->get($provider)
                ->identityFromCallback($query);
        } catch (Throwable $e) {
            return [
                'success' => false,
                'reason' => 'provider_error',
                'log' => $this->log(
                    'auth.social_link_failed',
                    $userId,
                    $provider,
                    $context,
                    [
                        'reason' => 'provider_error',
                        'exception' => $e::class,
                    ],
                ),
            ];
        }

        if (!$this->validIdentity($provider, $identity)) {
            return [
                'success' => false,
                'reason' => 'invalid_identity',
                'log' => $this->log(
                    'auth.social_link_failed',
                    $userId,
                    $provider,
                    $context,
                    ['reason' => 'invalid_identity'],
                ),
            ];
        }

        try {
            $this->accounts->link($userId, $identity);
        } catch (SocialAccountConflictException) {
            return [
                'success' => false,
                'reason' => 'account_conflict',
                'log' => $this->log(
                    'auth.social_link_failed',
                    $userId,
                    $provider,
                    $context,
                    ['reason' => 'account_conflict'],
                ),
            ];
        }

        return [
            'success' => true,
            'identity' => $identity,
            'log' => $this->log(
                'auth.social_account_linked',
                $userId,
                $provider,
                $context,
            ),
        ];
    }

    public function unlinkCurrentUser(
        string $provider,
        array $context = [],
    ): array {
        $userId = $this->auth->id();

        if ($userId === null) {
            return [
                'success' => false,
                'reason' => 'unauthenticated',
                'log' => $this->log(
                    'auth.social_unlink_failed',
                    null,
                    $this->normalizeProvider($provider),
                    $context,
                    ['reason' => 'unauthenticated'],
                ),
            ];
        }

        return $this->unlink($userId, $provider, $context);
    }

    public function unlink(
        int|string $userId,
        string $provider,
        array $context = [],
    ): array {
        $provider = $this->normalizeProvider($provider);
        $user = $this->users->findById($userId);

        if (!$user) {
            return [
                'success' => false,
                'reason' => 'invalid_user',
                'log' => $this->log(
                    'auth.social_unlink_failed',
                    $userId,
                    $provider,
                    $context,
                    ['reason' => 'invalid_user'],
                ),
            ];
        }

        $accounts = $this->accounts->accountsForUser($userId);
        $linked = array_values(array_filter(
            $accounts,
            fn (array $account): bool =>
                strtolower((string) ($account['provider'] ?? '')) === $provider,
        ));

        if ($linked === []) {
            return [
                'success' => false,
                'reason' => 'not_linked',
                'log' => $this->log(
                    'auth.social_unlink_failed',
                    $userId,
                    $provider,
                    $context,
                    ['reason' => 'not_linked'],
                ),
            ];
        }

        $hasPassword = ($user->authPasswordHash() ?? '') !== '';

        if (count($accounts) <= 1 && !$hasPassword) {
            return [
                'success' => false,
                'reason' => 'last_sign_in_method',
                'log' => $this->log(
                    'auth.social_unlink_failed',
                    $userId,
                    $provider,
                    $context,
                    ['reason' => 'last_sign_in_method'],
                ),
            ];
        }

        $this->accounts->unlink($userId, $provider);

        return [
            'success' => true,
            'log' => $this->log(
                'auth.social_account_unlinked',
                $userId,
                $provider,
                $context,
            ),
        ];
    }

    public function accountsForUser(int|string $userId): array
    {
        return $this->accounts->accountsForUser($userId);
    }

    public function providers(): array
    {
        return $this->providers->names();
    }

    private function validIdentity(
        string $requestedProvider,
        SocialIdentity $identity,
    ): bool {
        return $this->normalizeProvider($identity->provider) === $requestedProvider
            && trim($identity->providerUserId) !== '';
    }

    private function normalizeProvider(string $provider): string
    {
        return strtolower(trim($provider));
    }

    private function stateKey(string $flow, string $provider): string
    {
        return $flow . ':' . $provider;
    }

    private function failed(
        string $action,
        int|string|null $userId,
        string $provider,
        array $context,
        string $reason,
        array $metadata = [],
    ): AuthResult {
        return new AuthResult(
            false,
            null,
            $this->log(
                $action,
                $userId,
                $provider,
                $context,
                ['reason' => $reason] + $metadata,
            ),
            $reason,
        );
    }

    private function log(
        string $action,
        int|string|null $userId,
        string $provider,
        array $context,
        array $metadata = [],
    ): array {
        return [
            'action' => $action,
            'subject_type' => 'user',
            'subject_id' => $userId,
            'actor_type' => $userId !== null ? 'user' : null,
            'actor_id' => $userId,
            'message' => $action,
            'metadata' => array_merge(
                ['provider' => $provider],
                $metadata,
                $context['metadata'] ?? [],
            ),
            'ip_address' => $context['ip_address'] ?? null,
            'user_agent' => $context['user_agent'] ?? null,
        ];
    }
}
