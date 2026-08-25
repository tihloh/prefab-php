<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Messaging\Contracts;

use Tihloh\Prefab\Messaging\Message;
use Tihloh\Prefab\Messaging\Recipient;

interface NotificationInterface
{
    /** @return list<string> */
    public function channels(Recipient $recipient): array;

    public function message(string $channel, Recipient $recipient): Message;
}
