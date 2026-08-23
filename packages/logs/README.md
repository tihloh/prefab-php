# Tihloh Prefab Logs

Framework-independent structured logging for Tihloh Prefab PHP.

Prefab Logs is standalone. It does not require Users, Auth, Permissions, Prefab Database, Laravel, or another framework package.

## Goals

- Accept structured audit/activity payloads from any module or project code.
- Keep actor, subject, changes, metadata, IP address, user agent, and timestamps.
- Store logs through `LogRepositoryInterface`.
- Accept plain PDO or Prefab's `DatabaseInterface` for the built-in repository.
- Automatically integrate with compatible Prefab modules when present.
- Present the same stored log in technical or ordinary-user-friendly form.

## Standalone setup

```php
use Tihloh\Prefab\Logs\Repositories\PdoLogRepository;
use Tihloh\Prefab\Logs\Services\LogManager;

$logs = new LogManager(
    new PdoLogRepository($pdo),
);
```

The historical `PdoLogRepository` class name is retained for compatibility, but it now consumes `DatabaseInterface` internally. Passing PDO is automatically adapted.

A custom repository remains valid:

```php
$logs = new LogManager($customRepository);
```

## Automatic database configuration

Direct configuration affects Logs only:

```php
$logs = new LogManager([
    'database' => $logPdo,
]);
```

Central module-specific configuration is also supported:

```php
PrefabConfig::set([
    'database' => $mainPdo,

    'modules' => [
        'logs' => [
            'connection' => 'logs',
            'table' => 'activity_logs',
        ],
    ],
]);
```

When Prefab Database exists, the named connection is resolved automatically.

```text
1. direct Logs repository / database / connection
2. Logs-specific PrefabConfig
3. common PrefabConfig
4. compatible database capability
5. clear error if storage is still unresolved
```

## Record a structured log

```php
$logs->record([
    'action' => 'user.updated',
    'subject_type' => 'user',
    'subject_id' => 25,
    'actor_id' => 7,
    'message' => 'User profile was updated.',
    'changes' => [
        'office' => [
            'old' => 'Accounting',
            'new' => 'Budget',
        ],
    ],
    'metadata' => [
        'source' => 'admin-ui',
    ],
]);
```

A `LogEntry` DTO may also be supplied directly.

## Automatic Prefab activity logging

When Logs exists, compatible Prefab modules can discover the `logger` capability automatically.

For example, Users, Auth, and Permissions can emit structured activity without the application manually forwarding every log payload.

```php
$users = new UserManager();
$auth = new AuthManager();
$permissions = new PermissionManager();
$logs = new LogManager();
```

Explicit repositories/databases still override automatic storage choices.

## Human-friendly and technical views

The database stores only one structured record.

Technical/audit view:

```php
$technical = $logs->recent(50);
```

Ordinary-user view:

```php
$human = $logs->humanRecent(
    50,
    actorResolver: fn ($id) => $users->find($id)?->name,
    subjectResolver: fn ($type, $id) => $type === 'user'
        ? $users->find($id)?->name
        : null,
);
```

For example, a technical `permission.denied` record can be presented compactly as:

```text
Demo Admin denied View Documents for Test User.
```

The technical details remain available; the human representation does not duplicate database storage.

## Query logs

```php
$logs->recent();
$logs->find(1001);
$logs->forSubject('user', 25);
$logs->forActor(7);
```

## Database abstraction

The built-in repository accepts:

```text
PDO
DatabaseInterface
```

Plain PDO is normalized automatically:

```text
PDO
 ↓
PdoDatabaseAdapter
 ↓
DatabaseInterface
 ↓
PdoLogRepository
```

The same contract can later be supplied by Prefab Database or a framework adapter.

Driver-specific table/index DDL remains isolated in the built-in repository for MySQL/MariaDB, PostgreSQL, SQLite, and SQL Server until a separate schema abstraction is justified.

## Ownership

Logs owns only its own storage table by default:

```text
prefab_logs
```

It does not require or modify project user tables, permission tables, or business tables.

## Diagnostics

Use:

```php
$logs->explain();
```

to inspect where the repository, database, connection, and table configuration came from.
