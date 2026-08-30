<?php

declare(strict_types=1);

use Tihloh\Prefab\Core\Environment\Env;

Env::load();

if (!function_exists('prefab_env')) {
    function prefab_env(string $key, mixed $default = null): mixed
    {
        return Env::get($key, $default);
    }
}

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        return Env::get($key, $default);
    }
}
