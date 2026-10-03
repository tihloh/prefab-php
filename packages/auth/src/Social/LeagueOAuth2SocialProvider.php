<?php

namespace Tihloh\Prefab\Auth\Social;

use Closure;
use RuntimeException;
use Tihloh\Prefab\Auth\Contracts\SocialProviderInterface;

/**
 * Adapter for PHP League OAuth 2.0 provider clients.
 *
 * The external League provider package remains optional. This class uses the
 * common provider API at runtime so Prefab Auth does not force Google, GitHub,
 * Facebook, or another OAuth client onto applications that do not need them.
 */
final class LeagueOAuth2SocialProvider implements SocialProviderInterface
{
    public function __construct(
        private string $providerName,
        private object $provider,
        private ?Closure $identityMapper = null,
        private array $authorizationOptions = [],
    ) {
        foreach (['getAuthorizationUrl', 'getAccessToken', 'getResourceOwner'] as $method) {
            if (!method_exists($this->provider, $method)) {
                throw new RuntimeException(
                    "League OAuth provider must implement {$method}().",
                );
            }
        }
    }

    public static function google(object $provider, array $authorizationOptions = []): self
    {
        return new self('google', $provider, null, $authorizationOptions);
    }

    public static function github(object $provider, array $authorizationOptions = []): self
    {
        return new self('github', $provider, null, $authorizationOptions);
    }

    public static function facebook(object $provider, array $authorizationOptions = []): self
    {
        return new self('facebook', $provider, null, $authorizationOptions);
    }

    public function name(): string
    {
        return $this->providerName;
    }

    public function authorizationUrl(string $state): string
    {
        $options = array_replace($this->authorizationOptions, ['state' => $state]);

        return (string) $this->provider->getAuthorizationUrl($options);
    }

    public function identityFromCallback(array $query): SocialIdentity
    {
        if (!empty($query['error'])) {
            $message = trim((string) ($query['error_description'] ?? $query['error']));
            throw new RuntimeException(
                'OAuth provider rejected the sign-in request'
                . ($message !== '' ? ': ' . $message : '.'),
            );
        }

        $code = trim((string) ($query['code'] ?? ''));
        if ($code === '') {
            throw new RuntimeException('OAuth callback is missing the authorization code.');
        }

        $token = $this->provider->getAccessToken('authorization_code', [
            'code' => $code,
        ]);

        $owner = $this->provider->getResourceOwner($token);

        if ($this->identityMapper) {
            $identity = ($this->identityMapper)(
                $owner,
                $token,
                $query,
                $this->providerName,
            );

            if (!$identity instanceof SocialIdentity) {
                throw new RuntimeException(
                    'OAuth identity mapper must return SocialIdentity.',
                );
            }

            return $identity;
        }

        return $this->mapKnownProvider($owner);
    }

    private function mapKnownProvider(object $owner): SocialIdentity
    {
        $raw = method_exists($owner, 'toArray')
            ? (array) $owner->toArray()
            : [];

        $providerUserId = $this->stringValue(
            $this->firstMethodValue($owner, ['getId'])
                ?? $raw['id']
                ?? $raw['sub']
                ?? null,
        );

        if ($providerUserId === null) {
            throw new RuntimeException(
                "OAuth provider {$this->providerName} did not return a user identifier.",
            );
        }

        $email = $this->stringValue(
            $this->firstMethodValue($owner, ['getEmail'])
                ?? $raw['email']
                ?? null,
        );

        $name = $this->stringValue(
            $this->firstMethodValue($owner, ['getName'])
                ?? $raw['name']
                ?? null,
        );

        $username = $this->stringValue(
            $this->firstMethodValue($owner, ['getNickname', 'getUsername'])
                ?? $raw['login']
                ?? $raw['username']
                ?? null,
        );

        $avatar = $this->stringValue(
            $this->firstMethodValue($owner, [
                'getAvatar',
                'getAvatarUrl',
                'getPictureUrl',
            ])
                ?? $raw['avatar_url']
                ?? $raw['picture']
                ?? null,
        );

        if (is_array($raw['picture'] ?? null)) {
            $avatar = $this->stringValue(
                $raw['picture']['data']['url']
                    ?? $raw['picture']['url']
                    ?? $avatar,
            );
        }

        $emailVerified = null;
        if (array_key_exists('email_verified', $raw)) {
            $emailVerified = filter_var(
                $raw['email_verified'],
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE,
            );
        } elseif (array_key_exists('verified_email', $raw)) {
            $emailVerified = filter_var(
                $raw['verified_email'],
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE,
            );
        }

        return new SocialIdentity(
            provider: $this->providerName,
            providerUserId: $providerUserId,
            email: $email,
            name: $name,
            avatar: $avatar,
            raw: $raw,
            emailVerified: $emailVerified,
            username: $username,
        );
    }

    private function firstMethodValue(object $object, array $methods): mixed
    {
        foreach ($methods as $method) {
            if (method_exists($object, $method)) {
                $value = $object->{$method}();
                if ($value !== null && $value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    private function stringValue(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
