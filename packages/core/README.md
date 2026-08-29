# Prefab Core

`prefab-core` is the tiny shared infrastructure layer used by Prefab feature modules.

It is not a feature module. Applications normally install a feature package such as `tihloh/prefab-users`; Composer installs Core automatically as a dependency.

Core contains only shared infrastructure:

- Prefab runtime and configuration
- capability and extension registry
- diagnostics, tracing, and debug rendering
- common database contracts / PDO adapter used internally by modules
- interoperability helpers

Core must stay small. User management, authentication, permissions, logging, routing, files, notifications, messaging, and other feature behavior belong in their own packages.

## Architecture

```text
prefab-core
├─ runtime
├─ configuration
├─ diagnostics
├─ contracts
└─ interoperability

prefab-users
prefab-auth
prefab-permissions
prefab-logs
...
```

Feature modules should depend on Core, not on one another unless a hard feature dependency is genuinely required. Optional integrations continue to use capabilities/contracts at runtime.

## Development

The monorepo remains the source of truth. The canonical shared source files currently live under `tools/` and are synchronized into `packages/core/src/` by:

```bash
php tools/sync-prefab-bootstrap.php
```

During the migration period the same script also keeps the legacy copies inside existing feature packages synchronized. Those copies can be removed after `prefab-core` is published and all feature packages require it.
