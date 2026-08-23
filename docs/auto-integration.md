# Standalone automatic integration

Tihloh Prefab has no required Core package. Every module is standalone and can be installed/used by itself. When compatible Prefab modules are declared in the same application, they automatically resolve compatible integrations during module initialization.

## Optional common configuration

A project may declare shared configuration before creating modules:

```php
use Tihloh\Prefab\PrefabConfig;

PrefabConfig::set([
    'database' => $mainPdo,
    'modules' => [
        'logs' => ['database' => $logPdo],
    ],
]);
```

Each module reads only the settings/resources it understands. Explicit constructor/module configuration overrides the shared configuration.

## Resolution rule

For a needed resource, a module resolves configuration during initialization in this general order:

1. explicit module/constructor configuration
2. the module's own sensible internal default, when one exists
3. shared `PrefabConfig` resource
4. a compatible resource exposed by another initialized Prefab module
5. clear configuration error if the resource is required and still unresolved

Examples of internal defaults are table/session names. A database connection has no safe internal default, so it can come from `PrefabConfig`, another compatible module, or explicit module configuration.

## Declaration sequence

Each module registers itself when constructed and triggers a small configuration pass across the Prefab modules declared so far. The final module declaration leaves the available module graph configured.

```php
$users = new UserManager(...);
$auth = new AuthManager();
$permissions = new PermissionManager(...);
$logs = new LogManager(...);
```

After the last declaration, normal feature calls use already-resolved references. They do not repeat module discovery/configuration:

```php
$auth->attempt($email, $password);
$users->update(25, ['name' => 'Testing']);
$permissions->set('user', 25, 'documents.approve', true);
$logs->recent();
```

## Automatic combinations

Examples:

- Auth with no explicit provider can use a compatible Prefab Users module.
- Permissions with no explicit database/store can use the shared database or a compatible Users database.
- Logs with no explicit database/repository can use the shared database or a compatible module database.
- When Logs exists, Users/Auth/Permissions activity is recorded automatically; callers do not manually forward each log payload.
- Auth supplies the current actor for compatible activity logging.

## Override one module only

A shared database can be used by most modules while Logs uses another database:

```php
PrefabConfig::set(['database' => $mainPdo]);

$users = new UserManager();
$auth = new AuthManager();
$permissions = new PermissionManager(['definitions' => $definitions]);
$logs = new LogManager(['database' => $logPdo]);
```

Explicit Logs configuration changes only Logs. It does not disable integration with Users/Auth/Permissions.

## Standalone remains standalone

Installing only Users does not install Auth, Permissions, Logs, or a Core package:

```bash
composer require tihloh/prefab-users
```

The same principle applies to every other module.
