<?php

namespace Tihloh\Prefab\Auth\Contracts;

interface AuthUserProviderInterface
{
    public function findByIdentifier(string $identifier): ?AuthenticatableUserInterface;
    public function findById(int|string $id): ?AuthenticatableUserInterface;
}
