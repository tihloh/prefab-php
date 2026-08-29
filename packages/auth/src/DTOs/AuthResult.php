<?php

namespace Tihloh\Prefab\Auth\DTOs;

use Tihloh\Prefab\Auth\Contracts\AuthenticatableUserInterface;
use Tihloh\Prefab\PrefabRuntime;

final class AuthResult
{
    public function __construct(
        public bool $success,
        public ?AuthenticatableUserInterface $user = null,
        public ?array $log = null,
        public ?string $reason = null,
    ) {
        $operation = (string) ($log['action'] ?? 'attempt');
        PrefabRuntime::traceStart('auth', $operation);
        PrefabRuntime::traceEnd([
            'success' => $success,
            'user_id' => $user?->authId(),
            'reason' => $reason,
        ]);
    }
}
