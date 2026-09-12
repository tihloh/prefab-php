# Prefab Logs

**Prefab Logs** provides framework-independent structured activity and audit logging for PHP applications.

> Store compact structured facts, then present them as a human-readable activity trail.

## Installation

```bash
composer require tihloh/prefab-logs
```

## Quick start

```php
use Tihloh\Prefab\Logs\DTOs\LogEntry;
use Tihloh\Prefab\Logs\Services\LogManager;

$logs = new LogManager();

$before = [
    'office' => 'Accounting',
    'active' => false,
    'email' => 'user@example.com',
];

$now = [
    'office' => 'Budget',
    'active' => true,
    'email' => 'user@example.com',
];

$logs->record([
    'action' => 'user.updated',
    'subject_type' => 'user',
    'subject_id' => 25,
    'actor_id' => 7,
    'changes' => LogEntry::changes($before, $now),
]);
```

Only fields whose values actually changed are stored. In this example `email` is omitted automatically.

The stored changes are compact and explicit:

```php
[
    'office' => [
        'before' => 'Accounting',
        'now' => 'Budget',
    ],
    'active' => [
        'before' => false,
        'now' => true,
    ],
]
```

## Human-first activity

Prefab Logs is intended to produce activity that reads naturally to people, not just developers.

```php
$human = $logs->humanRecent(
    50,
    actorResolver: fn ($id) => $users->find($id)?->name,
    subjectResolver: fn ($type, $id) => $type === 'user'
        ? $users->find($id)?->name
        : null,
);
```

A presented log can read like:

```text
Christian updated Juan Dela Cruz.
```

Its details remain separate:

```text
Office
Accounting → Budget

Active
No → Yes
```

The presenter returns:

```php
$log['event'];
$log['details'];
$log['created_at'];
$log['occurred_at'];
```

Each change detail contains:

```php
[
    'field' => 'Office',
    'before' => 'Accounting',
    'now' => 'Budget',
]
```

### Custom human sentence

Applications may supply a message when the generic actor/action/subject sentence is not enough:

```php
$logs->record([
    'action' => 'purchase_request.forwarded',
    'subject_type' => 'purchase_request',
    'subject_id' => 182,
    'actor_id' => 7,
    'message' => 'Christian forwarded Purchase Request PR-2026-00182 to Provincial Accounting Office',
]);
```

When `message` is supplied, the human presenter uses it as the event sentence while the structured action, subject and actor remain available for technical use.

## Update tracking

Use `LogEntry::changes()` whenever an operation has before and current values:

```php
$changes = LogEntry::changes($before, $now);
```

Fields may be excluded explicitly:

```php
$changes = LogEntry::changes(
    $before,
    $now,
    ['updated_at', 'last_seen_at'],
);
```

The rules are simple:

```text
same value       → not stored
changed value    → before + now
new field        → None → value
removed field    → value → None
```

Manually supplied change arrays are also normalized. Prefab accepts the older `old`/`new` shape when encountered, but stores the current canonical `before`/`now` shape and removes unchanged pairs.

## Actor and subject

```text
Actor   → who performed the action
Action  → what happened
Subject → what or who was affected
```

Resolvers replace technical IDs with useful names. Without a resolver, Prefab falls back to values such as `Someone #7` and `user #25`.

Typical human events include:

```text
Christian created Purchase Request PR-2026-00182.
Christian updated Juan Dela Cruz.
Christian approved Loan #1024.
Christian rejected Application #85.
Christian uploaded Requirement #12.
Christian signed in.
```

## Sensitive fields

Sensitive values should never be shown as human change details. The presenter filters common fields including:

```text
password
password_hash
token
secret
access_token
refresh_token
api_key
authorization
cookie
```

Applications should still avoid placing secrets in log metadata or changes in the first place.

## Event and time are separate

Do not build timestamps into the human sentence.

Recommended UI:

```text
Activity                                      Time
------------------------------------------------------------
Christian updated Purchase Request PR-00182   2 min ago
Christian signed in                           8 min ago
```

Selecting a row can reveal only the changed details:

```text
Christian updated Purchase Request PR-00182

Amount
₱12,000.00 → ₱15,000.00

Status
Draft → Submitted
```

The application decides whether time is absolute, relative or hidden.

## Technical view

The original structured records remain available:

```php
$logs->recent(50);
$logs->find(1001);
$logs->forSubject('user', 25);
$logs->forActor(7);
```

Technical records retain the action, actor ID, subject type/ID, structured changes, metadata and timestamps.

## Metadata

Project-specific context belongs in metadata:

```php
'metadata' => [
    'source' => 'admin-ui',
    'request_id' => 'abc123',
    'module' => 'users',
],
```

Metadata is optional. Do not duplicate ordinary record contents into logs unnecessarily.

## Storage

Prefab Logs uses database-backed persistence through `LogRepositoryInterface`. The built-in repository uses compatible shared database infrastructure while Logs remains responsible for its own log records.

A custom repository may still be supplied directly:

```php
$logs = new LogManager($customRepository);
```

Or database configuration can be supplied:

```php
$logs = new LogManager([
    'database' => $logPdo,
]);
```

Logs owns only its own audit/activity data. It does not own application user, permission or business tables.

## Automatic Prefab activity

Compatible Prefab modules can emit structured activity when a logger capability is available:

```text
Users ───────┐
Auth ────────┼──→ structured activity → Logs
Permissions ─┘
```

Logs does not perform authentication, user management or permission decisions itself.

## Diagnostics

```php
$logs->record($data);
prefab_trace();
```

Detailed trace:

```php
prefab_trace_detailed();
```

Configuration resolution:

```php
$info = $logs->explain();
```

## API quick reference

| API | Purpose |
|---|---|
| `record()` | Store a structured activity/audit event |
| `LogEntry::changes()` | Compare before/current values and keep only changed fields |
| `LogEntry::normalizeChanges()` | Normalize manual changes to `before`/`now` and remove unchanged values |
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
| `details` | Changed fields only, presented as before → now |
| `created_at` | Time the log record was created |
| `occurred_at` | Optional event/business occurrence time |
| `technical` | Original technical record |
| `summary` | Compatibility alias for `event` |

## Design philosophy

```text
Application action
       ↓
structured LogEntry
       ↓
changed fields only
       ↓
database
   ┌───┴─────────────┐
   ↓                 ↓
technical        human presenter
                     ↓
          natural event + before → now
```

The core principle is:

> **Store structured facts compactly. Show them like a person wrote the audit trail.**
