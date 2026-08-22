<?php

namespace Tihloh\Prefab\Auth\Contracts;

interface SocialStateStoreInterface
{
    public function issue(string $provider): string;
    public function validate(string $provider, string $state): bool;
}
