<?php

namespace TestApp;

use Tihloh\Prefab\Auth\Contracts\AuthenticatableUserInterface;
use Tihloh\Prefab\Auth\Contracts\AuthUserProviderInterface;
use Tihloh\Prefab\Auth\Contracts\SocialAccountStoreInterface;
use Tihloh\Prefab\Auth\Contracts\SocialUserResolverInterface;
use Tihloh\Prefab\Auth\Social\SocialAccountConflictException;
use Tihloh\Prefab\Auth\Social\SocialIdentity;

final class TestUser implements AuthenticatableUserInterface
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public ?string $passwordHash,
        public bool $active = true,
    ) {}

    public function authId(): int|string { return $this->id; }
    public function authPasswordHash(): ?string { return $this->passwordHash; }
    public function authIsActive(): bool { return $this->active; }
}

final class InMemoryUserProvider implements AuthUserProviderInterface
{
    public function __construct()
    {
        $_SESSION['test_users'] ??= [
            1 => [
                'id' => 1,
                'name' => 'Demo User',
                'email' => 'demo@example.com',
                'password_hash' => password_hash(
                    'password123',
                    PASSWORD_DEFAULT,
                ),
                'active' => true,
            ],
        ];
    }

    public function findByIdentifier(
        string $identifier,
    ): ?AuthenticatableUserInterface {
        foreach ($_SESSION['test_users'] as $row) {
            if (strcasecmp($row['email'], $identifier) === 0) {
                return $this->hydrate($row);
            }
        }

        return null;
    }

    public function findById(
        int|string $id,
    ): ?AuthenticatableUserInterface {
        $row = $_SESSION['test_users'][(int) $id] ?? null;

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function createFromSocial(SocialIdentity $identity): TestUser
    {
        $ids = array_map('intval', array_keys($_SESSION['test_users']));
        $id = $ids === [] ? 1 : max($ids) + 1;

        $row = [
            'id' => $id,
            'name' => $identity->name ?? 'Social User',
            'email' => $identity->email ?? "social{$id}@example.test",
            'password_hash' => null,
            'active' => true,
        ];

        $_SESSION['test_users'][$id] = $row;

        return $this->hydrate($row);
    }

    private function hydrate(array $row): TestUser
    {
        return new TestUser(
            (int) $row['id'],
            (string) $row['name'],
            (string) $row['email'],
            $row['password_hash'] ?? null,
            (bool) ($row['active'] ?? true),
        );
    }
}

final class TestSocialUserResolver implements SocialUserResolverInterface
{
    public function __construct(private InMemoryUserProvider $users) {}

    public function resolve(
        SocialIdentity $identity,
    ): ?AuthenticatableUserInterface {
        /*
         * Safe registration rule for the demo:
         * an email collision is not automatic proof that the external account
         * belongs to the existing local user. Existing users should sign in
         * first, then explicitly connect the provider.
         */
        if (
            $identity->email
            && $this->users->findByIdentifier($identity->email)
        ) {
            return null;
        }

        return $this->users->createFromSocial($identity);
    }
}

final class SessionSocialAccountStore implements SocialAccountStoreInterface
{
    public function __construct()
    {
        $_SESSION['test_social_accounts'] ??= [];
    }

    public function findUserId(
        string $provider,
        string $providerUserId,
    ): int|string|null {
        return $_SESSION['test_social_accounts']
            [$provider]
            [$providerUserId]
            ['user_id'] ?? null;
    }

    public function link(int|string $userId, SocialIdentity $identity): void
    {
        $existing = $this->findUserId(
            $identity->provider,
            $identity->providerUserId,
        );

        if ($existing !== null && (string) $existing !== (string) $userId) {
            throw new SocialAccountConflictException(
                $identity->provider,
                $identity->providerUserId,
                $existing,
            );
        }

        foreach (
            $_SESSION['test_social_accounts'][$identity->provider] ?? []
            as $providerUserId => $account
        ) {
            if (
                (string) $account['user_id'] === (string) $userId
                && (string) $providerUserId !== $identity->providerUserId
            ) {
                throw new SocialAccountConflictException(
                    $identity->provider,
                    $identity->providerUserId,
                    $userId,
                );
            }
        }

        $_SESSION['test_social_accounts']
            [$identity->provider]
            [$identity->providerUserId] = [
                'user_id' => $userId,
                'email' => $identity->email,
                'email_verified' => $identity->emailVerified,
                'username' => $identity->username,
                'name' => $identity->name,
                'avatar' => $identity->avatar,
            ];
    }

    public function unlink(int|string $userId, string $provider): void
    {
        foreach (
            $_SESSION['test_social_accounts'][$provider] ?? []
            as $providerUserId => $account
        ) {
            if ((string) $account['user_id'] === (string) $userId) {
                unset(
                    $_SESSION['test_social_accounts']
                        [$provider]
                        [$providerUserId],
                );
            }
        }
    }

    public function accountsForUser(int|string $userId): array
    {
        $result = [];

        foreach ($_SESSION['test_social_accounts'] as $provider => $accounts) {
            foreach ($accounts as $providerUserId => $account) {
                if ((string) $account['user_id'] === (string) $userId) {
                    $result[] = [
                        'provider' => $provider,
                        'provider_user_id' => $providerUserId,
                    ] + $account;
                }
            }
        }

        return $result;
    }
}
