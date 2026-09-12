<?php

namespace Tihloh\Prefab\Logs\DTOs;

use Tihloh\Prefab\PrefabRuntime;

final class LogEntry
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
    ) {
        $this->changes = self::normalizeChanges($changes);

        PrefabRuntime::traceStart('logs', 'entry', [
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
        ]);
        PrefabRuntime::traceEnd([
            'actor_id' => $actorId,
            'changes' => count($this->changes),
        ]);
    }

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

    public static function changes(array $before, array $now, array $ignore = []): array
    {
        $changes = [];
        $fields = array_unique([...array_keys($before), ...array_keys($now)]);

        foreach ($fields as $field) {
            if (in_array($field, $ignore, true)) { continue; }

            $old = $before[$field] ?? null;
            $new = $now[$field] ?? null;

            if (self::same($old, $new)) { continue; }

            $changes[$field] = [
                'before' => $old,
                'now' => $new,
            ];
        }

        return $changes;
    }

    public static function normalizeChanges(array $changes): array
    {
        $normalized = [];

        foreach ($changes as $field => $change) {
            if (!is_array($change)) { continue; }

            $before = array_key_exists('before', $change)
                ? $change['before']
                : ($change['old'] ?? null);
            $now = array_key_exists('now', $change)
                ? $change['now']
                : ($change['new'] ?? null);

            if (self::same($before, $now)) { continue; }

            $normalized[$field] = [
                'before' => $before,
                'now' => $now,
            ];
        }

        return $normalized;
    }

    private static function same(mixed $before, mixed $now): bool
    {
        if (is_array($before) || is_array($now)) {
            return json_encode($before) === json_encode($now);
        }

        return $before === $now;
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
