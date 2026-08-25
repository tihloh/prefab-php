<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Tihloh\Prefab\Messaging\Channels\CallableChannel;
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
]);

$messaging->on('sent', function ($channel) use (&$events) {
    $events[] = $channel;
});

$result = $messaging->mail('demo@example.com', 'Hello', 'Prefab Messaging');
assert($result->successful === true);
assert($result->messageId === 'mail-1');
assert($sent[0] === ['demo@example.com', 'Hello', 'Prefab Messaging']);

$recipient = new Recipient(25, 'Demo', ['sms' => '+639000000000']);
$messaging->channel(new CallableChannel('sms', fn () => 'sms-1'));
$sms = $messaging->send('sms', $recipient, new Message('OTP', '123456'));
assert($sms->successful === true);
assert($events === ['mail', 'sms']);

echo "Prefab Messaging smoke OK\n";
