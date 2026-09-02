<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Core\Support;

use DateTimeInterface;
use InvalidArgumentException;
use Throwable;
use Tihloh\Prefab\Core\DateTime\Date;

final class Value
{
    private mixed $value;
    private ?Throwable $error = null;

    public function __construct(mixed $value = null)
    {
        $this->value = $value;
    }

    public function value(): mixed { return $this->value; }
    public function get(): mixed { return $this->value; }
    public function type(): string { return get_debug_type($this->value); }
    public function error(): ?Throwable { return $this->error; }
    public function ok(): bool { return $this->error === null; }
    public function failed(): bool { return $this->error !== null; }

    public function isNull(): bool { return $this->value === null; }
    public function isString(): bool { return is_string($this->value); }
    public function isNumber(): bool { return is_int($this->value) || is_float($this->value) || (is_string($this->value) && is_numeric($this->value)); }
    public function isBool(): bool { return is_bool($this->value); }
    public function isArray(): bool { return is_array($this->value); }
    public function isObject(): bool { return is_object($this->value); }
    public function isEmail(): bool { return is_string($this->value) && filter_var($this->value, FILTER_VALIDATE_EMAIL) !== false; }
    public function isIp(): bool { return is_string($this->value) && filter_var(trim($this->value), FILTER_VALIDATE_IP) !== false; }
    public function isUrl(): bool { return is_string($this->value) && filter_var($this->value, FILTER_VALIDATE_URL) !== false; }
    public function isDate(): bool
    {
        if ($this->value instanceof DateTimeInterface || $this->value instanceof Date) { return true; }
        if (!is_string($this->value) || trim($this->value) === '') { return false; }
        return strtotime($this->value) !== false && preg_match('/[-:\/]|[A-Za-z]{3,}/', $this->value) === 1;
    }

    public function isEmpty(): bool
    {
        return $this->value === null || $this->value === '' || $this->value === [];
    }

    /** Use a value only when the current value is null, an empty string, or an empty array. */
    public function default(mixed $default): self
    {
        if ($this->isEmpty()) { $this->value = self::resolve($default); }
        return $this;
    }

    /** Recover from a failed operation or unavailable value. Callable fallbacks are lazy. */
    public function fallback(mixed $fallback): self
    {
        if ($this->failed() || $this->isEmpty()) {
            $this->value = self::resolve($fallback);
            $this->error = null;
        }
        return $this;
    }

    public function orNull(): mixed { return $this->failed() ? null : $this->value; }

    public function orFail(): mixed
    {
        if ($this->error !== null) { throw $this->error; }
        return $this->value;
    }

    /** Run a potentially failing transformation without writing try/catch at the call site. */
    public function attempt(callable $callback): self
    {
        if ($this->failed()) { return $this; }
        try { $this->value = $callback($this->value); }
        catch (Throwable $e) { $this->error = $e; }
        return $this;
    }

    public function getPath(string $path, mixed $default = null): self
    {
        $current = $this->value;
        foreach (explode('.', $path) as $part) {
            if (is_array($current) && array_key_exists($part, $current)) { $current = $current[$part]; continue; }
            if (is_object($current) && (isset($current->{$part}) || property_exists($current, $part))) { $current = $current->{$part}; continue; }
            $current = self::resolve($default);
            break;
        }
        return new self($current);
    }

    public function trim(): self { return $this->mapString(static fn (string $v): string => trim($v)); }
    public function lower(): self { return $this->mapString(static fn (string $v): string => strtolower($v)); }
    public function upper(): self { return $this->mapString(static fn (string $v): string => strtoupper($v)); }

    public function toString(): self { return $this->convert(static fn ($v): string => (string) $v); }

    public function toInt(): self
    {
        return $this->convert(static function ($v): int {
            if (!is_numeric($v)) { throw new InvalidArgumentException('Value is not numeric.'); }
            return (int) $v;
        });
    }

    public function toFloat(): self
    {
        return $this->convert(static function ($v): float {
            if (!is_numeric($v)) { throw new InvalidArgumentException('Value is not numeric.'); }
            return (float) $v;
        });
    }

    public function toBool(): self
    {
        return $this->convert(static function ($v): bool {
            if (is_bool($v)) { return $v; }
            $result = filter_var($v, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
            if ($result === null) { throw new InvalidArgumentException('Value cannot be converted to boolean.'); }
            return $result;
        });
    }

    public function toDateTime(): self { return $this->convert(static fn ($v): Date => Date::make($v)); }

    public function format(string|int|null $format = null, array $options = []): string
    {
        if ($this->error !== null) { return (string) ($options['default'] ?? ''); }
        $name = is_string($format) ? strtolower($format) : $format;

        if ($this->value instanceof Date || $this->value instanceof DateTimeInterface || ($this->isDate() && is_string($format))) {
            return Date::make($this->value)->format($format === null ? 'datetime' : (string) $format);
        }

        if ($name === 'phone') {
            $digits = preg_replace('/\D+/', '', (string) $this->value) ?? '';
            $international = (bool) ($options['international'] ?? false);
            if (strlen($digits) === 11 && str_starts_with($digits, '09')) {
                return $international
                    ? '+63 ' . substr($digits, 1, 3) . ' ' . substr($digits, 4, 3) . ' ' . substr($digits, 7)
                    : substr($digits, 0, 4) . ' ' . substr($digits, 4, 3) . ' ' . substr($digits, 7);
            }
            if (strlen($digits) === 12 && str_starts_with($digits, '63')) {
                return '+63 ' . substr($digits, 2, 3) . ' ' . substr($digits, 5, 3) . ' ' . substr($digits, 8);
            }
            return (string) $this->value;
        }

        if ($name === 'ip') {
            $ip = trim((string) $this->value);
            return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : (string) $this->value;
        }

        if ($name === 'address') {
            return preg_replace('/\s+/', ' ', trim((string) $this->value)) ?? (string) $this->value;
        }

        if ($name === 'currency') {
            if (!$this->isNumber()) { return (string) ($options['default'] ?? $this->value); }
            $decimals = (int) ($options['decimals'] ?? 2);
            $currency = strtoupper((string) ($options['currency'] ?? ''));
            $symbols = ['PHP' => '₱', 'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'JPY' => '¥'];
            $symbol = array_key_exists('symbol', $options) ? (string) $options['symbol'] : ($symbols[$currency] ?? '');
            return $symbol . number_format((float) $this->value, $decimals);
        }

        if ($name === 'percent') {
            if (!$this->isNumber()) { return (string) ($options['default'] ?? $this->value); }
            $decimals = (int) ($options['decimals'] ?? 0);
            $multiplier = (bool) ($options['fraction'] ?? true) ? 100 : 1;
            return number_format((float) $this->value * $multiplier, $decimals) . '%';
        }

        if (is_int($format)) {
            return $this->isNumber() ? number_format((float) $this->value, $format) : (string) $this->value;
        }
        if ($format === null && is_float($this->value)) { return rtrim(rtrim(number_format($this->value, 10, '.', ','), '0'), '.'); }
        if ($format === null && is_int($this->value)) { return number_format($this->value); }
        return (string) $this->value;
    }

    public function __toString(): string { return (string) $this->value; }

    private function mapString(callable $callback): self
    {
        if (!is_string($this->value)) { return $this->fail(new InvalidArgumentException('Value is not a string.')); }
        $this->value = $callback($this->value);
        return $this;
    }

    private function convert(callable $callback): self
    {
        if ($this->failed()) { return $this; }
        try { $this->value = $callback($this->value); }
        catch (Throwable $e) { $this->error = $e; }
        return $this;
    }

    private function fail(Throwable $error): self { $this->error = $error; return $this; }
    private static function resolve(mixed $value): mixed { return is_callable($value) ? $value() : $value; }
}
