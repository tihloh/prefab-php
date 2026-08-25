<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Tihloh\Prefab\Notifications\NotificationManager;

$notifications = new NotificationManager();

$n = $notifications->send(25, 'Document Approved', 'Your document was approved.', ['document_id' => 1001], '/documents/1001');
assert($n->isUnread());
assert($notifications->unreadCount(25) === 1);
assert(count($notifications->recent(25)) === 1);
assert(count($notifications->unread(25)) === 1);

assert($notifications->markRead($n->id));
assert($notifications->unreadCount(25) === 0);
assert($notifications->markUnread($n->id));
assert($notifications->unreadCount(25) === 1);
assert($notifications->delete($n->id));
assert(count($notifications->recent(25)) === 0);

echo "Prefab Notifications smoke OK\n";
