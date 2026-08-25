<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Messaging\Mail;

use Tihloh\Prefab\Messaging\DeliveryResult;
use Tihloh\Prefab\Messaging\Message;
use Tihloh\Prefab\Messaging\Recipient;

interface MailTransportInterface
{
    public function send(Recipient $recipient, Message $message): DeliveryResult;
}
