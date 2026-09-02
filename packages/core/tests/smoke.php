<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use PDO;
use Tihloh\Prefab\Core\Cache\ArrayCache;
use Tihloh\Prefab\Core\Console\Input;
use Tihloh\Prefab\Core\Database\DatabaseManager;
use Tihloh\Prefab\Core\Environment\Env;

$pdo = new PDO('sqlite::memory:');
$db = new DatabaseManager($pdo);
$db->statement('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, active INTEGER, age INTEGER)');
$db->table('users')->insert(['name' => 'Ada', 'active' => 1, 'age' => 36]);
$rows = $db->table('users')->where('active', 1)->get();
assert(count($rows) === 1);
assert($rows[0]['name'] === 'Ada');

$db->table('users')->insert([
    'name' => val(' Grace ')->trim(),
    'active' => val('true')->toBool()->fallback(false),
    'age' => val('40')->toInt()->fallback(0),
]);
$grace = $db->table('users')->where('name', val('Grace')->trim())->first();
assert($grace !== null);
assert($grace['name'] === 'Grace');
assert((int) $grace['active'] === 1);
assert((int) $grace['age'] === 40);

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

$envFile = tempnam(sys_get_temp_dir(), 'prefab-env-');
assert($envFile !== false);
file_put_contents($envFile, "PREFAB_SMOKE_NAME=Prefab\nPREFAB_SMOKE_ENABLED=true\nPREFAB_SMOKE_URL=\"https://example.com/api\"\nPREFAB_SMOKE_COPY=\${PREFAB_SMOKE_NAME}\n");
Env::reset();
assert(Env::load($envFile, true) !== null);
assert(env('PREFAB_SMOKE_NAME') === 'Prefab');
assert(prefab_env('PREFAB_SMOKE_ENABLED') === true);
assert(env('PREFAB_SMOKE_URL') === 'https://example.com/api');
assert(env('PREFAB_SMOKE_COPY') === 'Prefab');
unlink($envFile);

assert(val(' Hello ')->trim()->upper()->value() === 'HELLO');
assert(val('42')->toInt()->value() === 42);
assert(val('true')->toBool()->value() === true);
assert(val('not-bool')->toBool()->failed() === true);
assert(val('nope')->toInt()->fallback(0)->value() === 0);
assert(val(null)->default('Guest')->value() === 'Guest');
assert(val(0)->default(99)->value() === 0);
assert(val(false)->default(true)->value() === false);

$fallbackCalls = 0;
assert(val('ready')->fallback(function () use (&$fallbackCalls): string {
    $fallbackCalls++;
    return 'fallback';
})->value() === 'ready');
assert($fallbackCalls === 0);
assert(val(null)->fallback(function () use (&$fallbackCalls): string {
    $fallbackCalls++;
    return 'fallback';
})->value() === 'fallback');
assert($fallbackCalls === 1);

assert(val(['user' => ['name' => 'Ada']])->get('user.name')->value() === 'Ada');
assert(val(['user' => ['name' => null]])->get('user.name', 'Guest')->value() === null);
assert(val(['user' => []])->get('user.name', 'Guest')->value() === 'Guest');
assert(val('test@example.com')->isEmail());
assert(val('192.168.1.10')->isIp());
assert(val('https://example.com')->isUrl());

assert(val('09171234567')->format('phone') === '0917 123 4567');
assert(val('09171234567')->format('phone', ['international' => true]) === '+63 917 123 4567');
assert(val(1234567.5)->format('currency', ['currency' => 'PHP']) === '₱1,234,567.50');
assert(val(0.125)->format('percent', ['decimals' => 1]) === '12.5%');
assert(val('2026-09-02 18:30:00')->format('date') === '2026-09-02');

echo "Prefab Core smoke OK\n";
