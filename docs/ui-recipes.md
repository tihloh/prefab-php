# UI Recipes

Tihloh Prefab PHP is headless-first. Prefabs provide public APIs, data, metadata, relationships, and operations; the consuming project owns presentation.

Optional UI examples live under each package's `examples/` directory. They are copyable recipes, not runtime-rendered UI and not required dependencies.

## Rules

- Recipes must consume only public Prefab APIs.
- Recipes must never query Prefab tables directly.
- Recipes may use Bootstrap 5, plain PHP, Laravel Blade, or other presentation technologies.
- Projects may copy, attach, rename, and modify recipes freely.
- Prefab upgrades must not overwrite project-owned UI.
- Every management-oriented Prefab should expose enough read/query data to build a complete management UI without direct database access.

## Current recipes

- `packages/users/examples/bootstrap/users-list.php`
- `packages/permissions/examples/bootstrap/groups-list.php`
- `packages/permissions/examples/bootstrap/user-permissions.php`
- `packages/logs/examples/bootstrap/log-list.php`

The core modules remain usable without any UI recipe.
