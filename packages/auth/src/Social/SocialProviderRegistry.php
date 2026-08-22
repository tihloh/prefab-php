<?php

namespace Tihloh\Prefab\Auth\Social;

use InvalidArgumentException;
use Tihloh\Prefab\Auth\Contracts\SocialProviderInterface;

final class SocialProviderRegistry
{
    /** @var array<string, SocialProviderInterface> */
    private array $providers = [];

    public function register(SocialProviderInterface $provider): void
    {
        $this->providers[$provider->name()] = $provider;
    }

    public function get(string $name): SocialProviderInterface
    {
        if (!isset($this->providers[$name])) {
            throw new InvalidArgumentException("Unknown social provider: {$name}");
        }
        return $this->providers[$name];
    }

    public function names(): array
    {
        return array_keys($this->providers);
    }
}
