# Tihloh Prefab PHP Examples

Each example is an independent Composer project using local path repositories from `packages/`.

The initialization/options explanation is kept directly inside each example's `index.php` (or `public/index.php` for the social-auth example) so the sample is self-contained.

The examples demonstrate these rules:

- Prefab Core discovery/integration is always automatic.
- Explicit initialization is only used for a module/piece Core cannot infer from another initialized module or default project resource.
- Explicit initialization never disables discovery/integration of other modules.
- Combo examples let Core perform the integration instead of manually forwarding log payloads.
- Multiple database and custom factory examples are included as commented alternatives where relevant.

## Standalone module tests

- `users-test` — Users CRUD and generated log payload
- `permissions-test` — permission definitions, user/group overrides, effective resolution
- `logs-test` — record and query structured log entries
- `auth-test` — password login and native PHP session
- `auth-social-test` — social sign-in flow using a mock provider

## Pairwise integration tests

- `users-auth-test` — Users + Auth composed through Prefab Core
- `users-permissions-test` — Users + Permissions composed through Prefab Core
- `auth-logs-test` — Auth log events are recorded automatically by Logs through Core
- `permissions-logs-test` — permission mutation events are recorded automatically by Logs through Core

## Full integration

- `combined-test` — Users + Auth + Permissions + Logs in one interactive app; Core supplies actor context and automatic logging

## Run any example

```bash
cd examples/<example-name>
composer install
php -S 127.0.0.1:8080
```

For `auth-social-test`, run from that folder with:

```bash
php -S 127.0.0.1:8080 -t public
```

Then open `http://127.0.0.1:8080`.

The examples intentionally use session/in-memory adapters where possible so they are easy to run without configuring a database. Production projects can replace those adapters with PDO/Laravel implementations while keeping the same Prefab APIs.
