<?php

declare(strict_types=1);

namespace Tihloh\Prefab\AuthServer\Entities;

use League\OAuth2\Server\Entities\UserEntityInterface;

final class UserEntity implements UserEntityInterface
{
    public function __construct(private string $identifier) {}

    public function getIdentifier(): string
    {
        return $this->identifier;
    }
}
