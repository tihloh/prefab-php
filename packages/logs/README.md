# Tihloh Prefab Logs

Framework-independent structured logging for Tihloh Prefab PHP.

## Goals

- Keep logging independent from Users, Permissions, Auth, and other Prefabs.
- Accept structured log payloads produced by any module or project code.
- Store logs through a repository contract; PDO is the first implementation.
- Preserve actor, subject, changes, metadata, IP address, user agent, and timestamps.

## Setup

```php
use Tihloh\Prefab\Logs\Repositories\PdoLogRepository;
use Tihloh\Prefab\Logs\Services\LogManager;

$logs = new LogManager(
    new PdoLogRepository($pdo)
);
```

Run the included migration:

```text
migrations/202608220001_create_prefab_logs.sql
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

You may also create a `LogEntry` DTO directly.

```php
use Tihloh\Prefab\Logs\DTOs\LogEntry;

$id = $logs->record(new LogEntry(
    action: 'permission.granted',
    subjectType: 'user',
    subjectId: 25,
    actorId: 7,
    message: 'documents.approve granted to user 25.',
    metadata: [
        'permission' => 'documents.approve',
        'value' => true,
    ],
));
```

## CRUD integration pattern

Users and other Prefabs do not need to depend on `prefab-logs`. They may construct a plain log payload and let the project decide whether to record it.

```php
$before = $users->find(25);
$after = $users->update(25, [
    'office' => 'Budget',
]);

$entry = [
    'action' => 'user.updated',
    'subject_type' => 'user',
    'subject_id' => $after->id,
    'actor_id' => $currentUserId,
    'message' => "User {$after->name} was updated.",
    'changes' => [
        'office' => [
            'old' => $before?->office,
            'new' => $after->office,
        ],
    ],
];

$logs->record($entry);
```

The planned integration is for CRUD operations to optionally return a structured log payload alongside their normal result. The consuming project then passes that payload to `LogManager`.

## Query logs

```php
$logs->recent();
$logs->find(1001);
$logs->forSubject('user', 25);
$logs->forActor(7);
```

## Ownership

The Logs prefab owns only its own storage table by default:

```text
prefab_logs
```

It does not require or modify project user tables, permission tables, or business tables.
