<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use PDO;
use Tihloh\Prefab\Core\Cache\ArrayCache;
use Tihloh\Prefab\Core\Console\Input;
use Tihloh\Prefab\Core\Database\DatabaseManager;

$pdo = new PDO('sqlite::memory:');
$db = new DatabaseManager($pdo);
$db->statement('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, active INTEGER)');
$db->table('users')->insert(['name' => 'Ada', 'active' => 1]);
$rows = $db->table('users')->where('active', 1)->get();
assert(count($rows) === 1);
assert($rows[0]['name'] === 'Ada');

$cache = new ArrayCache();
$calls = 0;
$value = $cache->remember('answer', 60, function () use (&$calls): int {
    $calls++;
    return 42;
});
assert($value === 42);
assert($cache->remember('answer', 60, function () use (&$calls): int {
    $calls++;
    return 99;
}) === 42);
assert($calls === 1);

$input = new Input(['Juan', '--admin', '--email=juan@example.com']);
assert($input->argument(0) === 'Juan');
assert($input->flag('admin') === true);
assert($input->option('email') === 'juan@example.com');

$console = prefab_console();
prefab_command('test:hello', static fn (): int => 0, 'Smoke-test command');
assert(isset($console->commands()['list']));
assert(isset($console->commands()['help']));
assert(isset($console->commands()['about']));
assert(isset($console->commands()['init']));
assert(isset($console->commands()['test:hello']));

echo "Prefab Core smoke OK\n";
