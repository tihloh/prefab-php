<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Messaging\Contracts;

use Tihloh\Prefab\Messaging\DeliveryResult;
use Tihloh\Prefab\Messaging\Message;
use Tihloh\Prefab\Messaging\Recipient;

interface ChannelInterface
{
    public function name(): string;

    public function send(Recipient $recipient, Message $message): DeliveryResult;
}
