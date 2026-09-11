# Prefab Logs v2 Direction

Prefab Logs needs to grow from a small activity recorder into a reliable general-purpose audit and operational history module without becoming an event bus or application analytics framework.

## Core rule

**Record structured facts once. Query, present, retain and export them later.**

Logs owns durable audit/activity history. It does not own domain events, notifications, queues, metrics or business workflows.

## Goals

Prefab Logs v2 should support:

- compact structured storage
- human-friendly presentation
- append-only audit records
- useful filtering and pagination
- actor, subject and action indexing
- severity and outcome
- request/correlation tracing
- IP/user-agent context
- safe metadata and structured changes
- redaction of secrets and sensitive fields
- configurable retention/pruning
- export-friendly records
- database-efficient queries
- compatibility with Core shared database infrastructure

## Proposed record model

```text
id
created_at
occurred_at

action              e.g. loan.approved
category             e.g. loans, auth, users
level                debug|info|notice|warning|error|critical
outcome              success|failure|denied|partial|null

actor_type
actor_id

subject_type
subject_id

message              optional application-provided message
changes               structured before/after values
metadata              extra structured context

request_id
correlation_id
session_id
ip_address
user_agent
```

Only `action` should be mandatory at the conceptual level. Subject and actor must be optional because valid events such as system maintenance, failed login attempts and scheduled jobs may not have both.

## Action naming

Use stable machine-friendly action names:

```text
auth.login
auth.login_failed
user.created
user.updated
permission.granted
loan.created
loan.approved
payment.received
file.downloaded
```

Do not store the human sentence as the primary event identity.

## Query API direction

The current convenience methods remain useful:

```php
$logs->recent();
$logs->forActor($id);
$logs->forSubject('loan', $id);
```

Add a general query API instead of multiplying convenience methods forever:

```php
$logs->query([
    'action' => 'loan.approved',
    'category' => 'loans',
    'actor_id' => 7,
    'subject_type' => 'loan',
    'subject_id' => 1024,
    'outcome' => 'success',
    'level' => ['warning', 'error', 'critical'],
    'from' => '2026-09-01 00:00:00',
    'to' => '2026-09-30 23:59:59',
    'request_id' => '...',
    'correlation_id' => '...',
    'limit' => 100,
    'offset' => 0,
]);
```

A fluent query object may be introduced later if it clearly improves readability, but array criteria keeps the first implementation small.

## Correlation

`request_id` identifies one HTTP/CLI request.

`correlation_id` connects several related operations across requests or modules.

Example:

```text
Loan approval
  correlation_id: loan-1024-approval

├─ loan.approved
├─ notification.created
└─ sms.queued
```

Logs stores correlation identifiers but does not dispatch those operations.

## Audit integrity

Normal log records should be treated as append-only.

Prefab Logs should not expose ordinary update/delete APIs for individual audit rows. Administrative pruning is a separate retention operation.

Applications that require stronger tamper evidence can later add hash chaining or immutable external storage through an adapter. That should not burden the default package.

## Redaction

Redaction must happen before persistence.

Default sensitive keys should include common names such as:

```text
password
password_hash
secret
token
access_token
refresh_token
api_key
authorization
cookie
```

Applications must be able to add project-specific redacted fields.

Never depend only on the presenter to hide secrets; technical records must also be safe to retrieve.

## Changes

Changes remain structured:

```php
'changes' => [
    'status' => ['old' => 'pending', 'new' => 'approved'],
    'amount' => ['old' => 1000, 'new' => 1200],
]
```

Large payloads should not be blindly duplicated into logs. Applications should log identifiers and meaningful changes rather than complete records unless explicitly required.

## Storage efficiency

Prefer columns for commonly filtered fields and JSON for flexible context.

Indexed columns should include at minimum:

```text
created_at
action
category
level
outcome
actor_id
subject_type + subject_id
request_id
correlation_id
```

`changes` and `metadata` remain JSON because their shape is application-specific.

Compression may be considered only for large rarely-queried payload fields. Searchable/indexed fields must remain normal columns.

## Retention

Retention is explicit policy, not silent behavior.

Future API direction:

```php
$logs->prune(before: '2025-01-01');
```

or configuration such as:

```php
[
    'retention_days' => 730,
]
```

Automatic scheduling belongs to an application scheduler/queue integration, not Logs itself.

## Human presentation

Presentation remains separate from storage.

```text
technical record
      ↓
HumanLogPresenter
      ↓
event + details + timestamps
```

Applications may replace actor/subject IDs with names through resolvers. The stored audit record must remain valid even if the related user or business record is later deleted.

## Boundaries

Prefab Logs is not:

```text
Domain event bus
Metrics/time-series system
Application error tracker
Queue
Notification system
Business workflow engine
```

It may receive records from those systems, but it does not replace them.

## Migration strategy

The v2 implementation should be additive and backward-compatible:

1. Keep `record()`, `recent()`, `find()`, `forActor()` and `forSubject()`.
2. Add optional fields with safe defaults.
3. Add a general query API.
4. Add redaction before persistence.
5. Add retention/pruning APIs.
6. Update the SQL schema with a new migration instead of rewriting the original migration.
7. Keep custom repositories working through capability/extended interfaces where possible instead of forcing every implementation to immediately support all v2 query features.

This lets existing applications upgrade without rewriting their current logging calls.
