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
 * Standalone authentication service with optional Prefab auto-integration.
 *
 * Auth can use an explicit provider/session, PrefabConfig values, or a compatible
 * `user_provider` capability. When Prefab Users provides the discovered user
 * provider, Auth wraps the Users module with PrefabUsersAuthProvider so existing
 * Auth behavior remains unchanged.
 */
final class AuthManager
{
    private ?AuthUserProviderInterface $users = null;
    private ?AuthSessionStoreInterface $session = null;
    private array $config = [];
    private ?object $context = null;
    private ?object $events = null;
    private ?object $autoLogger = null;

    public function __construct(
        AuthUserProviderInterface|array|null $users = null,
        ?AuthSessionStoreInterface $session = null,
    ) {
        if ($users instanceof AuthUserProviderInterface) {
            $this->users = $users;
            PrefabRuntime::recordResolution(
                'auth',
                'user_provider',
                'module-local',
                ['provider' => $users::class],
            );
        } elseif (is_array($users)) {
            $this->config = $users;
        }

        if ($session) {
            $this->session = $session;
            PrefabRuntime::recordResolution(
                'auth',
                'session',
                'module-local',
                ['provider' => $session::class],
            );
        }

        PrefabRuntime::register('auth', $this);
    }

    /** Resolve missing session/provider/logger references during startup. */
    public function prefabConfigure(): void
    {
        if (!$this->session) {
            $session = PrefabConfig::resolve('auth', 'session', $this->config);

            if ($session['value'] instanceof AuthSessionStoreInterface) {
                $this->session = $session['value'];
                PrefabRuntime::recordResolution(
                    'auth',
                    'session',
                    $session['source'],
                    ['provider' => $this->session::class],
                );
            } else {
                $sessionKey = PrefabConfig::resolve(
                    'auth',
                    'session_key',
                    $this->config,
                    'prefab_auth_user',
                );

                $this->session = new NativeSessionStore((string) $sessionKey['value']);
                PrefabRuntime::recordResolution(
                    'auth',
                    'session',
                    $sessionKey['source'],
                    [
                        'provider' => NativeSessionStore::class,
                        'session_key' => (string) $sessionKey['value'],
                    ],
                );
            }
        }

        if (!$this->users) {
            $provider = PrefabConfig::resolve('auth', 'provider', $this->config);

            if ($provider['value'] instanceof AuthUserProviderInterface) {
                $this->users = $provider['value'];
                PrefabRuntime::recordResolution(
                    'auth',
                    'user_provider',
                    $provider['source'],
                    ['provider' => $this->users::class],
                );
            } else {
                $entry = PrefabRuntime::resolveEntry('user_provider');
                $prefabUsers = PrefabRuntime::get('users');

                if ($entry && $prefabUsers && $entry['provider'] === 'prefab-users') {
                    $this->users = new PrefabUsersAuthProvider($prefabUsers);
                    PrefabRuntime::recordResolution(
                        'auth',
                        'user_provider',
                        'prefab-capability',
                        [
                            'provider' => $entry['provider'],
                            'adapter' => PrefabUsersAuthProvider::class,
                        ],
                    );
                } elseif ($entry && $entry['value'] instanceof AuthUserProviderInterface) {
                    $this->users = $entry['value'];
                    PrefabRuntime::recordResolution(
                        'auth',
                        'user_provider',
                        'prefab-capability',
                        ['provider' => $entry['provider']],
                    );
                }
            }
        }

        if (!$this->autoLogger) {
            $logger = PrefabRuntime::resolveEntry('logger');

            if ($logger) {
                $this->autoLogger = $logger['value'];
                PrefabRuntime::recordResolution(
                    'auth',
                    'logger',
                    'prefab-capability',
                    ['provider' => $logger['provider']],
                );
            }
        }

        /* Auth publishes the current-actor capability for Logs/Users/etc. */
        PrefabRuntime::provide('actor_provider', $this, 'prefab-auth');
    }

    /** Explain how this module resolved its integrations. */
    public function explain(): array
    {
        return PrefabRuntime::explain('auth');
    }

    public function useContext(object $context): self
    {
        $this->context = $context;
        return $this;
    }

    public function useEvents(object $events): self
    {
        $this->events = $events;
        return $this;
    }

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
                $this->log('auth.login_failed', $user->authId(), $context),
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

    public function login(
        AuthenticatableUserInterface $user,
        array $context = [],
    ): AuthResult {
        if (!$user->authIsActive()) {
            return new AuthResult(false, null, null, 'inactive');
        }

        $this->session()->put($user->authId());

        return $this->result(
            true,
            $user,
            $this->log('auth.login', $user->authId(), $context),
        );
    }

    public function logout(array $context = []): AuthResult
    {
        $id = $this->session()->userId();
        $log = $this->log('auth.logout', $id, $context);
        $this->session()->forget();

        return $this->result(true, null, $log);
    }

    public function check(): bool
    {
        return $this->session()->userId() !== null;
    }

    public function id(): int|string|null
    {
        return $this->session()->userId();
    }

    public function user(): ?AuthenticatableUserInterface
    {
        $id = $this->session()->userId();

        return $id === null ? null : $this->provider()->findById($id);
    }

    private function provider(): AuthUserProviderInterface
    {
        if (!$this->users) {
            throw new RuntimeException(
                'Prefab Auth needs an auth provider or compatible user_provider capability.',
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

        return new AuthResult($success, $user, $log, $error);
    }

    private function log(
        string $action,
        int|string|null $userId,
        array $context,
        array $metadata = [],
    ): array {
        $base = ($this->context && method_exists($this->context, 'logContext'))
            ? $this->context->logContext()
            : [];

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
            'metadata' => array_merge($metadata, $context['metadata'] ?? []),
            'ip_address' => $context['ip_address'] ?? null,
            'user_agent' => $context['user_agent'] ?? null,
        ];
    }
}
