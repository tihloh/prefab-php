<?php

declare(strict_types=1);

use Tihloh\Prefab\Core\Environment\Env;

/*
 * Prefab's dotenv support is deliberately conservative:
 * - existing OS/server/framework environment values always win;
 * - .env only fills values that do not already exist;
 * - an existing framework env() helper is never replaced;
 * - prefab_env() is always Prefab's collision-free accessor.
 */
Env::load(override: false);

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
