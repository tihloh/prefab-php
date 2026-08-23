# Tihloh Prefab PHP Examples

Each example is an independent Composer project using local path repositories from `packages/`.

The initialization/options explanation is kept directly inside each example's `index.php` (or `public/index.php` for social auth) so the sample is self-contained.

The examples demonstrate these rules:

- every Prefab module is standalone; there is no required Core package
- `PrefabConfig::set(...)` is optional shared project configuration declared before modules
- explicit module configuration overrides shared configuration only for that module
- unconfigured compatible modules integrate automatically during module declarations
- after declarations complete, normal feature calls use resolved references and do not repeat discovery
- one module can use a different database/config without disabling its integration with the others
- project-owned tables stay project-owned; Prefab modules create only their own required storage tables

## Standalone module tests

- `users-test` — Users CRUD and generated log payload
- `permissions-test` — permission definitions, user/group overrides, effective resolution
- `logs-test` — record and query structured log entries
- `auth-test` — password login and native PHP session
- `auth-social-test` — social sign-in flow using a mock provider

## Pairwise integration tests

- `users-auth-test` — Auth automatically uses compatible Prefab Users when no Auth provider is configured
- `users-permissions-test` — Users and Permissions remain independent while sharing compatible project resources
- `auth-logs-test` — Auth activity is recorded automatically when Logs exists
- `permissions-logs-test` — permission activity is recorded automatically when Logs exists

## Full integration

- `combined-test` — Users + Auth + Permissions + Logs in one interactive session/in-memory app
- `database-integration-test` — real SQLite integration: project-owned `app_users`, Prefab Permissions table in `main.sqlite`, and Prefab Logs table in separate `logs.sqlite`

## Run any example

```bash
cd examples/<example-name>
composer update
php -S 127.0.0.1:8080
```

For `auth-social-test`:

```bash
php -S 127.0.0.1:8080 -t public
```

Then open `http://127.0.0.1:8080`.

The database integration example requires PDO SQLite (`pdo_sqlite`). It creates `data/main.sqlite` and `data/logs.sqlite` locally; those generated database files are ignored by Git.

The other examples intentionally use session/in-memory adapters where possible so they run without configuring a database. Production projects can use PDO/Laravel/custom implementations while keeping the same Prefab APIs.
