<?php

declare(strict_types=1);

use Tihloh\Prefab\Core\Console\Console;

if (!function_exists('prefab_console')) {
    function prefab_console(): Console
    {
        static $console;
        return $console ??= new Console();
    }
}

if (!function_exists('prefab_command')) {
    function prefab_command(string $name, callable $handler, string $description = ''): Console
    {
        return prefab_console()->command($name, $handler, $description);
    }
}

if (!function_exists('prefab_cli')) {
    function prefab_cli(array $argv): int
    {
        return prefab_console()->run($argv);
    }
}
