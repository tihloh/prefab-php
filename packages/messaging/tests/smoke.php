<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Tihloh\Prefab\Messaging\Channels\CallableChannel;
use Tihloh\Prefab\Messaging\Contracts\NotificationInterface;
use Tihloh\Prefab\Messaging\Message;
use Tihloh\Prefab\Messaging\MessagingManager;
use Tihloh\Prefab\Messaging\Recipient;

$sent = [];
$events = [];

$messaging = new MessagingManager([
    new CallableChannel('mail', function (Recipient $recipient, Message $message) use (&$sent) {
        $sent[] = [$recipient->route('mail'), $message->subject, $message->text];
        return 'mail-1';
    }),
    new CallableChannel('inbox', fn () => 'inbox-1'),
]);

$messaging->on('sent', function ($channel) use (&$events) {
    $events[] = $channel;
});

$result = $messaging->mail('demo@example.com', 'Hello', 'Prefab Messaging');
assert($result->successful === true);
assert($result->messageId === 'mail-1');
assert($sent[0] === ['demo@example.com', 'Hello', 'Prefab Messaging']);

$recipient = new Recipient(25, 'Demo', ['mail' => 'demo@example.com', 'inbox' => 25]);
$notification = new class implements NotificationInterface {
    public function channels(Recipient $recipient): array { return ['mail', 'inbox']; }
    public function message(string $channel, Recipient $recipient): Message { return new Message('Status', "Delivered by {$channel}"); }
};

$results = $messaging->notify($recipient, $notification);
assert(count($results) === 2);
assert($results[0]->successful && $results[1]->successful);
assert($events === ['mail', 'mail', 'inbox']);

echo "Prefab Messaging smoke OK\n";
