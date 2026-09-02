<?php

declare(strict_types=1);

use Tihloh\Prefab\Core\Support\Value;

if (!function_exists('val')) {
    function val(mixed $value = null): Value
    {
        return new Value($value);
    }
}
