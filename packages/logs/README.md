# Prefab Logs

**Prefab Logs** provides framework-independent structured activity and audit logging for PHP applications.

> Store structured facts once, then present the event, details, and time independently.

## Installation

```bash
composer require tihloh/prefab-logs
```

## Quick start

```php
use Tihloh\Prefab\Logs\Services\LogManager;

$logs = new LogManager();

$logs->record([
    'action' => 'user.updated',
    'subject_type' => 'user',
    'subject_id' => 25,
    'actor_id' => 7,
    'changes' => [
        'office' => ['old' => 'Accounting', 'new' => 'Budget'],
        'active' => ['old' => false, 'new' => true],
    ],
]);
```

Prefab prefers structured facts over a prebuilt display sentence. The same record can serve auditing, troubleshooting and a human activity feed.

## Human-friendly activity

```php
$human = $logs->humanRecent(
    50,
    actorResolver: fn ($id) => $users->find($id)?->name,
    subjectResolver: fn ($type, $id) => $type === 'user'
        ? $users->find($id)?->name
        : null,
);
```

A presented record separates display concerns:

```php
$log['event'];       // Demo Admin updated Test User.
$log['details'];     // structured friendly before/after rows
$log['created_at'];  // log creation time
$log['occurred_at']; // optional business/event occurrence time
```

`summary` remains a compatibility alias for `event`. New interfaces should prefer `event`.

### Event is not the timestamp

Do not concatenate the creation time into the human event sentence.

Recommended UI:

```text
Event                         Details                    Created
-----------------------------------------------------------------------
Admin updated Vendo #2        Enabled: No → Yes          2 min ago
Admin signed in                                          8 min ago
```

The application decides whether `created_at` is shown as an absolute date, a relative time, or omitted. Prefab keeps it separate from the event description.

## Structured changes

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

The human presenter exposes these separately from `event`:

```php
foreach ($log['details'] as $change) {
    echo $change['field'];
    echo $change['old'];
    echo $change['new'];
}
```

Sensitive change fields such as passwords, hashes, tokens and secrets are not included in friendly change details.

## Actor and subject

```text
Actor   → who performed the action
Action  → what happened
Subject → what/who was affected
```

Resolvers can replace technical IDs with useful names. If a subject cannot be resolved, the presenter can still fall back to a type and ID such as `user #25`.

## Technical view

Use the normal query APIs when the original structured audit data is required:

```php
$logs->recent(50);
$logs->find(1001);
$logs->forSubject('user', 25);
$logs->forActor(7);
```

Technical records retain action, actor ID, subject type/ID, metadata, changes and timestamps.

## Metadata

Project-specific context belongs in metadata:

```php
'metadata' => [
    'source' => 'admin-ui',
    'request_id' => 'abc123',
    'module' => 'users',
],
```

Prefab does not require every application to share the same metadata schema.

## Storage

Logs stores records through `LogRepositoryInterface`. The built-in repository can use compatible shared database infrastructure while Logs remains responsible for its own persistence concern.

A custom repository can be supplied directly:

```php
$logs = new LogManager($customRepository);
```

Or built-in database configuration can be used:

```php
$logs = new LogManager([
    'database' => $logPdo,
]);
```

By default Logs owns only its own log storage; it does not own application user, permission or business tables.

## Automatic Prefab activity

Compatible Prefab modules can emit structured activity when a logger capability is available:

```text
Users ───────┐
Auth ────────┼──→ structured activity → Logs
Permissions ─┘
```

Logs does not perform authentication, user management or permission decisions itself.

## Diagnostics

Use normal trace output while developing:

```php
$logs->record($data);
prefab_trace();
```

Detailed trace:

```php
prefab_trace_detailed();
```

Tracing is temporary developer diagnostics. Logs is persistent application/audit history.

Configuration resolution can be inspected with:

```php
$info = $logs->explain();
```

## API quick reference

| API | Purpose |
|---|---|
| `record()` | Store a structured activity/audit event |
| `recent()` | Return recent technical records |
| `humanRecent()` | Return human-friendly presented records |
| `find()` | Find a record by ID |
| `forSubject()` | Activity affecting a subject |
| `forActor()` | Activity performed by an actor |
| `explain()` | Inspect resolved logging configuration |

Human presentation fields:

| Field | Purpose |
|---|---|
| `event` | Human-readable event sentence |
| `details` | Friendly structured change rows |
| `created_at` | Time the log record was created |
| `occurred_at` | Optional event/business occurrence time |
| `technical` | Original technical record |
| `summary` | Compatibility alias for `event` |

## Design philosophy

```text
Application event
       ↓
structured LogEntry
       ↓
repository
       ↓
stored once
   ┌───┴─────────────┐
   ↓                 ↓
technical        human presenter
                     ↓
             event / details / time
```

The core principle is: **record structured facts once; decide how to display them later.**
