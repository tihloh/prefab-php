<?php

namespace Tihloh\Prefab\Auth\Services;

use RuntimeException;
use Tihloh\Prefab\PrefabConfig;
use Tihloh\Prefab\PrefabRuntime;
use Tihloh\Prefab\Auth\Adapters\PrefabUsersAuthProvider;
use Tihloh\Prefab\Auth\Contracts\AuthSessionStoreInterface;
use Tihloh\Prefab\Auth\Contracts\AuthUserProviderInterface;
use Tihloh\Prefab\Auth\Contracts\AuthenticatableUserInterface;
use Tihloh\Prefab\Auth\DTOs\AuthResult;
use Tihloh\Prefab\Auth\Session\NativeSessionStore;

/**
 * Authentication service for password/session based sign-in.
 *
 * Auth can run standalone with an explicit AuthUserProviderInterface or, when
 * Prefab Users is present and Auth has no provider configured, automatically
 * adapt the Users module as its source.
 *
 * Compatible Prefab Logs integration is also resolved during declaration so
 * normal login/logout calls do not perform repeated module discovery.
 */
final class AuthManager
{
    private ?AuthUserProviderInterface $users = null;
    private ?AuthSessionStoreInterface $session = null;
    private array $config = [];
    private ?object $context = null;
    private ?object $events = null;
    private ?object $autoLogger = null;

    /**
     * @param AuthUserProviderInterface|array|null $users Explicit provider,
     *        local Auth configuration, or null for automatic/default resolution.
     */
    public function __construct(
        AuthUserProviderInterface|array|null $users = null,
        ?AuthSessionStoreInterface $session = null,
    ) {
        if ($users instanceof AuthUserProviderInterface) {
            $this->users = $users;
        } elseif (is_array($users)) {
            $this->config = $users;
        }

        if ($session) {
            $this->session = $session;
        }

        PrefabRuntime::register('auth', $this);
    }

    /** Resolve missing session/provider/log integrations during declaration. */
    public function prefabConfigure(): void
    {
        if (!$this->session) {
            $configuredSession = $this->config['session']
                ?? PrefabConfig::module('auth', 'session');

            $this->session = $configuredSession instanceof AuthSessionStoreInterface
                ? $configuredSession
                : new NativeSessionStore(
                    $this->config['session_key'] ?? 'prefab_auth_user',
                );
        }

        if (!$this->users) {
            $configuredProvider = $this->config['provider']
                ?? PrefabConfig::module('auth', 'provider');

            if ($configuredProvider instanceof AuthUserProviderInterface) {
                $this->users = $configuredProvider;
            } else {
                $prefabUsers = PrefabRuntime::get('users');

                if ($prefabUsers) {
                    $this->users = new PrefabUsersAuthProvider($prefabUsers);
                }
            }
        }

        $this->autoLogger ??= PrefabRuntime::get('logs');
    }

    /** Attach a project-specific logging/request context provider. */
    public function useContext(object $context): self
    {
        $this->context = $context;
        return $this;
    }

    /** Attach an external event dispatcher. */
    public function useEvents(object $events): self
    {
        $this->events = $events;
        return $this;
    }

    /** Attempt password authentication using an identifier such as email. */
    public function attempt(
        string $identifier,
        string $password,
        array $context = [],
    ): AuthResult {
        $user = $this->provider()->findByIdentifier($identifier);

        if (!$user || !$user->authIsActive()) {
            return $this->result(
                false,
                null,
                $this->log(
                    'auth.login_failed',
                    null,
                    $context,
                    ['identifier' => $identifier],
                ),
                'invalid_credentials',
            );
        }

        $hash = $user->authPasswordHash();

        if (!$hash || !password_verify($password, $hash)) {
            return $this->result(
                false,
                null,
                $this->log(
                    'auth.login_failed',
                    $user->authId(),
                    $context,
                ),
                'invalid_credentials',
            );
        }

        $this->session()->put($user->authId());

        return $this->result(
            true,
            $user,
            $this->log('auth.login', $user->authId(), $context),
        );
    }

    /** Sign in an already-resolved authenticatable user. */
    public function login(
        AuthenticatableUserInterface $user,
        array $context = [],
    ): AuthResult {
        if (!$user->authIsActive()) {
            return new AuthResult(
                false,
                null,
                null,
                'inactive',
            );
        }

        $this->session()->put($user->authId());

        return $this->result(
            true,
            $user,
            $this->log('auth.login', $user->authId(), $context),
        );
    }

    /** End the current authenticated session. */
    public function logout(array $context = []): AuthResult
    {
        $id = $this->session()->userId();
        $log = $this->log('auth.logout', $id, $context);

        $this->session()->forget();

        return $this->result(true, null, $log);
    }

    /** Determine whether a user is currently authenticated. */
    public function check(): bool
    {
        return $this->session()->userId() !== null;
    }

    /** Return the current authenticated user ID. */
    public function id(): int|string|null
    {
        return $this->session()->userId();
    }

    /** Return the current authenticated user object, if available. */
    public function user(): ?AuthenticatableUserInterface
    {
        $id = $this->session()->userId();

        return $id === null
            ? null
            : $this->provider()->findById($id);
    }

    private function provider(): AuthUserProviderInterface
    {
        if (!$this->users) {
            throw new RuntimeException(
                'Prefab Auth needs an auth provider, or a compatible configured Prefab Users module.',
            );
        }

        return $this->users;
    }

    private function session(): AuthSessionStoreInterface
    {
        if (!$this->session) {
            $this->prefabConfigure();
        }

        return $this->session
            ?? throw new RuntimeException('Prefab Auth session is unavailable.');
    }

    private function result(
        bool $success,
        ?AuthenticatableUserInterface $user,
        ?array $log,
        ?string $error = null,
    ): AuthResult {
        if ($log) {
            if ($this->events && method_exists($this->events, 'dispatch')) {
                $this->events->dispatch('prefab.log', $log);
            } elseif ($this->autoLogger && method_exists($this->autoLogger, 'record')) {
                $this->autoLogger->record($log);
            }
        }

        return new AuthResult(
            $success,
            $user,
            $log,
            $error,
        );
    }

    private function log(
        string $action,
        int|string|null $userId,
        array $context,
        array $metadata = [],
    ): array {
        $base = (
            $this->context
            && method_exists($this->context, 'logContext')
        ) ? $this->context->logContext() : [];

        $context = array_replace($base, $context);

        $actorId = $action === 'auth.login_failed'
            ? ($context['actor_id'] ?? null)
            : $userId;

        return [
            'action' => $action,
            'subject_type' => 'user',
            'subject_id' => $userId,
            'actor_type' => $actorId !== null ? 'user' : null,
            'actor_id' => $actorId,
            'message' => match ($action) {
                'auth.login' => 'User signed in.',
                'auth.logout' => 'User signed out.',
                default => 'Sign-in attempt failed.',
            },
            'metadata' => array_merge(
                $metadata,
                $context['metadata'] ?? [],
            ),
            'ip_address' => $context['ip_address'] ?? null,
            'user_agent' => $context['user_agent'] ?? null,
        ];
    }
}
