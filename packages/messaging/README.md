# Prefab Messaging

Framework-independent external outbound communication for PHP applications.

> Messaging sends information outside the application. Internal bell/inbox notices belong to Prefab Notifications.

This README is the package reference documentation. Step-by-step learning examples belong in the Prefab tutorial site.

## Requirements

- PHP 8.1 or newer
- Composer when installed as a package
- Network access and provider credentials for external transports such as SMTP

## Installation

```bash
composer require tihloh/prefab-messaging
```

## Package scope

Prefab Messaging provides a common application-facing API for outbound delivery through replaceable channels and transports.

```text
Application
    ↓
MessagingManager
    ↓
ChannelInterface
    ├── mail
    ├── SMS adapter
    ├── external push adapter
    └── custom channel
    ↓
External recipient/provider
```

It is not an internal notification center, user-to-user chat system, HTTP client, webhook framework, event bus, queue worker or scheduler.

## MessagingManager

`MessagingManager` coordinates named delivery channels.

```php
$messaging = new MessagingManager([
    $mailChannel,
    $smsChannel,
]);
```

Application code sends through a channel name instead of depending directly on provider-specific APIs.

## Generic delivery

```php
$result = $messaging->send(
    'sms',
    $recipient,
    new Message('OTP', 'Your code is 123456'),
);
```

`send()` selects the requested channel, resolves the recipient route for that channel, executes delivery and returns a `DeliveryResult`.

## Recipient

`Recipient` contains channel-specific destination routes.

```php
$recipient = new Recipient([
    'mail' => 'user@example.com',
    'sms' => '+639000000000',
]);
```

A recipient can therefore expose multiple delivery destinations without forcing the manager to know the domain model of the application's users.

The application remains responsible for obtaining and validating contact information.

## Message

`Message` represents outbound content independently from a transport.

Typical content includes:

- subject;
- plain text body;
- HTML body where supported;
- attachments;
- transport-independent message information.

Example:

```php
$message = new Message(
    subject: 'Annual Report',
    text: 'Your report is ready.',
);
```

## DeliveryResult

Every delivery returns a `DeliveryResult` describing the outcome rather than requiring application code to interpret transport internals.

A result can carry information such as:

| Information | Purpose |
|---|---|
| success/failure | Overall delivery state |
| channel | Channel used for delivery |
| provider message ID | Optional identifier returned by the external provider |
| error | Failure information when available |
| metadata | Provider/channel-specific result context |

Applications should use the result to decide whether to display success, log failure, retry through their own job system or take another domain-specific action.

## CallableChannel

`CallableChannel` provides a lightweight custom/test channel using a PHP callback.

```php
$channel = new CallableChannel(
    'test',
    function (Recipient $recipient, Message $message) {
        return 'TEST-123';
    }
);
```

It is useful for tests, development and small project-specific integrations that do not need a dedicated channel class.

## ChannelInterface

Provider-specific delivery belongs behind `ChannelInterface`.

Conceptually:

```text
MessagingManager
      ↓
ChannelInterface
      ↓
provider-specific implementation
```

A custom channel can implement SMS, external push, chat-provider delivery or another outbound transport while application code continues to call `send()`.

## Mail convenience API

For the common email case:

```php
$result = $messaging->mail(
    'user@example.com',
    'Annual Report',
    'Your report is ready.',
);
```

`mail()` expects an available mail channel and is a convenience layer over the general delivery architecture.

## MailChannel

`MailChannel` adapts a mail transport to the common Messaging channel API.

```php
$messaging = new MessagingManager([
    new MailChannel($transport),
]);
```

The channel owns mail-specific message preparation while the transport owns the actual delivery mechanism.

## NativeMailTransport

`NativeMailTransport` provides zero-dependency delivery through PHP's native mail facilities. Its actual behavior depends on the host PHP/mail-server configuration.

Use it when the environment already provides a correctly configured native mail path. For explicit SMTP configuration, use `SmtpTransport`.

## SmtpTransport

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
```

Supported SMTP concerns include host/port configuration, authentication, STARTTLS/SSL, plain-text and HTML mail, CC/BCC, reply-to and attachments.

Do not commit SMTP credentials to source control. Load them from environment variables, secret management or another protected configuration source.

## Attachments

Existing file:

```php
$message = new Message(
    subject: 'Report',
    text: 'Your report is attached.',
    attachments: [
        Attachment::fromPath('/tmp/report.pdf'),
    ],
);
```

In-memory content can use `Attachment::fromData()`.

Applications should ensure attachment paths/content are authorized and appropriate for the intended recipient before sending.

## Templates

The lightweight `Template` helper performs simple `{{variable}}` substitution without requiring a template engine.

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

Template rendering produces a normal `Message`; delivery remains the responsibility of the selected channel.

## Lifecycle hooks

Messaging exposes synchronous delivery hooks:

```text
sending
sent
failed
```

Registration:

```php
$messaging->on('sending', $listener);
$messaging->on('sent', $listener);
$messaging->on('failed', $listener);
```

Useful callback forms include:

```php
$messaging->on('sending', function ($channel, $recipient, $message) {});
$messaging->on('sent', function ($channel, $recipient, $message, $result) {});
$messaging->on('failed', function ($channel, $recipient, $message, $result) {});
```

Hooks are appropriate for lightweight audit, metrics and integration behavior. Long-running retries and asynchronous processing belong in a queue/jobs layer.

## Failure handling

Transport/provider failure is represented through the delivery result and the `failed` lifecycle hook where applicable.

Application policy determines what happens next:

```text
failed delivery
    ├── show error
    ├── record audit/error
    ├── schedule retry
    └── use another channel
```

Messaging deliberately does not become a queue or retry scheduler merely because a provider can fail.

## Relationship to Prefab Notifications

```text
Internal application notice
bell / inbox / unread state
        ↓
prefab-notifications

External communication
email / SMS / external push
        ↓
prefab-messaging
```

An application may deliberately perform both actions for the same event. Neither package automatically triggers the other.

## Relationship to Prefab Users

Messaging does not depend on a particular user model. User contact information can be converted into a `Recipient` by application code.

```text
user profile
   ↓
email / phone
   ↓
Recipient
   ↓
Messaging
```

This keeps user storage independent from external delivery providers.

## Relationship to Prefab Auth

Authentication workflows can use Messaging for password-reset links, one-time codes, account alerts or other external security communication.

Messaging transports the message; Auth remains responsible for authentication state, token validity and security policy.

## Relationship to Prefab Files

Files can provide a stored path or application-generated file that is intentionally attached to a message. Authorization should occur before attachment creation/delivery.

Messaging does not grant access to a file merely because it can attach it.

## Relationship to Prefab Logs

The `sending`, `sent` and `failed` hooks are natural integration points for audit logging when a project needs delivery history.

Avoid storing sensitive message bodies, credentials or tokens in audit logs unless application policy explicitly requires and protects them.

## Operation tracing

Prefab's shared runtime provides developer operation tracing where a module operation has been instrumented.

The intended syntax is a separate line after the operation:

```php
$messaging->send('mail', $recipient, $message);

prefab_trace();
```

Detailed trace:

```php
prefab_trace(true);
```

Tracing is diagnostic output rather than application presentation. Instrumentation availability depends on the operation/module version in use.

The shared runtime redacts common secret-bearing context keys such as password, secret, token and authorization fields.

## Security considerations

- Keep SMTP/provider credentials outside source control.
- Validate recipient addresses/routes according to the selected channel.
- Do not expose provider errors containing secrets directly to end users.
- Authorize attachments before delivery.
- Escape or sanitize application-controlled HTML mail content according to its source and intended rendering.
- Treat reset links, OTPs and authentication tokens as secrets.
- Be deliberate about logging message contents and recipient information.

## API reference

| API / component | Purpose |
|---|---|
| `MessagingManager::send()` | Send through a named channel |
| `MessagingManager::mail()` | Convenience mail delivery |
| `MessagingManager::on()` | Register lifecycle hooks |
| `Message` | Transport-independent message content |
| `Recipient` | Channel-specific recipient routes |
| `DeliveryResult` | Delivery outcome |
| `ChannelInterface` | Custom channel contract |
| `CallableChannel` | Callback-based channel/test adapter |
| `MailChannel` | Mail channel adapter |
| `NativeMailTransport` | PHP-native mail transport |
| `SmtpTransport` | Built-in SMTP transport |
| `Attachment::fromPath()` | File-backed attachment |
| `Attachment::fromData()` | In-memory attachment |
| `Template` | Lightweight variable substitution |

## Responsibility boundary

Prefab Messaging deliberately does not own:

| Concern | Owner |
|---|---|
| internal bell/inbox state | Prefab Notifications |
| user accounts | Prefab Users / host application |
| authentication | Prefab Auth |
| authorization | Prefab Permissions / application |
| audit persistence | Prefab Logs |
| file authorization/storage | Prefab Files / application |
| queues and retries | jobs/queue layer |
| scheduling | scheduler layer |
| inbound webhooks | HTTP/integration layer |
| user-to-user chat | separate communication/domain component |

## Design principle

```text
application message
       ↓
Recipient + Message
       ↓
MessagingManager
       ↓
replaceable channel/transport
       ↓
external provider
```

The core rule is: **deliver outbound application messages through replaceable channels without turning Messaging into the rest of the application framework.**
