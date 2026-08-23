<?php

namespace Tihloh\Prefab\Auth\Services;

use Tihloh\Prefab\Auth\Contracts\AuthUserProviderInterface;
use Tihloh\Prefab\Auth\Contracts\SocialAccountStoreInterface;
use Tihloh\Prefab\Auth\Contracts\SocialStateStoreInterface;
use Tihloh\Prefab\Auth\Contracts\SocialUserResolverInterface;
use Tihloh\Prefab\Auth\DTOs\AuthResult;
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
        $state = $this->states->issue($provider);
        return $this->providers->get($provider)->authorizationUrl($state);
    }

    public function callback(string $provider, array $query, array $context = []): AuthResult
    {
        $state = (string)($query['state'] ?? '');
        if ($state === '' || !$this->states->validate($provider, $state)) {
            return new AuthResult(false, null, $this->log('auth.social_failed', null, $provider, $context, ['reason' => 'invalid_state']), 'invalid_state');
        }

        $identity = $this->providers->get($provider)->identityFromCallback($query);
        $userId = $this->accounts->findUserId($identity->provider, $identity->providerUserId);
        $user = $userId !== null ? $this->users->findById($userId) : null;

        if (!$user) {
            $user = $this->resolver->resolve($identity);
            if (!$user) {
                return new AuthResult(false, null, $this->log('auth.social_failed', null, $provider, $context, ['reason' => 'unresolved_user', 'email' => $identity->email]), 'unresolved_user');
            }
            $this->accounts->link($user->authId(), $identity);
        }

        $result = $this->auth->login($user, $context);
        if (!$result->success) return $result;

        return new AuthResult(true, $user, $this->log('auth.social_login', $user->authId(), $provider, $context, [
            'provider_user_id' => $identity->providerUserId,
            'email' => $identity->email,
        ]));
    }

    public function link(int|string $userId, string $provider, array $query, array $context = []): array
    {
        $state = (string)($query['state'] ?? '');
        if ($state === '' || !$this->states->validate($provider, $state)) {
            return ['success' => false, 'log' => $this->log('auth.social_link_failed', $userId, $provider, $context, ['reason' => 'invalid_state'])];
        }

        $identity = $this->providers->get($provider)->identityFromCallback($query);
        $this->accounts->link($userId, $identity);

        return ['success' => true, 'identity' => $identity, 'log' => $this->log('auth.social_account_linked', $userId, $provider, $context)];
    }

    public function unlink(int|string $userId, string $provider, array $context = []): array
    {
        $this->accounts->unlink($userId, $provider);
        return ['success' => true, 'log' => $this->log('auth.social_account_unlinked', $userId, $provider, $context)];
    }

    public function accountsForUser(int|string $userId): array
    {
        return $this->accounts->accountsForUser($userId);
    }

    public function providers(): array
    {
        return $this->providers->names();
    }

    private function log(string $action, int|string|null $userId, string $provider, array $context, array $metadata = []): array
    {
        return [
            'action' => $action,
            'subject_type' => 'user',
            'subject_id' => $userId,
            'actor_type' => 'user',
            'actor_id' => $userId,
            'message' => $action,
            'metadata' => array_merge(['provider' => $provider], $metadata, $context['metadata'] ?? []),
            'ip_address' => $context['ip_address'] ?? null,
            'user_agent' => $context['user_agent'] ?? null,
        ];
    }
}
