<?php

namespace Tihloh\Prefab\Logs\DTOs;

final readonly class LogEntry
{
    public function __construct(
        public string $action,
        public string $subjectType,
        public int|string|null $subjectId = null,
        public ?string $message = null,
        public int|string|null $actorId = null,
        public array $changes = [],
        public array $metadata = [],
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
        public ?string $occurredAt = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            action: (string) ($data['action'] ?? ''),
            subjectType: (string) ($data['subject_type'] ?? $data['subjectType'] ?? ''),
            subjectId: $data['subject_id'] ?? $data['subjectId'] ?? null,
            message: $data['message'] ?? null,
            actorId: $data['actor_id'] ?? $data['actorId'] ?? null,
            changes: is_array($data['changes'] ?? null) ? $data['changes'] : [],
            metadata: is_array($data['metadata'] ?? null) ? $data['metadata'] : [],
            ipAddress: $data['ip_address'] ?? $data['ipAddress'] ?? null,
            userAgent: $data['user_agent'] ?? $data['userAgent'] ?? null,
            occurredAt: $data['occurred_at'] ?? $data['occurredAt'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'action' => $this->action,
            'subject_type' => $this->subjectType,
            'subject_id' => $this->subjectId,
            'message' => $this->message,
            'actor_id' => $this->actorId,
            'changes' => $this->changes,
            'metadata' => $this->metadata,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
