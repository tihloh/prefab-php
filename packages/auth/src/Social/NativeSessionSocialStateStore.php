<?php

namespace Tihloh\Prefab\Auth\Social;

use Tihloh\Prefab\Auth\Contracts\SocialStateStoreInterface;
use Tihloh\Prefab\Auth\Session\SessionScope;

final class NativeSessionSocialStateStore implements SocialStateStoreInterface
{
    private string $scopedKey;

    public function __construct(
        private string $key = 'auth:social_state',
        private int $ttlSeconds = 600,
        private int $maxPendingPerProvider = 5,
    ) {
        SessionScope::start();
        $this->scopedKey = SessionScope::key($this->key);
    }

    public function issue(string $provider): string
    {
        $provider = strtolower(trim($provider));
        $this->purgeExpired($provider);

        $state = bin2hex(random_bytes(32));
        $hash = hash('sha256', $state);

        $_SESSION[$this->scopedKey][$provider][$hash] = time();

        $pending = &$_SESSION[$this->scopedKey][$provider];
        if (count($pending) > $this->maxPendingPerProvider) {
            asort($pending, SORT_NUMERIC);
            while (count($pending) > $this->maxPendingPerProvider) {
                array_shift($pending);
            }
        }

        return $state;
    }

    public function validate(string $provider, string $state): bool
    {
        $provider = strtolower(trim($provider));
        $this->purgeExpired($provider);

        $hash = hash('sha256', $state);
        $issuedAt = $_SESSION[$this->scopedKey][$provider][$hash] ?? null;

        if (!is_int($issuedAt)) {
            return false;
        }

        unset($_SESSION[$this->scopedKey][$provider][$hash]);

        return (time() - $issuedAt) <= $this->ttlSeconds;
    }

    private function purgeExpired(string $provider): void
    {
        $pending = $_SESSION[$this->scopedKey][$provider] ?? null;

        if (!is_array($pending)) {
            $_SESSION[$this->scopedKey][$provider] = [];
            return;
        }

        $cutoff = time() - $this->ttlSeconds;

        foreach ($pending as $hash => $issuedAt) {
            if (!is_int($issuedAt) || $issuedAt < $cutoff) {
                unset($_SESSION[$this->scopedKey][$provider][$hash]);
            }
        }
    }
}
