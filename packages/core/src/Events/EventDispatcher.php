<?php

namespace Tihloh\Prefab\Core\Events;

final class EventDispatcher
{
    /** @var array<string,list<callable>> */
    private array $listeners = [];

    public function listen(string $event, callable $listener): self
    {
        $this->listeners[$event][] = $listener;
        return $this;
    }

    public function dispatch(string $event, mixed $payload = null): void
    {
        foreach ($this->listeners[$event] ?? [] as $listener) $listener($payload, $event);
        foreach ($this->listeners['*'] ?? [] as $listener) $listener($payload, $event);
    }
}
