<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Messaging\Inbox;

use PDO;
use Tihloh\Prefab\Messaging\Message;
use Tihloh\Prefab\Messaging\Recipient;

final class PdoInboxStore implements InboxStoreInterface
{
    /** @param array<string, string> $columns */
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $table = 'notifications',
        private readonly array $columns = [],
    ) {}

    public function store(Recipient $recipient, Message $message): string|int|null
    {
        $c = array_merge([
            'recipient' => 'recipient_id',
            'subject' => 'subject',
            'text' => 'message',
            'metadata' => 'metadata',
            'created' => 'created_at',
        ], $this->columns);

        $recipientId = $recipient->route('inbox') ?? $recipient->id;
        $sql = sprintf(
            'INSERT INTO %s (%s, %s, %s, %s, %s) VALUES (:recipient, :subject, :text, :metadata, :created)',
            $this->identifier($this->table),
            $this->identifier($c['recipient']), $this->identifier($c['subject']),
            $this->identifier($c['text']), $this->identifier($c['metadata']), $this->identifier($c['created'])
        );
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'recipient' => $recipientId,
            'subject' => $message->subject,
            'text' => $message->text,
            'metadata' => json_encode($message->metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created' => date('Y-m-d H:i:s'),
        ]);
        $id = $this->pdo->lastInsertId();
        return $id === '' ? null : $id;
    }

    private function identifier(string $value): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value)) {
            throw new \InvalidArgumentException("Unsafe SQL identifier [{$value}].");
        }
        return $value;
    }
}
