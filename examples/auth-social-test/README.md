# Auth + Social Test Project

A tiny standalone PHP app for testing `tihloh/prefab-auth` without Laravel,
CodeIgniter, or Prefab Users.

## Run

From this directory:

```bash
composer install
php -S 127.0.0.1:8080 -t public
```

Open `http://127.0.0.1:8080`.

## Test credentials

- Email: `demo@example.com`
- Password: `password123`

You can also click **Continue with Mock Google**. The mock provider follows the
same state + redirect + callback flow as a real OAuth provider but runs entirely
locally.

The first social sign-in creates a separate passwordless demo user. The linked
external identity is then reused for later social sign-ins.

## What it demonstrates

- standalone `prefab-auth` with a custom user provider;
- password login and isolated native sessions;
- `SocialProviderRegistry`;
- expiring OAuth state creation/validation;
- social callback normalization through `SocialIdentity`;
- social registration without silently matching an existing email;
- social account linking;
- authenticated-user lookup;
- logout;
- structured authentication log payloads.

## Real Google / GitHub / Facebook providers

Prefab Auth includes `LeagueOAuth2SocialProvider`, which adapts PHP League
OAuth provider clients without requiring them as hard dependencies.

Install only the provider package the project needs:

```bash
composer require league/oauth2-google
composer require league/oauth2-github
composer require league/oauth2-facebook
```

Then register the provider client with `SocialProviderRegistry`. The rest of
the application continues to use the same API:

```php
header('Location: ' . $social->authorizationUrl('google'));

$result = $social->callback('google', $_GET);
```

Existing local accounts should not be linked merely because an OAuth provider
returns the same email. Sign the local user in first, then use
`linkAuthorizationUrl()` and `linkCurrentUser()` to connect the provider.
