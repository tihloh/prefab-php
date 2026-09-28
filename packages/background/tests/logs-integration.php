<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use PDO;
use Tihloh\Prefab\Background\BackgroundManager;
use Tihloh\Prefab\Logs\Services\LogManager;

$path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'prefab-background-logs-' . bin2hex(random_bytes(6));
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$background = new BackgroundManager([
    'path' => $path,
    'max_workers' => 1,
    'max_pending' => 10,
    'max_bytes' => 1024 * 1024,
    'fsync' => false,
    'retry_base_ms' => 1,
    'retry_max_ms' => 5,
]);

$logs = new LogManager([
    'database' => $pdo,
    'background' => true,
]);

$id = $logs->record([
    'action' => 'document.approved',
    'subject_type' => 'document',
    'subject_id' => 1001,
    'actor_id' => 7,
]);

assert(is_string($id));
assert(count($logs->recent()) === 0);
assert($background->status()['journal']['pending'] === 1);

assert($background->workOnce() === true);

$rows = $logs->recent();
assert(count($rows) === 1);
assert($rows[0]['action'] === 'document.approved');
assert($rows[0]['subject_id'] === '1001');
assert(is_string($rows[0]['occurred_at']) && $rows[0]['occurred_at'] !== '');

$delete = function (string $target) use (&$delete): void {
    if (!is_dir($target)) {
        @unlink($target);
        return;
    }
    foreach (scandir($target) ?: [] as $item) {
        if ($item === '.' || $item === '..') continue;
        $delete($target . DIRECTORY_SEPARATOR . $item);
    }
    @rmdir($target);
};
$delete($path);

echo "Prefab Background + Logs integration passed", PHP_EOL;
