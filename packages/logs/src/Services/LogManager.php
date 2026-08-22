<?php

namespace Tihloh\Prefab\Logs\Services;

use InvalidArgumentException;
use Tihloh\Prefab\Logs\Contracts\LogRepositoryInterface;
use Tihloh\Prefab\Logs\DTOs\LogEntry;

final class LogManager
{
    public function __construct(
        private LogRepositoryInterface $repository,
    ) {}

    public function record(LogEntry|array $entry): int|string
    {
        $entry = is_array($entry) ? LogEntry::fromArray($entry) : $entry;

        if ($entry->action === '' || $entry->subjectType === '') {
            throw new InvalidArgumentException('Log entry requires action and subject type.');
        }

        return $this->repository->record($entry);
    }

    public function find(int|string $id): ?array
    {
        return $this->repository->find($id);
    }

    public function recent(int $limit = 100, int $offset = 0): array
    {
        return $this->repository->recent($limit, $offset);
    }

    public function forSubject(string $subjectType, int|string $subjectId, int $limit = 100): array
    {
        return $this->repository->forSubject($subjectType, $subjectId, $limit);
    }

    public function forActor(int|string $actorId, int $limit = 100): array
    {
        return $this->repository->forActor($actorId, $limit);
    }
}
