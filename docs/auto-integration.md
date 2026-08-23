# Automatic module integration

Prefab modules remain independently usable. When registered with `tihloh/prefab-core`, compatible modules automatically share context and events.

```php
use Tihloh\Prefab\Core\Prefab;

$prefab = Prefab::create([
    'db' => $mainPdo,
    'connections' => ['logs' => $logPdo],
]);

$prefab
    ->register('users', $users)
    ->register('auth', $auth)
    ->register('permissions', $permissions)
    ->register('logs', $logs);
```

After registration, normal module calls remain unchanged:

```php
$auth->attempt($email, $password);
$users->update(25, ['name' => 'Testing']);
$permissions->set('user', 25, 'documents.approve', true);
```

If Logs is registered, Auth, Users, and Permissions log payloads are automatically recorded. If Auth is registered, its current user automatically becomes the actor context for other modules. Explicit operation context still overrides automatic context.

Modules do not require Core and can still be instantiated and used directly. Core is an optional composition layer, not a replacement for managers, providers, repositories, or contracts.

## Multiple databases

Connections are named and project-owned:

```php
$prefab->connections()->set('default', $mainPdo);
$prefab->connections()->set('logs', $logPdo);
```

Repositories still receive the connection they need, so storage remains flexible.

## Custom integrations

Applications can listen to events without modifying modules:

```php
$prefab->events()->listen('prefab.log', function (array $entry) {
    // custom audit, notification, queue, etc.
});
```
