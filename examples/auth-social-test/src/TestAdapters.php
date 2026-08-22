<?php

namespace TestApp;

use Tihloh\Prefab\Auth\Contracts\AuthenticatableUserInterface;
use Tihloh\Prefab\Auth\Contracts\AuthUserProviderInterface;
use Tihloh\Prefab\Auth\Contracts\SocialAccountStoreInterface;
use Tihloh\Prefab\Auth\Contracts\SocialUserResolverInterface;
use Tihloh\Prefab\Auth\Social\SocialIdentity;

final class TestUser implements AuthenticatableUserInterface
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public string $passwordHash,
        public bool $active = true,
    ) {}

    public function authId(): int|string { return $this->id; }
    public function authPasswordHash(): ?string { return $this->passwordHash; }
    public function authIsActive(): bool { return $this->active; }
}

final class InMemoryUserProvider implements AuthUserProviderInterface
{
    /** @var array<int, TestUser> */
    private array $users = [];

    public function __construct()
    {
        $this->users[1] = new TestUser(
            1,
            'Demo User',
            'demo@example.com',
            password_hash('password123', PASSWORD_DEFAULT),
        );
    }

    public function findByIdentifier(string $identifier): ?AuthenticatableUserInterface
    {
        foreach ($this->users as $user) {
            if (strcasecmp($user->email, $identifier) === 0) return $user;
        }
        return null;
    }

    public function findById(int|string $id): ?AuthenticatableUserInterface
    {
        return $this->users[(int)$id] ?? null;
    }

    public function createFromSocial(SocialIdentity $identity): TestUser
    {
        $id = $this->users === [] ? 1 : max(array_keys($this->users)) + 1;
        $user = new TestUser(
            $id,
            $identity->name ?? 'Social User',
            $identity->email ?? "social{$id}@example.test",
            password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
        );
        $this->users[$id] = $user;
        return $user;
    }
}

final class TestSocialUserResolver implements SocialUserResolverInterface
{
    public function __construct(private InMemoryUserProvider $users) {}

    public function resolve(SocialIdentity $identity): ?AuthenticatableUserInterface
    {
        if ($identity->email) {
            $existing = $this->users->findByIdentifier($identity->email);
            if ($existing) return $existing;
        }
        return $this->users->createFromSocial($identity);
    }
}

final class SessionSocialAccountStore implements SocialAccountStoreInterface
{
    public function __construct()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $_SESSION['test_social_accounts'] ??= [];
    }

    public function findUserId(string $provider, string $providerUserId): int|string|null
    {
        return $_SESSION['test_social_accounts'][$provider][$providerUserId]['user_id'] ?? null;
    }

    public function link(int|string $userId, SocialIdentity $identity): void
    {
        $_SESSION['test_social_accounts'][$identity->provider][$identity->providerUserId] = [
            'user_id' => $userId,
            'email' => $identity->email,
            'name' => $identity->name,
        ];
    }

    public function unlink(int|string $userId, string $provider): void
    {
        foreach ($_SESSION['test_social_accounts'][$provider] ?? [] as $providerUserId => $account) {
            if ((string)$account['user_id'] === (string)$userId) {
                unset($_SESSION['test_social_accounts'][$provider][$providerUserId]);
            }
        }
    }

    public function accountsForUser(int|string $userId): array
    {
        $result = [];
        foreach ($_SESSION['test_social_accounts'] as $provider => $accounts) {
            foreach ($accounts as $providerUserId => $account) {
                if ((string)$account['user_id'] === (string)$userId) {
                    $result[] = ['provider' => $provider, 'provider_user_id' => $providerUserId] + $account;
                }
            }
        }
        return $result;
    }
}
