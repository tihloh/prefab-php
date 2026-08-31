<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Core\DateTime;

use DateInterval;
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

    public static function make(mixed $value = null): self
    {
        if ($value instanceof self) {
            return new self($value->value);
        }

        if ($value instanceof DateTimeInterface) {
            return new self(DateTimeImmutable::createFromInterface($value));
        }

        if (is_int($value) || is_float($value)) {
            return new self(new DateTimeImmutable('@' . (string) (int) $value));
        }

        if ($value instanceof Stringable) {
            $value = (string) $value;
        }

        if ($value === null || $value === '') {
            return new self(new DateTimeImmutable('now'));
        }

        if (!is_string($value)) {
            throw new InvalidArgumentException('Date/time value must be null, a string, timestamp, DateTimeInterface, Stringable, or Prefab Date.');
        }

        try {
            return new self(new DateTimeImmutable($value));
        } catch (\Throwable $e) {
            throw new InvalidArgumentException("Invalid date/time '{$value}'.", 0, $e);
        }
    }

    public static function parse(string $value, string $format, DateTimeZone|string|null $timezone = null): self
    {
        $date = DateTimeImmutable::createFromFormat($format, $value, self::timezoneObject($timezone));
        $errors = DateTimeImmutable::getLastErrors();

        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new InvalidArgumentException("Invalid date/time '{$value}' for format '{$format}'.");
        }

        return new self($date);
    }

    public function value(): DateTimeImmutable { return $this->value; }
    public function native(): DateTimeImmutable { return $this->value; }

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

    public function date(): string { return $this->format('date'); }
    public function time(): string { return $this->format('time'); }
    public function datetime(): string { return $this->format('datetime'); }
    public function short(): string { return $this->format('short'); }
    public function long(): string { return $this->format('long'); }
    public function iso(): string { return $this->format('iso'); }
    public function timestamp(): int { return $this->value->getTimestamp(); }
    public function year(): int { return (int) $this->value->format('Y'); }
    public function month(): int { return (int) $this->value->format('n'); }
    public function day(): int { return (int) $this->value->format('j'); }
    public function hour(): int { return (int) $this->value->format('G'); }
    public function minute(): int { return (int) $this->value->format('i'); }
    public function second(): int { return (int) $this->value->format('s'); }

    public function timezone(DateTimeZone|string $timezone): self
    {
        return new self($this->value->setTimezone(self::timezoneObject($timezone)));
    }

    public function add(string|DateInterval $interval): self
    {
        if ($interval instanceof DateInterval) {
            return new self($this->value->add($interval));
        }

        $date = $this->value->modify($interval);
        if ($date === false) {
            throw new InvalidArgumentException("Invalid date/time interval '{$interval}'.");
        }
        return new self($date);
    }

    public function sub(string|DateInterval $interval): self
    {
        if ($interval instanceof DateInterval) {
            return new self($this->value->sub($interval));
        }

        $interval = ltrim($interval);
        return $this->add(str_starts_with($interval, '-') ? $interval : '-' . $interval);
    }

    public function addSeconds(int $value): self { return $this->add(($value >= 0 ? '+' : '') . $value . ' seconds'); }
    public function addMinutes(int $value): self { return $this->add(($value >= 0 ? '+' : '') . $value . ' minutes'); }
    public function addHours(int $value): self { return $this->add(($value >= 0 ? '+' : '') . $value . ' hours'); }
    public function addDays(int $value): self { return $this->add(($value >= 0 ? '+' : '') . $value . ' days'); }
    public function addWeeks(int $value): self { return $this->add(($value >= 0 ? '+' : '') . $value . ' weeks'); }
    public function addMonths(int $value): self { return $this->add(($value >= 0 ? '+' : '') . $value . ' months'); }
    public function addYears(int $value): self { return $this->add(($value >= 0 ? '+' : '') . $value . ' years'); }
    public function subSeconds(int $value): self { return $this->addSeconds(-$value); }
    public function subMinutes(int $value): self { return $this->addMinutes(-$value); }
    public function subHours(int $value): self { return $this->addHours(-$value); }
    public function subDays(int $value): self { return $this->addDays(-$value); }
    public function subWeeks(int $value): self { return $this->addWeeks(-$value); }
    public function subMonths(int $value): self { return $this->addMonths(-$value); }
    public function subYears(int $value): self { return $this->addYears(-$value); }

    public function startOfDay(): self { return new self($this->value->setTime(0, 0, 0)); }
    public function endOfDay(): self { return new self($this->value->setTime(23, 59, 59)); }

    public function isBefore(mixed $other): bool { return $this->timestamp() < self::make($other)->timestamp(); }
    public function isAfter(mixed $other): bool { return $this->timestamp() > self::make($other)->timestamp(); }
    public function isPast(): bool { return $this->isBefore(); }
    public function isFuture(): bool { return $this->isAfter(); }
    public function isSameDay(mixed $other): bool { return $this->date() === self::make($other)->date(); }

    public function between(mixed $start, mixed $end, bool $inclusive = true): bool
    {
        $time = $this->timestamp();
        $from = self::make($start)->timestamp();
        $to = self::make($end)->timestamp();
        if ($from > $to) { [$from, $to] = [$to, $from]; }
        return $inclusive ? $time >= $from && $time <= $to : $time > $from && $time < $to;
    }

    public function diffInSeconds(mixed $other = null): int { return abs($this->timestamp() - self::make($other)->timestamp()); }
    public function diffInMinutes(mixed $other = null): float { return $this->diffInSeconds($other) / 60; }
    public function diffInHours(mixed $other = null): float { return $this->diffInSeconds($other) / 3600; }
    public function diffInDays(mixed $other = null): float { return $this->diffInSeconds($other) / 86400; }

    public function __toString(): string { return $this->datetime(); }

    private static function timezoneObject(DateTimeZone|string|null $timezone): ?DateTimeZone
    {
        if ($timezone === null || $timezone === '') { return null; }
        return $timezone instanceof DateTimeZone ? $timezone : new DateTimeZone($timezone);
    }
}
