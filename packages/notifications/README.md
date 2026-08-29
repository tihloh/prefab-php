# Prefab Notifications

Framework-independent internal system-to-user notifications for PHP applications.

> Notifications stay inside the application. External delivery belongs to Prefab Messaging.

This README is the package reference documentation. Step-by-step learning examples belong in the Prefab tutorial site.

## Requirements

- PHP 8.1 or newer
- Composer when installed as a package
- PDO when using `PdoNotificationStore`

## Installation

```bash
composer require tihloh/prefab-notifications
```

## Package scope

Prefab Notifications owns persistent or transient in-application notices such as:

```text
Document approved
New document received
Report is ready
Account setting changed
```

It provides recipient-specific notification lists, unread state, metadata, optional action URLs and replaceable storage.

It does not provide email, SMS or external push delivery. Use Prefab Messaging for those concerns.

## NotificationManager

```php
use Tihloh\Prefab\Notifications\NotificationManager;

$notifications = new NotificationManager();
```

With no store argument, the manager uses its in-memory store. This is appropriate for tests and transient same-process usage. Normal web applications usually require a persistent store.

A persistent store can be supplied directly:

```php
use Tihloh\Prefab\Notifications\Stores\PdoNotificationStore;

$notifications = new NotificationManager(
    new PdoNotificationStore($pdo)
);
```

## Creating notifications

```php
$notification = $notifications->send(
    25,
    'Document Approved',
    'OBR-2026-001 has been approved.',
    ['document_id' => 1001],
    '/documents/1001',
);
```

Arguments, in order:

| Argument | Purpose |
|---|---|
| recipient ID | Identifies the account/entity that owns the notification |
| title | Short notification heading |
| message | Human-readable notification body |
| metadata | Optional application-specific structured data |
| action URL | Optional application location related to the notification |

The returned notification object contains the stored notification data including its ID, recipient, content, metadata, creation time and read state.

## Reading notifications

Recent notifications for a recipient:

```php
$recent = $notifications->recent(25);
```

Unread notifications only:

```php
$unread = $notifications->unread(25);
```

Unread count:

```php
$count = $notifications->unreadCount(25);
```

The recipient ID is intentionally generic. It can correspond to a Prefab Users ID, a framework user ID or an application's own identity model.

## Read state

Mark a notification as read:

```php
$notifications->markRead($notificationId);
```

Restore unread state:

```php
$notifications->markUnread($notificationId);
```

The persistent PDO implementation represents unread state with `read_at = NULL` and read state with a timestamp.

## Deleting notifications

```php
$notifications->delete($notificationId);
```

Deletion can be used for dismissal or permanent removal according to application policy.

## In-memory storage

```php
$notifications = new NotificationManager();
```

The in-memory implementation does not persist across independent PHP requests. It is useful for unit tests, examples and short-lived processes.

## PDO storage

```php
use Tihloh\Prefab\Notifications\Stores\PdoNotificationStore;

$store = new PdoNotificationStore($pdo, 'notifications');
$notifications = new NotificationManager($store);
```

The default table name is `notifications`.

### Default schema fields

The PDO store expects fields representing:

| Field | Purpose |
|---|---|
| `id` | Notification identifier |
| `recipient_id` | Notification owner/recipient |
| `title` | Heading |
| `message` | Body text |
| `metadata` | Structured application metadata |
| `action_url` | Optional related application URL |
| `created_at` | Creation timestamp |
| `read_at` | Read timestamp or `NULL` |

A typical MySQL/MariaDB table is:

```sql
CREATE TABLE notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    recipient_id VARCHAR(191) NOT NULL,
    title VARCHAR(191) NOT NULL,
    message TEXT NOT NULL,
    metadata JSON NOT NULL,
    action_url VARCHAR(500) NULL,
    created_at DATETIME NOT NULL,
    read_at DATETIME NULL,
    INDEX idx_notifications_recipient (recipient_id),
    INDEX idx_notifications_read (read_at)
);
```

## Existing tables and column mapping

The PDO store supports column mapping so applications can reuse an existing notification schema instead of renaming project fields to Prefab defaults.

This is an important package rule: storage adapters should adapt to the host application where practical; the host application should not need to restructure its domain merely to use Prefab.

## Metadata

Metadata carries structured context that is useful to application code but does not belong in the human-readable title or message.

```php
[
    'document_id' => 1001,
    'process' => 'approval',
    'office' => 'PBO',
]
```

Applications own the metadata schema. Prefab Notifications does not impose domain-specific metadata keys.

## Action URLs

An action URL can point the interface to the object or page associated with a notice:

```php
'/documents/1001'
```

Notifications stores the value; routing, authorization and URL handling remain application responsibilities.

Never treat possession of an action URL as authorization to access the target resource.

## Storage contract

Notification persistence is replaceable through the package's store abstraction. The manager owns notification behavior while the store owns persistence.

```text
Application
    ↓
NotificationManager
    ↓
notification store
    ├── in-memory
    ├── PDO
    └── custom adapter
```

A custom store can integrate another database layer, framework ORM, remote service or test implementation without changing application calls to `NotificationManager`.

## Relationship to Prefab Users

Notifications does not require Prefab Users. When both are present, a user ID can naturally serve as the recipient ID:

```text
Prefab Users user #25
        ↓
recipient_id = 25
        ↓
Prefab Notifications
```

The application remains responsible for selecting the appropriate recipient.

## Relationship to Prefab Auth

Auth identifies the current account. Application code can use that identity when retrieving notifications:

```text
current authenticated user
        ↓
recipient ID
        ↓
recent / unread / unreadCount
```

Notifications itself does not authenticate requests.

## Relationship to Prefab Permissions

Notifications does not make authorization decisions. If an action URL points to a protected resource, the destination route/controller should enforce the required permission independently.

## Relationship to Prefab Messaging

The two packages have deliberately different responsibilities:

```text
Internal application notice
bell / inbox / unread state
        ↓
prefab-notifications

External communication
email / SMS / external channel
        ↓
prefab-messaging
```

An application may use both for one event:

```text
Document Approved
       ├── Notifications → in-app notice
       └── Messaging     → email/SMS
```

Neither action implies the other automatically.

## Relationship to Prefab Logs

Notifications represent information intended for a recipient. Logs represent audit/activity history. A project may log notification creation or dismissal when required, but notification records should not be treated as a replacement for an audit log.

## Operation tracing

Prefab's shared runtime provides developer operation tracing where a module operation has been instrumented.

The intended application-side usage is deliberately one line after the operation:

```php
$notifications->send($userId, $title, $message);

prefab_trace();
```

Detailed rendering:

```php
prefab_trace(true);
```

Tracing is diagnostic output, not application presentation. Instrumentation availability depends on the operation/module version in use.

Sensitive trace context is redacted by the shared Prefab runtime for common secret-bearing field names.

## Security notes

- Escape notification title/message when rendering them into HTML unless the application explicitly owns and sanitizes rich content.
- Treat metadata as application data, not trusted executable content.
- Authorize destination resources independently from notification ownership.
- Use parameterized persistence in custom stores.
- Do not expose notifications belonging to another recipient merely because their IDs are known.

## API reference

| API | Returns / effect | Purpose |
|---|---|---|
| `send()` | notification | Create a notification |
| `recent()` | notification list | Retrieve recent notifications for a recipient |
| `unread()` | notification list | Retrieve unread notifications |
| `unreadCount()` | integer | Count unread notifications |
| `markRead()` | store-dependent result | Mark one notification read |
| `markUnread()` | store-dependent result | Restore unread state |
| `delete()` | store-dependent result | Delete/dismiss one notification |

## Responsibility boundary

Prefab Notifications deliberately does not own:

| Concern | Owner |
|---|---|
| user accounts | Prefab Users / host application |
| authentication | Prefab Auth / host framework |
| authorization | Prefab Permissions / host application |
| email and SMS | Prefab Messaging |
| audit history | Prefab Logs |
| routing | Prefab Routes / host framework |
| real-time WebSocket transport | application/infrastructure adapter |
| background scheduling | job/scheduler layer |

## Design principle

Prefab Notifications is intentionally a small notification-state component rather than a communication framework:

```text
business event
     ↓
create internal notice
     ↓
recipient notification list
     ↓
read / unread / dismiss
```

The core rule is: **store and manage internal system-to-user notices; keep external delivery and unrelated application concerns separate.**
