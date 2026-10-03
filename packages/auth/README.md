# Prefab Auth

**Prefab Auth** provides framework-independent authentication for PHP applications while allowing the host project to keep ownership of its users, database and application structure.

> Use only password authentication if that is all you need. Add social authentication and other integrations when the application grows.

Auth does not own the project's user table. Any compatible user source can be connected through the Auth contracts, including Prefab Users or a project/framework adapter.

## Requirements

- PHP 8.1 or newer
- Composer when installed as a package

## Installation

```bash
composer require tihloh/prefab-auth
```

## Responsibilities

Prefab Auth handles authentication concerns such as:

- password login and logout;
- current authenticated user/session lookup;
- custom user-provider integration;
- automatic use of compatible Prefab Users and Prefab Logs capabilities when available;
- isolated native PHP sessions by default;
- social sign-in;
- social account linking and unlinking;
- OAuth state validation;
- password-reset/social-account storage support;
- structured authentication log payloads.

It deliberately does not require the application to replace its existing user model or database design.

---

# 1. Password authentication

If Prefab Users is already available, normal usage is intentionally small:

```php
use Tihloh\Prefab\Auth\Services\AuthManager;

$auth = new AuthManager();

$result = $auth->attempt(
    $_POST['identifier'] ?? '',
    $_POST['password'] ?? '',
);

if ($result->success) {
    // Authentication succeeded.
}
```

Auth resolves missing compatible resources on first use. You do not normally call internal Prefab configuration methods yourself.

For a completely standalone Auth installation, provide your own user provider explicitly:

```php
$auth = new AuthManager($userProvider);
```

A custom session implementation may also be supplied when needed.

---

# 2. Current authenticated user

```php
$auth->check();
$auth->id();
$auth->user();
$auth->logout();
```

Typical use:

```php
if ($auth->check()) {
    $user = $auth->user();
    echo 'Welcome ' . $user->name;
}
```

---

# 3. Automatic session isolation

Prefab Auth separates its native PHP session from other applications by default.

For example:

```text
http://localhost/web1
→ PREFAB_WEB1_SESSION
→ cookie path /web1

http://localhost/web2
→ PREFAB_WEB2_SESSION
→ cookie path /web2
```

No session configuration is required for this protection.

Prefab also namespaces its own session keys so that even when PHP was started before Auth, Prefab authentication values remain application-scoped.

The default resolution is:

```text
Explicit session.namespace
        ↓
app.id
        ↓
Detected application URL path
        ↓
Detected host / stable fallback
```

An explicit shared namespace may be configured through the global Prefab configuration:

```php
PrefabConfig::set([
    'session' => [
        'namespace' => 'company_sso',
    ],
]);
```

Using the same explicit namespace gives applications the same Prefab session identity. By default an explicit namespace uses cookie path `/`; `path` and `domain` can be overridden for unusual deployments or cross-host sharing.

Optional shared session settings include:

```php
PrefabConfig::set([
    'session' => [
        'namespace' => 'company_sso',
        'name' => 'COMPANY_SESSION',
        'path' => '/',
        'domain' => '.example.com',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
        'lifetime' => 0,
    ],
]);
```

Session isolation is the default. Sharing must be intentional through common configuration.

---

# 4. User-provider ownership

Prefab Auth authenticates users; it does not require its own `users` table.

```text
Your users / employees / accounts
              ↓
AuthUserProviderInterface
              ↓
         Prefab Auth
```

A compatible user object implements `AuthenticatableUserInterface`. Existing project models, Prefab Users, Laravel model adapters or another implementation can participate without making Auth dependent on one storage design.

---

# 5. Cooperation with Prefab Users

Prefab Auth accepts any identifier string. When Prefab Users is connected, Auth delegates identifier resolution to Users.

By default, Users resolves login identifiers by email only. Projects may configure one or more mapped login fields:

```php
use Tihloh\Prefab\PrefabConfig;

PrefabConfig::set([
    'modules' => [
        'users' => [
            'identifiers' => ['email', 'username'],
        ],
    ],
]);
```

With a mapping such as:

```php
$map = new UserMap(
    table: 'users',
    email: 'email',
    attributes: [
        'username' => 'user',
    ],
);
```

the normal Auth API accepts either username or email:

```php
$result = $auth->attempt(
    $_POST['identifier'] ?? '',
    $_POST['password'] ?? '',
);
```

Auth itself remains identifier-agnostic. Prefab Users owns the configured lookup fields. Older Users integrations that do not expose `findByIdentifier()` continue to fall back to email lookup.



Prefab Users publishes a compatible user-provider capability. Auth can discover it automatically:

```text
Prefab Users
     ↓
user_provider
     ↓
Prefab Auth
```

Normal application code remains:

```php
$auth = new AuthManager();
$result = $auth->attempt($email, $password);
```

Explicit configuration always remains available and wins over automatic discovery.

---

# 6. Cooperation with Prefab Logs

When Prefab Logs is available, Auth can discover its logger capability and record infrastructure authentication events such as login, failed login and logout.

```text
Prefab Logs
    ↓
 logger
    ↓
Prefab Auth
```

The application does not need to insert audit rows around every authentication call.

---

# 7. Authentication result

Authentication returns a result instead of forcing HTML, JSON, redirects or exceptions for normal credential failure:

```php
$result = $auth->attempt($identifier, $password);

if (!$result->success) {
    // The host application decides how to respond.
}
```

Useful request context can be supplied:

```php
$result = $auth->attempt($identifier, $password, [
    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
]);
```

---

# 8. Social authentication

Social authentication belongs to **Prefab Auth**. It is another way to prove
who a user is; it does not make Auth own the application's user table.

The dependency boundary remains:

```text
Prefab Users ─────┐
Laravel adapter ──┤
CodeIgniter adapter ── user_provider ── Prefab Auth
Custom provider ──┤                         │
Legacy users ─────┘                         └── Social
                                               ├── Google
                                               ├── GitHub
                                               └── Facebook
```

`prefab-users` is therefore optional. Auth accepts any compatible
`AuthUserProviderInterface` implementation and can automatically discover
Prefab Users when it is installed.

Social providers are registered through `SocialProviderRegistry`. Projects
may implement `SocialProviderInterface` directly, use
`CallbackSocialProvider`, or adapt a PHP League OAuth provider with
`LeagueOAuth2SocialProvider`.

---

# 9. Google, GitHub and Facebook

Provider-specific OAuth libraries are optional. Install only what the
application needs:

```bash
composer require league/oauth2-google
composer require league/oauth2-github
composer require league/oauth2-facebook
```

Those are official PHP League OAuth provider clients. Prefab Auth does not
require them because a Laravel, CodeIgniter, legacy, or custom application may
prefer another provider implementation.

Example with Google:

```php
use League\OAuth2\Client\Provider\Google;
use Tihloh\Prefab\Auth\Social\LeagueOAuth2SocialProvider;
use Tihloh\Prefab\Auth\Social\SocialProviderRegistry;

$google = new Google([
    'clientId'     => $_ENV['GOOGLE_CLIENT_ID'],
    'clientSecret' => $_ENV['GOOGLE_CLIENT_SECRET'],
    'redirectUri'  => 'https://app.example.com/auth/google/callback',
]);

$providers = new SocialProviderRegistry();

$providers->register(
    LeagueOAuth2SocialProvider::google(
        $google,
        ['scope' => ['openid', 'email', 'profile']],
    ),
);
```

GitHub and Facebook provider clients are registered through the same adapter:

```php
$providers->register(
    LeagueOAuth2SocialProvider::github($github),
);

$providers->register(
    LeagueOAuth2SocialProvider::facebook($facebook),
);
```

The normalized result is always a `SocialIdentity`, regardless of the
provider.

Prefab's League adapter does not persist OAuth access or refresh tokens by
default. Authentication normally needs only the durable provider identity.
Applications that need provider APIs such as Google Drive or GitHub repository
access should treat those tokens as a separate authorization concern.

---

# 10. Starting social sign-in

```php
$url = $social->authorizationUrl('google');

header('Location: ' . $url);
exit;
```

Native OAuth state storage:

- uses the same isolated Prefab session scope as Auth;
- stores hashed state values;
- supports multiple concurrent browser tabs;
- expires pending state values;
- consumes a state value only once.

---

# 11. Handling the social callback

```php
$result = $social->callback('google', $_GET, [
    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
]);

if (!$result->success) {
    // invalid_state, provider_error, unresolved_user, account_conflict, ...
}
```

The flow is:

```text
provider + provider_user_id already linked?
        │
        ├── yes → load local user → sign in
        │
        └── no  → SocialUserResolverInterface
                    │
                    ├── create/resolve an explicitly approved user
                    └── or return null
```

A resolver should **not silently claim an existing local account solely because
the provider returned the same email address**. Email is useful profile data,
not the durable external-account key.

For a new social registration, the host resolver may create the local user and
return it. If an existing local account needs to be connected, authenticate
that local user first and use the explicit linking flow below.

The stable external key is:

```text
provider + provider_user_id
```

not email.

---

# 12. Linking and unlinking existing accounts

For an already-authenticated user:

```php
header(
    'Location: ' . $social->linkAuthorizationUrl('github')
);
exit;
```

Then in the provider callback:

```php
$result = $social->linkCurrentUser('github', $_GET);
```

This prevents a matching provider email from silently taking ownership of an
existing local account.

To disconnect:

```php
$result = $social->unlinkCurrentUser('github');
```

Prefab prevents unlinking the user's only social account when that user also
has no password credential. The application can first require the user to set a
password or connect another provider.

The social-account store also prevents:

- one external identity from being reassigned to another user;
- one local user from accidentally linking multiple different accounts for the
  same provider;
- provider/callback identity mismatches.

# 13. Permissions and Routes

Auth answers who the current user is. Permissions answers what that user may do. Routes may consume authentication/authorization capabilities through compatible integration.

```text
Routes
  ↓
Auth
  ↓
Permissions
  ↓
controller
```

The modules remain independently installable.

---

# 14. Error handling

Prefab Auth should fail as a library, not take over the host application. Missing required providers/resources produce clear, catchable exceptions. Invalid credentials return a normal authentication result.

```php
try {
    $result = $auth->attempt($email, $password);
} catch (RuntimeException $e) {
    // Your application chooses HTML, JSON, redirect or logging.
}
```

---

# 15. Diagnostics

Automatic integration can be inspected when troubleshooting:

```php
print_r($auth->explain());
```

Normal application code does not need this.

---

# 16. API quick reference

| API | Purpose |
|---|---|
| `attempt()` | Authenticate credentials |
| `login()` | Authenticate an already-resolved compatible user |
| `check()` | Determine whether a user is authenticated |
| `id()` | Return the authenticated user's ID |
| `user()` | Return the authenticated user |
| `logout()` | End the current authenticated session |\n| `authorizationUrl()` | Start social sign-in |\n| `callback()` | Complete social sign-in/registration |\n| `linkAuthorizationUrl()` | Start linking a provider to the current user |\n| `linkCurrentUser()` | Complete account linking |\n| `unlinkCurrentUser()` | Disconnect a provider without removing the last sign-in method |
| `explain()` | Inspect resolved Prefab integrations |

---

# 17. Design philosophy

```text
Auth alone
  → explicit provider + isolated session

+ Prefab Users
  → user provider discovered automatically

+ Prefab Logs
  → authentication auditing available automatically

+ Permissions / Routes
  → current actor can participate in access control
```

The simple authentication API remains the same as the application grows.

That is the goal of Prefab Auth: **authentication that can stand alone, cooperate automatically when useful, isolate itself safely by default, and never require the application to become a specific framework.**
