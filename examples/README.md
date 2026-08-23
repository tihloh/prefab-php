# Tihloh Prefab PHP Examples

Each example is an independent Composer project using local path repositories from `packages/`.

Every example folder now includes a `prefab-options.php.example` file. It is intentionally commented and explains:

- automatic module discovery is always on when Core is used
- explicit initialization is only needed for unresolved/custom pieces
- explicit initialization never disables discovery of other modules
- relevant module-specific factory/instance examples
- multi-database configuration where applicable

## Standalone module tests

- `users-test` — Users CRUD and generated log payload
- `permissions-test` — permission definitions, user/group overrides, effective resolution
- `logs-test` — record and query structured log entries
- `auth-test` — password login and native PHP session
- `auth-social-test` — social sign-in flow using a mock provider

## Pairwise integration tests

- `users-auth-test` — one project user object used by Users/Auth concepts
- `users-permissions-test` — project user implements permission subject and resolves permissions
- `auth-logs-test` — Auth activity consumed by LogManager
- `permissions-logs-test` — permission mutation activity consumed by LogManager

## Full integration

- `combined-test` — Users + Auth + Permissions + Logs in one interactive app

## Run any example

```bash
cd examples/<example-name>
composer install
php -S 127.0.0.1:8080
```

Then open `http://127.0.0.1:8080`.

The examples intentionally use session/in-memory adapters where possible so they are easy to run without configuring a database. Production projects can replace those adapters with PDO/Laravel implementations while keeping the same Prefab service APIs.
