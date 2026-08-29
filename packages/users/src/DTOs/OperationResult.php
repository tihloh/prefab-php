<?php

namespace Tihloh\Prefab\Users\DTOs;

/**
 * Result returned by user write operations.
 *
 * Tracing belongs to UserManager so the full operation, including database and
 * logging work, appears as one useful call tree instead of a DTO-construction
 * event.
 */
final class OperationResult
{
    public function __construct(
        public readonly mixed $data,
        public readonly array $log,
    ) {
    }
}
