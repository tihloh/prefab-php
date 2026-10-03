<?php

declare(strict_types=1);

namespace Tihloh\Prefab\AuthServer\Services;

use RuntimeException;

final class JwkSetService
{
    public function __construct(
        private string $publicKey,
        private ?string $keyId = null,
    ) {}

    /** @return array{keys:array<int,array<string,string>>} */
    public function document(): array
    {
        $pem = $this->keyContents($this->publicKey);
        $key = openssl_pkey_get_public($pem);

        if ($key === false) {
            throw new RuntimeException('Unable to load the OIDC public key.');
        }

        $details = openssl_pkey_get_details($key);

        if (
            !is_array($details)
            || !isset($details['rsa']['n'], $details['rsa']['e'])
        ) {
            throw new RuntimeException('OIDC signing key must be an RSA key.');
        }

        return [
            'keys' => [[
                'kty' => 'RSA',
                'use' => 'sig',
                'alg' => 'RS256',
                'kid' => $this->keyId ?? self::keyIdFor($pem),
                'n' => $this->base64Url((string) $details['rsa']['n']),
                'e' => $this->base64Url((string) $details['rsa']['e']),
            ]],
        ];
    }

    public static function keyIdFor(string $publicKeyPem): string
    {
        return substr(hash('sha256', trim($publicKeyPem)), 0, 32);
    }

    public static function contents(string $keyOrPath): string
    {
        if (is_file($keyOrPath)) {
            $contents = file_get_contents($keyOrPath);

            if ($contents === false) {
                throw new RuntimeException(
                    'Unable to read OIDC signing key: ' . $keyOrPath
                );
            }

            return $contents;
        }

        return $keyOrPath;
    }

    private function keyContents(string $keyOrPath): string
    {
        return self::contents($keyOrPath);
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
