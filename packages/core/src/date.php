<?php

declare(strict_types=1);

use DateTimeZone;
use Tihloh\Prefab\Core\DateTime\Date;

if (!function_exists('prefab_date')) {
    function prefab_date(
        mixed $value = null,
        ?string $format = null,
        DateTimeZone|string|null $timezone = null
    ): Date {
        return Date::make($value, $format, $timezone);
    }
}
