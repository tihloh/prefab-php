<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Messaging\Inbox;

use Tihloh\Prefab\Messaging\Message;
use Tihloh\Prefab\Messaging\Recipient;

interface InboxStoreInterface
{
    /** @return string|int|null Stored message identifier. */
    public function store(Recipient $recipient, Message $message): string|int|null;
}
