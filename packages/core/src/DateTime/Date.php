<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Core\DateTime;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;
use Stringable;

final class Date implements Stringable
{
    private DateTimeImmutable $value;

    private function __construct(DateTimeImmutable $value)
    {
        $this->value = $value;
    }

    public static function make(
        mixed $value = null,
        ?string $format = null,
        DateTimeZone|string|null $timezone = null
    ): self {
        $tz = self::timezoneObject($timezone);

        if ($value instanceof self) {
            $date = $value->value;
            return new self($tz ? $date->setTimezone($tz) : $date);
        }

        if ($value instanceof DateTimeInterface) {
            $date = DateTimeImmutable::createFromInterface($value);
            return new self($tz ? $date->setTimezone($tz) : $date);
        }

        if (is_int($value) || is_float($value)) {
            $date = (new DateTimeImmutable('@' . (string) (int) $value));
            return new self($tz ? $date->setTimezone($tz) : $date);
        }

        if ($value instanceof Stringable) {
            $value = (string) $value;
        }

        if ($value === null || $value === '') {
            return new self(new DateTimeImmutable('now', $tz));
        }

        if (!is_string($value)) {
            throw new InvalidArgumentException('Date value must be null, a string, timestamp, DateTimeInterface, Stringable, or Prefab Date.');
        }

        if ($format !== null) {
            $date = DateTimeImmutable::createFromFormat($format, $value, $tz);
            $errors = DateTimeImmutable::getLastErrors();

            if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
                throw new InvalidArgumentException("Invalid date '{$value}' for format '{$format}'.");
            }

            return new self($date);
        }

        try {
            return new self(new DateTimeImmutable($value, $tz));
        } catch (\Throwable $e) {
            throw new InvalidArgumentException("Invalid date '{$value}'.", 0, $e);
        }
    }

    public static function parse(
        mixed $value,
        ?string $format = null,
        DateTimeZone|string|null $timezone = null
    ): self {
        return self::make($value, $format, $timezone);
    }

    public function value(): DateTimeImmutable
    {
        return $this->value;
    }

    public function format(string $format = 'Y-m-d H:i:s'): string
    {
        return match (strtolower($format)) {
            'date' => $this->value->format('Y-m-d'),
            'time' => $this->value->format('H:i:s'),
            'datetime' => $this->value->format('Y-m-d H:i:s'),
            'short' => $this->value->format('M j, Y'),
            'long' => $this->value->format('F j, Y g:i A'),
            'iso' => $this->value->format(DateTimeInterface::ATOM),
            default => $this->value->format($format),
        };
    }

    public function date(): string
    {
        return $this->format('date');
    }

    public function time(): string
    {
        return $this->format('time');
    }

    public function datetime(): string
    {
        return $this->format('datetime');
    }

    public function timestamp(): int
    {
        return $this->value->getTimestamp();
    }

    public function timezone(DateTimeZone|string $timezone): self
    {
        return new self($this->value->setTimezone(self::timezoneObject($timezone)));
    }

    public function add(string $modify): self
    {
        return new self($this->value->modify($modify));
    }

    public function sub(string $modify): self
    {
        $modify = ltrim($modify);
        return new self($this->value->modify(str_starts_with($modify, '-') ? $modify : '-' . $modify));
    }

    public function addDays(int $days): self
    {
        return $this->add(($days >= 0 ? '+' : '') . $days . ' days');
    }

    public function subDays(int $days): self
    {
        return $this->addDays(-$days);
    }

    public function addMonths(int $months): self
    {
        return $this->add(($months >= 0 ? '+' : '') . $months . ' months');
    }

    public function subMonths(int $months): self
    {
        return $this->addMonths(-$months);
    }

    public function startOfDay(): self
    {
        return new self($this->value->setTime(0, 0, 0));
    }

    public function endOfDay(): self
    {
        return new self($this->value->setTime(23, 59, 59));
    }

    public function isBefore(mixed $other): bool
    {
        return $this->timestamp() < self::make($other)->timestamp();
    }

    public function isAfter(mixed $other): bool
    {
        return $this->timestamp() > self::make($other)->timestamp();
    }

    public function diffInSeconds(mixed $other = null): int
    {
        return abs($this->timestamp() - self::make($other)->timestamp());
    }

    public function __toString(): string
    {
        return $this->datetime();
    }

    private static function timezoneObject(DateTimeZone|string|null $timezone): ?DateTimeZone
    {
        if ($timezone === null || $timezone === '') {
            return null;
        }

        return $timezone instanceof DateTimeZone ? $timezone : new DateTimeZone($timezone);
    }
}
