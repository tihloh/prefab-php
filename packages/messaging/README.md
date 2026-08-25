# Prefab Messaging

**Prefab Messaging** is a standalone, framework-independent application-to-recipient messaging layer for PHP.

> Send a simple message directly. Use structured notifications and multiple channels only when the application needs them.

Messaging intentionally covers communication **to people/users/recipients**. It is not an HTTP client, event bus, webhook framework, queue system or networking library.

## Installation

```bash
composer require tihloh/prefab-messaging
```

## Core model

```text
Application
    ↓
MessagingManager
    ↓
Message / Notification
    ↓
ChannelInterface
    ├── mail
    ├── inbox
    ├── SMS adapter
    ├── push adapter
    └── custom channel
    ↓
DeliveryResult
```

No channel provider is required by the core package. Applications register only the delivery channels they use.

## Simple direct message

A custom or provider-backed channel implements `ChannelInterface`:

```php
use Tihloh\Prefab\Messaging\Channels\CallableChannel;
use Tihloh\Prefab\Messaging\MessagingManager;

$messaging = new MessagingManager([
    new CallableChannel('mail', function ($recipient, $message) {
        $email = $recipient->route('mail');

        // Deliver using your SMTP/provider library here.
        return 'provider-message-id';
    }),
]);

$result = $messaging->mail(
    'user@example.com',
    'Annual Report',
    'Please see the attached report.',
);
```

The direct API keeps small applications small. A notification class is not required merely to send one message.

## Recipients

```php
$recipient = new Recipient(
    id: 25,
    name: 'Demo User',
    routes: [
        'mail' => 'user@example.com',
        'sms' => '+639000000000',
        'inbox' => 25,
    ],
);
```

The recipient describes **where** each channel should deliver. This keeps channel routing separate from message content.

## Structured notifications

Notifications are useful when one application event may be delivered through several channels:

```php
final class DocumentApproved implements NotificationInterface
{
    public function channels(Recipient $recipient): array
    {
        return ['mail', 'inbox'];
    }

    public function message(string $channel, Recipient $recipient): Message
    {
        return new Message(
            subject: 'Document approved',
            text: 'Your document has been approved.',
        );
    }
}

$results = $messaging->notify(
    $recipient,
    new DocumentApproved(),
);
```

A notification is therefore a higher-level messaging feature, not the entire package.

## Custom channels

Every channel follows the same small contract:

```php
interface ChannelInterface
{
    public function name(): string;

    public function send(
        Recipient $recipient,
        Message $message,
    ): DeliveryResult;
}
```

This leaves SMTP libraries, SMS providers, push providers and application-specific inbox implementations outside the core.

## Delivery results

Every delivery produces a `DeliveryResult`:

```php
$result->successful;
$result->failed();
$result->channel;
$result->messageId;
$result->error;
$result->metadata;
```

Provider-specific details can live in metadata without leaking provider APIs into application code.

## Lifecycle hooks

```php
$messaging->on('sent', function (
    $channel,
    $recipient,
    $message,
    $result,
) {
    // logging, metrics, etc.
});
```

Supported lifecycle points in the initial API are:

```text
sending
sent
failed
```

Hooks allow Prefab Logs, metrics or future Jobs integration without making those packages hard dependencies.

## Attachments and templates

Attachments and reusable template/message builders belong in Messaging, but they should remain transport-neutral. Prefab Files can later provide stored file resources while Messaging only describes what should be delivered.

```text
Prefab Files      → owns stored files
Prefab Messaging  → owns recipient communication
Mail channel      → converts attachments/message into provider format
```

The first core API deliberately does not hard-code a particular SMTP or template engine.

## Intended integrations

```text
Prefab Users
    ↓ optional recipient resolution
Prefab Messaging
    ↓
channels

Prefab Files ─────→ optional attachments
Prefab Logs  ←──── lifecycle hooks
Future Jobs  ←──── asynchronous delivery adapter
```

## What does NOT belong here?

```text
HTTP clients / REST calls      → not Messaging
webhooks/system integration    → not Messaging
internal application events    → future Prefab Events
queue workers                   → future Prefab Jobs
cron/scheduling                 → future Prefab Tasks
file storage                    → Prefab Files
access control                  → Prefab Permissions
```

## Design philosophy

Prefab Messaging follows the same Prefab rules:

1. **Standalone first** — no Users, Files, Logs or Jobs package is required.
2. **Simple direct usage** — sending one message should not require a notification class.
3. **Progressive capability** — structured notifications and multiple channels are additive.
4. **Provider-neutral core** — SMTP/SMS/push providers implement small contracts rather than defining the package API.
5. **No hidden hard dependencies** — integrations remain optional.
6. **Clear responsibility** — Messaging communicates with recipients; it does not become an event bus, HTTP client or queue framework.

The core principle is: **one messaging abstraction for recipient communication, with notifications and email as capabilities rather than separate package silos.**
