# Tihloh Prefab PHP

Reusable, modular PHP components that work standalone and automatically cooperate when compatible Prefab modules are used together.

## Packages

- `tihloh/prefab-users` — user management/provider abstraction
- `tihloh/prefab-auth` — authentication and social sign-in building blocks
- `tihloh/prefab-permissions` — permissions, groups, overrides and authorization
- `tihloh/prefab-logs` — structured audit/activity logging

There is no required Core package. Install only the module(s) a project needs.

## Optional shared configuration

A project can declare common resources before constructing modules:

```php
use Tihloh\Prefab\PrefabConfig;

PrefabConfig::set([
    'database' => $mainPdo,
    'modules' => [
        'logs' => ['database' => $logPdo],
    ],
]);
```

Then modules can be declared normally:

```php
$users = new UserManager();
$auth = new AuthManager();
$permissions = new PermissionManager(['definitions' => $definitions]);
$logs = new LogManager();
```

Explicit module configuration overrides shared configuration. Missing compatible resources can be inherited from another Prefab module. Automatic configuration happens during module declarations; normal feature calls use resolved references rather than repeating discovery.

See `docs/auto-integration.md` and `examples/` for working examples.
