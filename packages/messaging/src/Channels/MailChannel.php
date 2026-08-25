<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Messaging\Channels;

use Tihloh\Prefab\Messaging\Contracts\ChannelInterface;
use Tihloh\Prefab\Messaging\DeliveryResult;
use Tihloh\Prefab\Messaging\Mail\MailTransportInterface;
use Tihloh\Prefab\Messaging\Message;
use Tihloh\Prefab\Messaging\Recipient;

final class MailChannel implements ChannelInterface
{
    public function __construct(private readonly MailTransportInterface $transport) {}

    public function name(): string { return 'mail'; }

    public function send(Recipient $recipient, Message $message): DeliveryResult
    {
        return $this->transport->send($recipient, $message);
    }
}
