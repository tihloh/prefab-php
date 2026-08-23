# Automatic module discovery and integration

Prefab modules remain independently usable. With `tihloh/prefab-core`, automatic discovery is the default when optional initialization is not supplied.

```php
use Tihloh\Prefab\Core\Prefab;

$prefab = Prefab::create(['db' => $pdo]);
```

Core checks which known Prefab module classes are installed. Modules that have a usable factory/default initialization are booted, then compatible modules are wired automatically. Applications do not need a manual `register()` chain for the normal case.

The rule is:

1. explicit module/configuration
2. automatic discovery
3. Prefab defaults

Manual registration remains available for advanced/custom integrations.

## Automatic integration

Once modules are booted, Core provides the common glue:

- Auth supplies the current actor through Context.
- Users, Auth, and Permissions receive Context and the shared EventDispatcher when supported.
- Logs listens for `prefab.log` events and records them automatically.
- Modules still work when the other modules are absent.

Normal calls stay normal:

```php
$auth->attempt($email, $password);
$users->update(25, ['name' => 'Testing']);
$permissions->set('user', 25, 'documents.approve', true);
```

No manual `$logs->record($result->log)` is required when the modules are integrated through Core.

## Optional initialization

Disable automatic discovery for total manual control:

```php
$prefab = Prefab::create([
    'db' => $pdo,
    'auto_discover' => false,
]);
```

Disable/customize an individual module:

```php
$prefab = Prefab::create([
    'db' => $pdo,
    'module_options' => [
        'logs' => ['enabled' => false],
        'users' => [
            'factory' => fn ($prefab, $options) => new MyUserManager(...),
        ],
    ],
]);
```

Supply explicit module instances when desired:

```php
$prefab = Prefab::create([
    'modules' => [
        'users' => $customUsers,
        'logs' => $customLogs,
    ],
]);
```

## Multiple databases

Connections are named and project-owned:

```php
$prefab = Prefab::create([
    'connections' => [
        'default' => $mainPdo,
        'logs' => $logPdo,
    ],
]);
```

Repositories still receive the connection they need, so storage remains flexible.

## Custom integrations

Applications can listen to events without modifying modules:

```php
$prefab->events()->listen('prefab.log', function (array $entry) {
    // custom audit, notification, queue, etc.
});
```

Core is an optional composition layer, not a replacement for managers, providers, repositories, contracts, or project-specific adapters.
