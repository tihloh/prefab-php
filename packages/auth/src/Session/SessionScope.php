<?php

namespace Tihloh\Prefab\Auth\Session;

use Tihloh\Prefab\PrefabConfig;

/**
 * Resolves one safe application session identity for Prefab Auth.
 *
 * Isolation is mandatory by default. If no session configuration exists,
 * Prefab derives a stable namespace and cookie path from the current app URL.
 * Supplying the same explicit session namespace intentionally gives apps the
 * same session identity; cookie domain/path may still be overridden when
 * sharing across hosts or unusual deployments.
 */
final class SessionScope
{
    private static ?array $resolved = null;

    /** @return array{namespace:string,name:string,path:string,active_before:bool} */
    public static function start(): array
    {
        if (self::$resolved !== null) {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
            return self::$resolved;
        }

        $config = PrefabConfig::get('session', []);
        $config = is_array($config) ? $config : [];
        $app = PrefabConfig::get('app', []);
        $app = is_array($app) ? $app : [];

        $explicitNamespace = isset($config['namespace'])
            && is_string($config['namespace'])
            && trim($config['namespace']) !== '';

        $namespace = self::safeId(
            $explicitNamespace
                ? $config['namespace']
                : (($app['id'] ?? null) ?: self::detectIdentity()),
        );

        $path = isset($config['path']) && is_string($config['path'])
            ? self::normalizePath($config['path'])
            : ($explicitNamespace ? '/' : self::detectUrlPath());

        $name = isset($config['name']) && is_string($config['name']) && trim($config['name']) !== ''
            ? self::safeCookieName($config['name'])
            : self::safeCookieName('PREFAB_' . strtoupper($namespace) . '_SESSION');

        $activeBefore = session_status() === PHP_SESSION_ACTIVE;

        if (!$activeBefore) {
            session_name($name);

            $current = session_get_cookie_params();
            session_set_cookie_params([
                'lifetime' => (int) ($config['lifetime'] ?? $current['lifetime'] ?? 0),
                'path' => $path,
                'domain' => (string) ($config['domain'] ?? $current['domain'] ?? ''),
                'secure' => (bool) ($config['secure'] ?? self::isHttps()),
                'httponly' => (bool) ($config['httponly'] ?? true),
                'samesite' => (string) ($config['samesite'] ?? 'Lax'),
            ]);

            session_start();
        }

        return self::$resolved = [
            'namespace' => $namespace,
            'name' => $activeBefore ? session_name() : $name,
            'path' => $activeBefore ? (session_get_cookie_params()['path'] ?? '/') : $path,
            'active_before' => $activeBefore,
        ];
    }

    public static function key(string $key): string
    {
        $scope = self::start();
        return 'prefab:' . $scope['namespace'] . ':' . ltrim($key, ':');
    }

    /** For tests and long-running workers only. */
    public static function reset(): void
    {
        self::$resolved = null;
    }

    private static function detectIdentity(): string
    {
        $path = self::detectUrlPath();
        if ($path !== '/') {
            return trim($path, '/');
        }

        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $host = preg_replace('/:\d+$/', '', $host) ?: '';
        if ($host !== '') {
            return $host;
        }

        return 'app_' . substr(sha1((string) ($_SERVER['SCRIPT_FILENAME'] ?? getcwd() ?: 'prefab')), 0, 12);
    }

    private static function detectUrlPath(): string
    {
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/'));
        $dir = dirname($script);
        return self::normalizePath($dir === '.' ? '/' : $dir);
    }

    private static function normalizePath(string $path): string
    {
        $path = '/' . trim(str_replace('\\', '/', $path), '/');
        return $path === '/' ? '/' : rtrim($path, '/');
    }

    private static function safeId(string $value): string
    {
        $raw = strtolower(trim($value));
        $safe = trim((string) preg_replace('/[^a-z0-9]+/', '_', $raw), '_');
        if ($safe === '') {
            $safe = 'app_' . substr(sha1($raw ?: 'prefab'), 0, 12);
        }
        if (strlen($safe) > 40) {
            $safe = substr($safe, 0, 27) . '_' . substr(sha1($raw), 0, 12);
        }
        return $safe;
    }

    private static function safeCookieName(string $value): string
    {
        $safe = preg_replace('/[^A-Za-z0-9_]/', '_', trim($value)) ?: 'PREFAB_SESSION';
        return substr($safe, 0, 96);
    }

    private static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || ((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    }
}
