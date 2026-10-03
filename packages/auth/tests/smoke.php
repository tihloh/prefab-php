<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Tihloh\Prefab\Auth\Contracts\AuthenticatableUserInterface;
use Tihloh\Prefab\Auth\Contracts\AuthSessionStoreInterface;
use Tihloh\Prefab\Auth\Contracts\AuthUserProviderInterface;
use Tihloh\Prefab\Auth\Contracts\SocialAccountStoreInterface;
use Tihloh\Prefab\Auth\Contracts\SocialUserResolverInterface;
use Tihloh\Prefab\Auth\Services\AuthManager;
use Tihloh\Prefab\Auth\Services\SocialAuthManager;
use Tihloh\Prefab\Auth\Social\CallbackSocialProvider;
use Tihloh\Prefab\Auth\Social\SocialAccountConflictException;
use Tihloh\Prefab\Auth\Social\SocialIdentity;
use Tihloh\Prefab\Auth\Social\SocialProviderRegistry;

final class SmokeUser implements AuthenticatableUserInterface
{
    public function __construct(
        private int $id,
        private ?string $passwordHash = null,
        private bool $active = true,
    ) {}

    public function authId(): int|string { return $this->id; }
    public function authPasswordHash(): ?string { return $this->passwordHash; }
    public function authIsActive(): bool { return $this->active; }
}

final class SmokeUsers implements AuthUserProviderInterface
{
    /** @var array<int, SmokeUser> */
    public array $users = [];

    public function findByIdentifier(
        string $identifier,
    ): ?AuthenticatableUserInterface {
        return null;
    }

    public function findById(
        int|string $id,
    ): ?AuthenticatableUserInterface {
        return $this->users[(int) $id] ?? null;
    }
}

final class SmokeSession implements AuthSessionStoreInterface
{
    private int|string|null $id = null;

    public function put(int|string $userId): void { $this->id = $userId; }
    public function userId(): int|string|null { return $this->id; }
    public function forget(): void { $this->id = null; }
}

final class SmokeStates implements \Tihloh\Prefab\Auth\Contracts\SocialStateStoreInterface
{
    /** @var array<string,string> */
    private array $states = [];

    public function issue(string $provider): string
    {
        return $this->states[$provider] = 'state-' . $provider;
    }

    public function validate(string $provider, string $state): bool
    {
        $valid = ($this->states[$provider] ?? null) === $state;
        unset($this->states[$provider]);
        return $valid;
    }
}

final class SmokeAccounts implements SocialAccountStoreInterface
{
    /** @var array<string,array<string,int|string>> */
    private array $links = [];

    public function findUserId(
        string $provider,
        string $providerUserId,
    ): int|string|null {
        return $this->links[$provider][$providerUserId] ?? null;
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

        foreach ($this->links[$identity->provider] ?? [] as $id => $linkedUser) {
            if (
                (string) $linkedUser === (string) $userId
                && $id !== $identity->providerUserId
            ) {
                throw new SocialAccountConflictException(
                    $identity->provider,
                    $identity->providerUserId,
                    $userId,
                );
            }
        }

        $this->links[$identity->provider][$identity->providerUserId] = $userId;
    }

    public function unlink(int|string $userId, string $provider): void
    {
        foreach ($this->links[$provider] ?? [] as $id => $linkedUser) {
            if ((string) $linkedUser === (string) $userId) {
                unset($this->links[$provider][$id]);
            }
        }
    }

    public function accountsForUser(int|string $userId): array
    {
        $result = [];

        foreach ($this->links as $provider => $links) {
            foreach ($links as $providerUserId => $linkedUser) {
                if ((string) $linkedUser === (string) $userId) {
                    $result[] = [
                        'provider' => $provider,
                        'provider_user_id' => $providerUserId,
                    ];
                }
            }
        }

        return $result;
    }
}

final class SmokeResolver implements SocialUserResolverInterface
{
    public function __construct(private SmokeUsers $users) {}

    public function resolve(
        SocialIdentity $identity,
    ): ?AuthenticatableUserInterface {
        $user = new SmokeUser(2, null);
        $this->users->users[2] = $user;
        return $user;
    }
}

$users = new SmokeUsers();
$users->users[1] = new SmokeUser(
    1,
    password_hash('secret', PASSWORD_DEFAULT),
);

$session = new SmokeSession();
$auth = new AuthManager($users, $session);
$accounts = new SmokeAccounts();
$states = new SmokeStates();

$providers = new SocialProviderRegistry();
$providers->register(new CallbackSocialProvider(
    'google',
    fn (string $state): string => 'https://provider.test?state=' . $state,
    fn (array $query): SocialIdentity => new SocialIdentity(
        provider: 'google',
        providerUserId: 'google-2',
        email: 'social@example.test',
        name: 'Social User',
        emailVerified: true,
    ),
));

$social = new SocialAuthManager(
    providers: $providers,
    accounts: $accounts,
    states: $states,
    users: $users,
    resolver: new SmokeResolver($users),
    auth: $auth,
);

$url = $social->authorizationUrl('GOOGLE');
assert(str_contains($url, 'state=state-signin:google'));

$result = $social->callback('google', [
    'state' => 'state-signin:google',
    'code' => 'demo',
]);
assert($result->success === true);
assert($auth->id() === 2);
assert($accounts->findUserId('google', 'google-2') === 2);

$blocked = $social->unlinkCurrentUser('google');
assert($blocked['success'] === false);
assert($blocked['reason'] === 'last_sign_in_method');

$auth->logout();
$auth->login($users->users[1]);

$linkUrl = $social->linkAuthorizationUrl('google');
assert(str_contains($linkUrl, 'state=state-link:google'));

$conflict = $social->linkCurrentUser('google', [
    'state' => 'state-link:google',
    'code' => 'demo',
]);
assert($conflict['success'] === false);
assert($conflict['reason'] === 'account_conflict');

echo "Prefab Auth social smoke OK\n";
