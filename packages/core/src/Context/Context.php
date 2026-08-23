<?php

namespace Tihloh\Prefab\Core\Context;

final class Context
{
    private array $values = [];
    private $actorResolver = null;

    public function set(string $key, mixed $value): self { $this->values[$key] = $value; return $this; }
    public function get(string $key, mixed $default = null): mixed { return $this->values[$key] ?? $default; }
    public function actorUsing(callable $resolver): self { $this->actorResolver = $resolver; return $this; }
    public function actorId(): int|string|null { return $this->actorResolver ? ($this->actorResolver)() : $this->get('actor_id'); }

    public function logContext(): array
    {
        return [
            'actor_type' => $this->get('actor_type', $this->actorId() !== null ? 'user' : null),
            'actor_id' => $this->actorId(),
            'ip_address' => $this->get('ip_address', $_SERVER['REMOTE_ADDR'] ?? null),
            'user_agent' => $this->get('user_agent', $_SERVER['HTTP_USER_AGENT'] ?? null),
            'metadata' => $this->get('metadata', []),
        ];
    }
}
