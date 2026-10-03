<?php

declare(strict_types=1);

namespace Tihloh\Prefab\AuthServer\Entities;

use JsonSerializable;
use League\OAuth2\Server\Entities\ScopeEntityInterface;

final class ScopeEntity implements ScopeEntityInterface, JsonSerializable
{
    public function __construct(private string $identifier) {}

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function jsonSerialize(): string
    {
        return $this->identifier;
    }
}
