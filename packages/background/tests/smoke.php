<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Tihloh\Prefab\Background\BackgroundContext;
use Tihloh\Prefab\Background\BackgroundManager;

$path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'prefab-background-' . bin2hex(random_bytes(6));
$output = [];

$background = new BackgroundManager([
    'path' => $path,
    'max_workers' => 1,
    'max_pending' => 10,
    'max_bytes' => 1024 * 1024,
    'max_payload_bytes' => 8192,
    'worker_max_tasks' => 10,
    'worker_max_lifetime' => 30,
    'retry_base_ms' => 1,
    'retry_max_ms' => 5,
    'fsync' => false,
]);

$background->handler('test.write', function (array $payload, BackgroundContext $context) use (&$output): void {
    $output[] = [
        'payload' => $payload,
        'occurred_at' => $context->occurredAt,
        'request_id' => $context->requestId,
        'sequence' => $context->sequence,
        'attempt' => $context->attempt,
    ];
});

$occurred = '2026-09-28T06:31:12.123456Z';
$receipt = $background->run('test.write', ['value' => 42], $occurred);

assert($receipt->occurredAt === $occurred);
assert($receipt->sequence === 1);
assert($background->status()['journal']['pending'] === 1);
assert($background->workOnce() === true);
assert(count($output) === 1);
assert($output[0]['payload']['value'] === 42);
assert($output[0]['occurred_at'] === $occurred);
assert($output[0]['sequence'] === 1);
assert($output[0]['attempt'] === 1);
assert($background->status()['journal']['pending'] === 0);

$attempts = 0;
$background->handler('test.retry', function () use (&$attempts): void {
    $attempts++;
    if ($attempts === 1) {
        throw new RuntimeException('retry me');
    }
});

$background->run('test.retry');
assert($background->workOnce() === true);
assert($background->status()['journal']['pending'] === 1);
usleep(10000);
assert($background->workOnce() === true);
assert($attempts === 2);
assert($background->status()['journal']['pending'] === 0);

$delete = function (string $path) use (&$delete): void {
    if (!is_dir($path)) {
        @unlink($path);
        return;
    }
    foreach (scandir($path) ?: [] as $item) {
        if ($item === '.' || $item === '..') continue;
        $delete($path . DIRECTORY_SEPARATOR . $item);
    }
    @rmdir($path);
};
$delete($path);

echo "Prefab Background smoke test passed", PHP_EOL;
