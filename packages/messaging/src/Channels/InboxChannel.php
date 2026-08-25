<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Messaging\Channels;

use Tihloh\Prefab\Messaging\Contracts\ChannelInterface;
use Tihloh\Prefab\Messaging\DeliveryResult;
use Tihloh\Prefab\Messaging\Inbox\InboxStoreInterface;
use Tihloh\Prefab\Messaging\Message;
use Tihloh\Prefab\Messaging\Recipient;

final class InboxChannel implements ChannelInterface
{
    public function __construct(private readonly InboxStoreInterface $store) {}

    public function name(): string
    {
        return 'inbox';
    }

    public function send(Recipient $recipient, Message $message): DeliveryResult
    {
        if ($recipient->route('inbox') === null && $recipient->id === null) {
            return new DeliveryResult(false, 'inbox', error: 'Recipient has no inbox route or identifier.');
        }

        try {
            $id = $this->store->store($recipient, $message);
            return new DeliveryResult(true, 'inbox', $id === null ? null : (string) $id);
        } catch (\Throwable $e) {
            return new DeliveryResult(false, 'inbox', error: $e->getMessage());
        }
    }
}
