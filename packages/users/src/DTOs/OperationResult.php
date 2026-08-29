<?php

namespace Tihloh\Prefab\Users\DTOs;

use Tihloh\Prefab\PrefabRuntime;

final class OperationResult
{
    public function __construct(
        public mixed $data,
        public array $log,
    ) {
        $operation = (string) ($log['action'] ?? 'operation');
        PrefabRuntime::traceStart('users', $operation, [
            'actor_id' => $log['actor_id'] ?? null,
            'subject_id' => $log['subject_id'] ?? null,
        ]);
        PrefabRuntime::traceEnd([
            'result' => is_object($data) ? $data::class : get_debug_type($data),
        ]);
    }
}
