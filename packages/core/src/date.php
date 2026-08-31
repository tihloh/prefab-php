<?php

declare(strict_types=1);

use Tihloh\Prefab\Core\DateTime\Date;

if (!function_exists('now')) {
    function now(): Date
    {
        return Date::make();
    }
}

if (!function_exists('datetime')) {
    function datetime(mixed $value = null): Date
    {
        return Date::make($value);
    }
}

if (!function_exists('prefab_datetime')) {
    function prefab_datetime(mixed $value = null): Date
    {
        return Date::make($value);
    }
}
