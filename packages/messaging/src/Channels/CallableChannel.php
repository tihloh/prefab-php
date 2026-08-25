<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Messaging\Channels;

use RuntimeException;
use Tihloh\Prefab\Messaging\Contracts\ChannelInterface;
use Tihloh\Prefab\Messaging\DeliveryResult;
use Tihloh\Prefab\Messaging\Message;
use Tihloh\Prefab\Messaging\Recipient;

final class CallableChannel implements ChannelInterface
{
    /** @param callable(Recipient, Message): DeliveryResult|bool|string|null $sender */
    public function __construct(
        private readonly string $channelName,
        private readonly mixed $sender,
    ) {
        if (!is_callable($sender)) {
            throw new RuntimeException('CallableChannel sender must be callable.');
        }
    }

    public function name(): string
    {
        return $this->channelName;
    }

    public function send(Recipient $recipient, Message $message): DeliveryResult
    {
        $result = ($this->sender)($recipient, $message);
        if ($result instanceof DeliveryResult) {
            return $result;
        }
        if ($result === false) {
            return new DeliveryResult(false, $this->channelName, error: 'Channel sender returned false.');
        }
        return new DeliveryResult(true, $this->channelName, is_string($result) ? $result : null);
    }
}
