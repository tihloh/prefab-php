# Prefab Logs

**Prefab Logs** provides framework-independent structured activity and audit logging for PHP applications.

> Store structured facts once, then present them as technical audit data or human-friendly activity.

Prefab Logs is standalone. It does not require Prefab Users, Auth, Permissions, Database, Routes, Laravel, or another framework package.

## Requirements

- PHP 8.1 or newer
- Composer when installed as a package

## Installation

```bash
composer require tihloh/prefab-logs
```

## Goals

- Accept structured audit/activity payloads from any module or project code.
- Preserve actor, subject, changes, metadata, IP address, user agent and timestamps.
- Store records through `LogRepositoryInterface`.
- Accept plain PDO or Prefab's `DatabaseInterface` for the built-in repository.
- Cooperate with compatible Prefab modules when present.
- Present one stored event in technical or ordinary-user-friendly form.

---

# 1. Quick start

```php
use Tihloh\Prefab\Logs\Repositories\PdoLogRepository;
use Tihloh\Prefab\Logs\Services\LogManager;

$logs = new LogManager(
    new PdoLogRepository($pdo),
);
```

Record an event:

```php
$logs->record([
    'action' => 'user.updated',
    'subject_type' => 'user',
    'subject_id' => 25,
    'actor_id' => 7,
    'message' => 'User profile was updated.',
]);
```

That is enough for a small standalone application.

---

# 2. Structured logging

Prefab prefers structured events over plain text-only logging.

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

The structure makes the same event useful for audits, activity feeds, troubleshooting and human-friendly descriptions.

---

# 3. Actor and subject

Two concepts are intentionally separate:

```text
Actor   → who performed the action
Subject → what/who the action affected
```

Example:

```text
Actor:   Admin #7
Action:  user.updated
Subject: User #25
```

This allows the log to answer both "who did it?" and "what was changed?".

---

# 4. Changes

Changes can preserve before/after values:

```php
'changes' => [
    'office' => [
        'old' => 'Accounting',
        'new' => 'Budget',
    ],
    'active' => [
        'old' => false,
        'new' => true,
    ],
],
```

This is especially useful for administrative and audit systems where knowing that an action occurred is not enough; the application may need to know what actually changed.

---

# 5. Metadata

Application-specific context belongs in metadata:

```php
'metadata' => [
    'source' => 'admin-ui',
    'request_id' => 'abc123',
    'module' => 'users',
],
```

Prefab does not require every project to have the same metadata schema.

---

# 6. Direct repository configuration

A custom repository can be supplied directly:

```php
$logs = new LogManager($customRepository);
```

This is the most explicit form and is useful when an application already has its own logging persistence layer.

Conceptually:

```text
Database / service / custom storage
              ↓
     LogRepositoryInterface
              ↓
          LogManager
```

---

# 7. Direct database configuration

For built-in database storage:

```php
$logs = new LogManager([
    'database' => $logPdo,
]);
```

This configuration applies only to that Logs instance.

---

# 8. Central Prefab configuration

Module-specific settings can be supplied through PrefabConfig:

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

$logs = new LogManager();
```

This lets Logs use a different connection/table while the rest of the application uses common infrastructure.

---

# 9. Configuration resolution

Logs follows the standard Prefab hierarchy:

```text
1. Direct Logs configuration
2. Logs-specific PrefabConfig
3. Common PrefabConfig
4. Compatible auto-discovered capability
5. Internal/default behavior where applicable
6. Clear error if required storage remains unresolved
```

This allows explicit standalone configuration and automatic modular configuration to coexist.

---

# 10. Named database connection

When Prefab Database provides named connections:

```php
$database = new DatabaseManager([
    'default' => 'main',
    'connections' => [
        'main' => $mainPdo,
        'logs' => $logPdo,
    ],
]);

$logs = new LogManager([
    'connection' => 'logs',
]);
```

Resolved architecture:

```text
Business data → main connection
Audit logs    → logs connection
```

This is useful when audit data should be isolated from the main application database.

---

# 11. Database abstraction

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

The historical `PdoLogRepository` name remains for compatibility while its internal dependency uses the shared database contract.

Framework adapters can therefore provide the same contract later without changing `LogManager`.

---

# 12. Querying logs

Common queries include:

```php
$logs->recent();
$logs->recent(50);
$logs->find(1001);
$logs->forSubject('user', 25);
$logs->forActor(7);
```

These cover common audit/activity use cases without turning Logs into a general-purpose analytics query framework.

---

# 13. Technical view

The technical/audit representation keeps structured details available:

```php
$technical = $logs->recent(50);
```

This representation is suitable for administrators, developers, auditing and troubleshooting.

Conceptually:

```text
action:       permission.denied
actor_id:     7
subject_type: user
subject_id:   25
metadata:     ...
changes:      ...
```

---

# 14. Human-friendly view

The same stored records can be presented in ordinary language:

```php
$human = $logs->humanRecent(
    50,
    actorResolver: fn ($id) => $users->find($id)?->name,
    subjectResolver: fn ($type, $id) => $type === 'user'
        ? $users->find($id)?->name
        : null,
);
```

A technical record can therefore become:

```text
Demo Admin denied View Documents for Test User.
```

Only one structured record is stored. The human representation is derived from it rather than duplicated in another log table.

---

# 15. Automatic Prefab activity logging

When Logs is available, compatible Prefab modules may discover the `logger` capability automatically.

For example:

```php
$users = new UserManager();
$auth = new AuthManager();
$permissions = new PermissionManager();
$logs = new LogManager();
```

Conceptually:

```text
Users ───────┐
Auth ────────┼──→ structured activity → Logs
Permissions ─┘
```

Explicit logger/repository configuration still takes precedence over automatic discovery.

---

# 16. Prefab Users integration

Users can produce activity such as user creation, update and deletion while Logs remains responsible for persistence/presentation.

When a user provider is available, actor and subject IDs can also be resolved into human-readable names for activity feeds.

---

# 17. Prefab Auth integration

Auth can provide both authentication events and current-actor context.

```text
login attempt
      ↓
Prefab Auth
      ↓
structured event
      ↓
Prefab Logs
```

The logging module does not need to perform authentication itself.

---

# 18. Prefab Permissions integration

Permission changes are especially valuable in audit history:

```text
Admin granted Approve Documents to Budget Staff.
Admin denied Delete Users for Test User.
```

The stored record remains structured, allowing the same data to be displayed differently for administrators and ordinary users.

---

# 19. Prefab Routes integration

Routes may attach logging metadata to an application action:

```php
$routes->post('/documents', 'DocumentController@store')
    ->log('documents.create');
```

Middleware or another integration layer can interpret that metadata and record the appropriate activity through Prefab Logs.

Routes and Logs remain separate packages.

---

# 20. Storage ownership

By default, Logs owns only its own storage table:

```text
prefab_logs
```

It does not require or modify the project's user table, permission tables or business tables.

This makes the package safer to add to an existing application.

---

# 21. Diagnostics

Use:

```php
$info = $logs->explain();
```

Diagnostics show how Logs resolved its repository, database, connection and table configuration.

This is useful when direct configuration, module PrefabConfig, common PrefabConfig and automatic capabilities coexist.

---

# 22. Practical small application

```php
$logs = new LogManager([
    'database' => $pdo,
]);

$logs->record([
    'action' => 'report.generated',
    'actor_id' => 7,
    'metadata' => [
        'year' => 2026,
    ],
]);
```

No other Prefab module is required.

---

# 23. Practical modular application

```php
PrefabConfig::set([
    'database' => $mainPdo,
    'modules' => [
        'logs' => [
            'connection' => 'logs',
        ],
    ],
]);

$database = new DatabaseManager([
    'connections' => [
        'main' => $mainPdo,
        'logs' => $logPdo,
    ],
]);

$users = new UserManager();
$auth = new AuthManager();
$permissions = new PermissionManager();
$logs = new LogManager();
```

Business modules can emit structured events while Logs owns the audit persistence concern.

---

# 24. API quick reference

Common `LogManager` operations:

| API | Purpose |
|---|---|
| `record()` | Store a structured activity/audit event |
| `recent()` | Return recent technical log entries |
| `humanRecent()` | Return human-friendly recent activity |
| `find()` | Find a log entry by ID |
| `forSubject()` | Return activity affecting a subject |
| `forActor()` | Return activity performed by an actor |
| `explain()` | Inspect resolved repository/database configuration |

Important concepts:

| Component | Purpose |
|---|---|
| `LogEntry` | Structured log DTO |
| `LogRepositoryInterface` | Persistence abstraction |
| `PdoLogRepository` | Built-in database repository |
| actor | Entity performing the action |
| subject | Entity affected by the action |
| changes | Before/after structured values |
| metadata | Project-specific event context |

---

# 25. Design philosophy

Prefab Logs separates event facts from presentation and storage details.

```text
Application event
       ↓
structured LogEntry
       ↓
repository
       ↓
stored once
   ┌───┴────┐
   ↓        ↓
technical  human-friendly
view       view
```

A small application can simply call `record()`. A large system can automatically collect activity from Users, Auth, Permissions and Routes while keeping those modules independent.

The core principle is: **record structured facts once; decide how to display them later.**
