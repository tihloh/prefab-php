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
        $name = $this->normalize($provider->name());

        if ($name === '') {
            throw new InvalidArgumentException(
                'Social provider name cannot be empty.',
            );
        }

        if (isset($this->providers[$name])) {
            throw new InvalidArgumentException(
                "Social provider is already registered: {$name}",
            );
        }

        $this->providers[$name] = $provider;
    }

    public function get(string $name): SocialProviderInterface
    {
        $name = $this->normalize($name);

        if (!isset($this->providers[$name])) {
            throw new InvalidArgumentException(
                "Unknown social provider: {$name}",
            );
        }

        return $this->providers[$name];
    }

    public function names(): array
    {
        return array_keys($this->providers);
    }

    private function normalize(string $name): string
    {
        return strtolower(trim($name));
    }
}
