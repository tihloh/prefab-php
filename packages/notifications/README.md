# Prefab Notifications

**Prefab Notifications** provides framework-independent internal system-to-user notifications for PHP applications.

> Notifications stay inside the application. External delivery belongs to Prefab Messaging.

## Installation

```bash
composer require tihloh/prefab-notifications
```

## Quick start

```php
use Tihloh\Prefab\Notifications\NotificationManager;

$notifications = new NotificationManager();

$notification = $notifications->send(
    25,
    'Document Approved',
    'OBR-2026-001 has been approved.',
    ['document_id' => 1001],
    '/documents/1001',
);
```

The default store is in-memory, which is useful for tests and small transient usage. Persistent applications can supply a store implementation such as `PdoNotificationStore`.

## Reading notifications

```php
$recent = $notifications->recent(25);
$unread = $notifications->unread(25);
$count = $notifications->unreadCount(25);
```

## Read state

```php
$notifications->markRead($notification->id);
$notifications->markUnread($notification->id);
```

Delete/dismiss a notification:

```php
$notifications->delete($notification->id);
```

## PDO persistence

```php
use Tihloh\Prefab\Notifications\NotificationManager;
use Tihloh\Prefab\Notifications\Stores\PdoNotificationStore;

$notifications = new NotificationManager(
    new PdoNotificationStore($pdo, 'notifications')
);
```

The expected default columns are:

```text
id
recipient_id
title
message
metadata
action_url
created_at
read_at
```

Column names can be mapped through the store constructor so an existing application table can be reused.

## Scope

Prefab Notifications owns internal notices such as:

```text
System → user
"Document approved"
"New document received"
"Report is ready"
```

It does not own:

```text
Email / SMS / external push   → Prefab Messaging
User-to-user chat             → separate future concern
Authentication                → Prefab Auth
Authorization                 → Prefab Permissions
Audit history                 → Prefab Logs
```

An application may intentionally use both modules for the same event:

```text
Document Approved
       │
       ├── Prefab Notifications → bell/in-app notice
       └── Prefab Messaging     → email/SMS
```

Neither package requires the other.

## API quick reference

| API | Purpose |
|---|---|
| `send()` | Create an internal notification |
| `recent()` | Get recent notifications for a recipient |
| `unread()` | Get unread notifications |
| `unreadCount()` | Count unread notifications |
| `markRead()` | Mark a notification read |
| `markUnread()` | Restore unread state |
| `delete()` | Remove/dismiss a notification |

## Design philosophy

The initial module stays deliberately small. It models internal notification storage and read state without becoming a chat system, external delivery service, real-time socket framework, or workflow engine.

The core principle is: **store and manage simple internal system-to-user notices; add advanced behavior only when real applications require it.**
