<?php

namespace Tihloh\Prefab\Auth\Contracts;

interface AuthenticatableUserInterface
{
    public function authId(): int|string;
    public function authPasswordHash(): ?string;
    public function authIsActive(): bool;
}
