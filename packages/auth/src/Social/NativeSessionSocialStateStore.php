<?php

namespace Tihloh\Prefab\Auth\Social;

use Tihloh\Prefab\Auth\Contracts\SocialStateStoreInterface;
use Tihloh\Prefab\Auth\Session\SessionScope;

final class NativeSessionSocialStateStore implements SocialStateStoreInterface
{
    private string $scopedKey;

    public function __construct(private string $key = 'auth:social_state')
    {
        SessionScope::start();
        $this->scopedKey = SessionScope::key($this->key);
    }

    public function issue(string $provider): string
    {
        $state = bin2hex(random_bytes(32));
        $_SESSION[$this->scopedKey][$provider] = $state;
        return $state;
    }

    public function validate(string $provider, string $state): bool
    {
        $expected = $_SESSION[$this->scopedKey][$provider] ?? null;
        unset($_SESSION[$this->scopedKey][$provider]);
        return is_string($expected) && hash_equals($expected, $state);
    }
}
