# Automatic module discovery and integration

Prefab modules remain independently usable. With `tihloh/prefab-core`, module discovery and compatible integration are **always automatic**.

```php
use Tihloh\Prefab\Core\Prefab;

$prefab = Prefab::create(['db' => $pdo]);
```

Core checks which known Prefab modules are available and integrates every module that has been initialized. There is no `auto_discover` switch: discovery is part of Core's normal behavior.

## Initialization rule

Explicit initialization is needed only when a module cannot be initialized from another available module or from Prefab's default/project resources.

For example, if Users has already been initialized and Auth can derive the user provider it needs from Users, Auth should initialize itself. If Logs has a usable default/log connection, it should initialize itself. If a project uses a special user provider that Prefab cannot infer, only that provider/module needs explicit initialization.

```text
Available/initialized module or default resource
                    ↓
            automatic discovery
                    ↓
          initialize what is possible
                    ↓
        automatic compatible integration
                    ↓
Explicit initialization only for unresolved/custom pieces
```

Explicit initialization never disables discovery and never requires the developer to manually register every other module.

## Automatic integration

Once modules are available, Core provides the common glue:

- Auth supplies the current actor through Context.
- Users, Auth, and Permissions receive Context and the shared EventDispatcher when supported.
- Logs listens for `prefab.log` events and records them automatically.
- Modules continue to work when other modules are absent.

Normal calls remain normal:

```php
$auth->attempt($email, $password);
$users->update(25, ['name' => 'Testing']);
$permissions->set('user', 25, 'documents.approve', true);
```

No manual `$logs->record($result->log)` is required when Logs is available.

## Optional explicit initialization

Use a custom factory only for something Prefab cannot infer automatically:

```php
$prefab = Prefab::create([
    'db' => $pdo,
    'module_options' => [
        'users' => [
            'factory' => fn ($prefab, $options) => new MyUserManager(...),
        ],
    ],
]);
```

Or supply an already-created custom module:

```php
$prefab = Prefab::create([
    'modules' => [
        'users' => $customUsers,
    ],
]);
```

Prefab still discovers and integrates Auth, Permissions, Logs, and any other compatible installed modules automatically.

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

Core is the automatic composition layer, not a replacement for managers, providers, repositories, contracts, or project-specific adapters.
