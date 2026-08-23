<?php

namespace Tihloh\Prefab\Auth\DTOs;

use Tihloh\Prefab\Auth\Contracts\AuthenticatableUserInterface;

final class AuthResult
{
    public function __construct(
        public bool $success,
        public ?AuthenticatableUserInterface $user = null,
        public ?array $log = null,
        public ?string $reason = null,
    ) {}
}
