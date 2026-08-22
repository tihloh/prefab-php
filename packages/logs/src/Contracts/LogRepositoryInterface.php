<?php

namespace Tihloh\Prefab\Logs\Contracts;

use Tihloh\Prefab\Logs\DTOs\LogEntry;

interface LogRepositoryInterface
{
    public function record(LogEntry $entry): int|string;

    public function find(int|string $id): ?array;

    /** @return list<array> */
    public function recent(int $limit = 100, int $offset = 0): array;

    /** @return list<array> */
    public function forSubject(string $subjectType, int|string $subjectId, int $limit = 100): array;

    /** @return list<array> */
    public function forActor(int|string $actorId, int $limit = 100): array;
}
