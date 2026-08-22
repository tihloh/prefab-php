<?php

namespace Tihloh\Prefab\Users\User;

use Tihloh\Prefab\Users\Contracts\UserFactoryInterface;

final class DefaultUserFactory implements UserFactoryInterface
{
    public function make(
        int|string $id,
        ?string $name,
        ?string $email,
        bool $active,
        array $attributes = [],
    ): PrefabUser {
        return new PrefabUser($id, $name, $email, $active, $attributes);
    }
}
