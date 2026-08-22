<?php

namespace Tihloh\Prefab\Users\Contracts;

use Tihloh\Prefab\Users\User\PrefabUser;

interface UserFactoryInterface
{
    public function make(
        int|string $id,
        ?string $name,
        ?string $email,
        bool $active,
        array $attributes = [],
    ): PrefabUser;
}
