<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Core\Environment;

final class Env
{
    private static bool $loaded = false;
    private static ?string $file = null;
    private static array $values = [];

    public static function load(?string $path = null, bool $override = false): ?string
    {
        $file = self::resolveFile($path);
        if ($file === null) {
            self::$loaded = true;
            return null;
        }

        if (self::$loaded && self::$file === $file && !$override) {
            return self::$file;
        }

        $lines = @file($file, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            self::$loaded = true;
            return null;
        }

        foreach ($lines as $line) {
            self::parseLine($line, $override);
        }

        self::$loaded = true;
        self::$file = $file;

        return $file;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (!self::$loaded) {
            self::load();
        }

        $value = getenv($key);
        if ($value === false) {
            $value = $_ENV[$key] ?? self::$values[$key] ?? null;
        }

        return $value === null ? $default : self::normalize($value);
    }

    public static function has(string $key): bool
    {
        if (!self::$loaded) {
            self::load();
        }

        return getenv($key) !== false || array_key_exists($key, $_ENV) || array_key_exists($key, self::$values);
    }

    public static function file(): ?string
    {
        return self::$file;
    }

    public static function all(): array
    {
        return self::$values;
    }

    public static function reset(): void
    {
        self::$loaded = false;
        self::$file = null;
        self::$values = [];
    }

    private static function parseLine(string $line, bool $override): void
    {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            return;
        }

        if (str_starts_with($line, 'export ')) {
            $line = ltrim(substr($line, 7));
        }

        $separator = strpos($line, '=');
        if ($separator === false) {
            return;
        }

        $key = trim(substr($line, 0, $separator));
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_.-]*$/', $key)) {
            return;
        }

        if (!$override && (getenv($key) !== false || array_key_exists($key, $_ENV))) {
            self::$values[$key] = getenv($key) !== false ? (string) getenv($key) : (string) $_ENV[$key];
            return;
        }

        $value = self::parseValue(trim(substr($line, $separator + 1)));
        $value = self::expand($value);

        self::$values[$key] = $value;
        $_ENV[$key] = $value;
        putenv($key . '=' . $value);
    }

    private static function parseValue(string $value): string
    {
        $length = strlen($value);
        if ($length >= 2 && $value[0] === '"' && $value[$length - 1] === '"') {
            $value = substr($value, 1, -1);
            return strtr($value, [
                '\\n' => "\n",
                '\\r' => "\r",
                '\\t' => "\t",
                '\\"' => '"',
                '\\\\' => '\\',
            ]);
        }

        if ($length >= 2 && $value[0] === "'" && $value[$length - 1] === "'") {
            return substr($value, 1, -1);
        }

        $value = preg_replace('/\s+#.*$/', '', $value) ?? $value;
        return trim($value);
    }

    private static function expand(string $value): string
    {
        return preg_replace_callback('/\$\{([A-Za-z_][A-Za-z0-9_.-]*)\}/', static function (array $match): string {
            $existing = getenv($match[1]);
            if ($existing !== false) {
                return (string) $existing;
            }
            return (string) ($_ENV[$match[1]] ?? self::$values[$match[1]] ?? '');
        }, $value) ?? $value;
    }

    private static function normalize(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        return match (strtolower($value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            'empty', '(empty)' => '',
            default => $value,
        };
    }

    private static function resolveFile(?string $path): ?string
    {
        if ($path !== null && $path !== '') {
            $candidate = is_dir($path) ? rtrim($path, '/\\') . DIRECTORY_SEPARATOR . '.env' : $path;
            $real = realpath($candidate);
            return $real !== false && is_file($real) ? $real : null;
        }

        $configured = getenv('PREFAB_ENV_FILE');
        if ($configured !== false && $configured !== '') {
            $real = realpath($configured);
            if ($real !== false && is_file($real)) {
                return $real;
            }
        }

        $starts = [];
        $cwd = getcwd();
        if ($cwd !== false) {
            $starts[] = $cwd;
        }
        if (!empty($_SERVER['DOCUMENT_ROOT'])) {
            $starts[] = (string) $_SERVER['DOCUMENT_ROOT'];
        }
        $starts[] = dirname(__DIR__, 5);

        foreach (array_unique($starts) as $start) {
            $found = self::searchUp($start);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    private static function searchUp(string $start): ?string
    {
        $directory = realpath($start);
        if ($directory === false || !is_dir($directory)) {
            return null;
        }

        for ($depth = 0; $depth < 10; $depth++) {
            $candidate = $directory . DIRECTORY_SEPARATOR . '.env';
            if (is_file($candidate)) {
                return realpath($candidate) ?: $candidate;
            }

            $parent = dirname($directory);
            if ($parent === $directory) {
                break;
            }
            $directory = $parent;
        }

        return null;
    }
}
