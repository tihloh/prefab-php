<?php

namespace Tihloh\Prefab\Auth\Social;

use Tihloh\Prefab\Auth\Contracts\SocialStateStoreInterface;

final class NativeSessionSocialStateStore implements SocialStateStoreInterface
{
    public function __construct(private string $key = '_prefab_social_state')
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    }

    public function issue(string $provider): string
    {
        $state = bin2hex(random_bytes(32));
        $_SESSION[$this->key][$provider] = $state;
        return $state;
    }

    public function validate(string $provider, string $state): bool
    {
        $expected = $_SESSION[$this->key][$provider] ?? null;
        unset($_SESSION[$this->key][$provider]);
        return is_string($expected) && hash_equals($expected, $state);
    }
}
