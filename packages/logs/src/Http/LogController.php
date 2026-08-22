<?php

namespace Tihloh\Prefab\Logs\Http;

use Tihloh\Prefab\Logs\Services\LogManager;

final class LogController
{
    public function __construct(
        private LogManager $logs,
    ) {}

    public function record(array $payload): array
    {
        $id = $this->logs->record($payload);
        return ['success' => true, 'id' => $id];
    }

    public function show(int|string $id): array
    {
        return ['data' => $this->logs->find($id)];
    }

    public function recent(int $limit = 100, int $offset = 0): array
    {
        return ['data' => $this->logs->recent($limit, $offset)];
    }

    public function subject(string $type, int|string $id, int $limit = 100): array
    {
        return ['data' => $this->logs->forSubject($type, $id, $limit)];
    }

    public function actor(int|string $id, int $limit = 100): array
    {
        return ['data' => $this->logs->forActor($id, $limit)];
    }
}
