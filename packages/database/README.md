# Tihloh Prefab Database

Standalone PDO connection management for Tihloh Prefab PHP.

## Purpose

Prefab Database centralizes database connection creation and reuse without becoming a required Core package. Install it only when a project benefits from shared or multiple named connections.

Other Prefab modules remain fully standalone. When Prefab Database is present, an unconfigured module may inherit its default connection or request a named connection.

## Basic usage

```php
use Tihloh\Prefab\Database\Services\DatabaseManager;

$database = new DatabaseManager([
    'default' => 'main',
    'connections' => [
        'main' => new PDO('sqlite:' . __DIR__ . '/main.sqlite'),
    ],
]);

$pdo = $database->default();
```

## Multiple named connections

```php
$database = new DatabaseManager([
    'default' => 'main',
    'connections' => [
        'main' => $mainPdo,
        'logs' => $logPdo,
        'reporting' => $reportingPdo,
    ],
]);

$main = $database->connection('main');
$logs = $database->connection('logs');
```

Connections may also be described instead of pre-created:

```php
$database = new DatabaseManager([
    'default' => 'main',
    'connections' => [
        'main' => [
            'dsn' => 'mysql:host=127.0.0.1;dbname=app;charset=utf8mb4',
            'username' => 'app',
            'password' => 'secret',
            'options' => [
                PDO::ATTR_PERSISTENT => false,
            ],
        ],
    ],
]);
```

All configured PDO instances are created during Prefab configuration passes. Normal feature calls therefore use cached PDO references rather than repeatedly discovering or rebuilding connections.

## Automatic integration

```php
$database = new DatabaseManager([
    'default' => 'main',
    'connections' => [
        'main' => $mainPdo,
        'logs' => $logPdo,
    ],
]);

$users = new UserManager();
$permissions = new PermissionManager();
$logs = new LogManager([
    'connection' => 'logs',
]);
```

Resolved behavior:

```text
Users        -> main
Permissions  -> main
Logs         -> logs
```

The Logs connection override affects Logs only.

## Shared Prefab configuration

The Database manager can also be configured before module declarations:

```php
PrefabConfig::set([
    'modules' => [
        'database' => [
            'default' => 'main',
            'connections' => [
                'main' => $mainPdo,
                'logs' => $logPdo,
            ],
        ],

        'logs' => [
            'connection' => 'logs',
        ],
    ],
]);

$database = new DatabaseManager();
$logs = new LogManager();
```

## Public API

```php
$database->default();
$database->defaultName();
$database->connection('main');
$database->get('main');
$database->has('main');
$database->names();
$database->ping('main');
$database->set('archive', $archivePdo);
$database->useDefault('archive');
```

## Configuration priority

For modules consuming a database resource, the intended order is:

```text
module explicit database/repository
        ↓
module named connection
        ↓
Prefab Database default connection
        ↓
other compatible Prefab database resource
        ↓
configuration error if storage is required
```

No module-local override is written back into shared configuration or propagated to unrelated modules.
