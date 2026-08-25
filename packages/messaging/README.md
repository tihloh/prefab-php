# Prefab Messaging

**Prefab Messaging** provides framework-independent **external outbound communication** for PHP applications.

> Messaging sends information outside the application. Internal bell/inbox notices belong to Prefab Notifications.

## Installation

```bash
composer require tihloh/prefab-messaging
```

## Purpose

```text
Application
    ↓
Prefab Messaging
    ├── email / SMTP
    ├── SMS adapter
    ├── external push adapter
    └── custom external channel
    ↓
External recipient/provider
```

It is intentionally not an internal notification center, chat system, HTTP client, webhook framework, event bus, queue worker or scheduler.

## Simple mail

```php
$result = $messaging->mail(
    'user@example.com',
    'Annual Report',
    'Your report is ready.',
);
```

## Generic channel delivery

```php
$result = $messaging->send(
    'sms',
    $recipient,
    new Message('OTP', 'Your code is 123456'),
);
```

`Recipient` stores channel routes such as email addresses or phone numbers. `ChannelInterface` keeps provider-specific delivery outside application business logic.

## Mail

Messaging includes a `MailChannel`, a zero-dependency `NativeMailTransport`, and a built-in `SmtpTransport` for SMTP delivery.

SMTP supports host/port configuration, authentication, STARTTLS/SSL, plain text, HTML+text MIME, CC/BCC, reply-to and attachments.

```php
$smtp = new SmtpTransport([
    'host' => 'smtp.example.com',
    'port' => 587,
    'username' => 'user@example.com',
    'password' => 'secret',
    'encryption' => 'tls',
    'from' => [
        'address' => 'noreply@example.com',
        'name' => 'My System',
    ],
]);

$messaging = new MessagingManager([
    new MailChannel($smtp),
]);
```

## Attachments

```php
$message = new Message(
    subject: 'Report',
    text: 'Your report is attached.',
    attachments: [
        Attachment::fromPath('/tmp/report.pdf'),
    ],
);
```

Attachments can also be created from in-memory data with `Attachment::fromData()`.

## Templates

The lightweight `Template` helper supports simple `{{variable}}` replacement without forcing a template engine.

```php
$template = new Template(
    subject: 'Hello {{name}}',
    text: 'Your report {{report}} is ready.'
);

$message = $template->render([
    'name' => 'Demo User',
    'report' => 'AR-2026',
]);
```

## Delivery results and hooks

Every delivery returns `DeliveryResult` with success/failure, channel, optional provider message ID, error and metadata.

Lifecycle hooks are available for:

```text
sending
sent
failed
```

These can integrate with Prefab Logs or future Jobs without creating hard dependencies.

## Relationship to Prefab Notifications

```text
External communication
Email / SMS / external push
        ↓
prefab-messaging

Internal application notice
Bell / inbox / unread state
        ↓
prefab-notifications
```

An application can use both for the same business event, but neither package requires the other.

## Design philosophy

1. Standalone first.
2. Direct mail stays simple.
3. Provider-specific logic stays behind small channel/transport contracts.
4. Advanced channels are additive.
5. Internal notification storage/read state stays out of Messaging.
6. Messaging remains external outbound delivery rather than becoming a general communication framework.

The core principle is: **deliver application messages to external recipients through replaceable transports.**
