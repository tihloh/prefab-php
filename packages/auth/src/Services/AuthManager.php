<?php

namespace Tihloh\Prefab\Auth\Services;

use Tihloh\Prefab\Auth\Contracts\AuthSessionStoreInterface;
use Tihloh\Prefab\Auth\Contracts\AuthUserProviderInterface;
use Tihloh\Prefab\Auth\Contracts\AuthenticatableUserInterface;
use Tihloh\Prefab\Auth\DTOs\AuthResult;

final class AuthManager
{
    public function __construct(
        private AuthUserProviderInterface $users,
        private AuthSessionStoreInterface $session,
    ) {}

    public function attempt(string $identifier, string $password, array $context = []): AuthResult
    {
        $user = $this->users->findByIdentifier($identifier);
        if (!$user || !$user->authIsActive()) {
            return new AuthResult(false, null, $this->log('auth.login_failed', null, $context, ['identifier' => $identifier]), 'invalid_credentials');
        }

        $hash = $user->authPasswordHash();
        if (!$hash || !password_verify($password, $hash)) {
            return new AuthResult(false, null, $this->log('auth.login_failed', $user->authId(), $context), 'invalid_credentials');
        }

        $this->session->put($user->authId());
        return new AuthResult(true, $user, $this->log('auth.login', $user->authId(), $context));
    }

    public function login(AuthenticatableUserInterface $user, array $context = []): AuthResult
    {
        if (!$user->authIsActive()) return new AuthResult(false, null, null, 'inactive');
        $this->session->put($user->authId());
        return new AuthResult(true, $user, $this->log('auth.login', $user->authId(), $context));
    }

    public function logout(array $context = []): AuthResult
    {
        $userId = $this->session->userId();
        $this->session->forget();
        return new AuthResult(true, null, $this->log('auth.logout', $userId, $context));
    }

    public function check(): bool { return $this->session->userId() !== null; }

    public function id(): int|string|null { return $this->session->userId(); }

    public function user(): ?AuthenticatableUserInterface
    {
        $id = $this->session->userId();
        return $id === null ? null : $this->users->findById($id);
    }

    private function log(string $action, int|string|null $userId, array $context, array $metadata = []): array
    {
        return [
            'action' => $action,
            'subject_type' => 'user',
            'subject_id' => $userId,
            'actor_type' => 'user',
            'actor_id' => $userId,
            'message' => $action,
            'metadata' => array_merge($metadata, $context['metadata'] ?? []),
            'ip_address' => $context['ip_address'] ?? null,
            'user_agent' => $context['user_agent'] ?? null,
        ];
    }
}
