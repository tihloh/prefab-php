<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Tihloh\Prefab\Background\BackgroundManager;

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'prefab-background-process-' . bin2hex(random_bytes(6));
$journal = $root . DIRECTORY_SEPARATOR . 'journal';
$output = $root . DIRECTORY_SEPARATOR . 'output.jsonl';
$bootstrap = $root . DIRECTORY_SEPARATOR . 'bootstrap.php';

if (!mkdir($root, 0770, true) && !is_dir($root)) {
    throw new RuntimeException('Unable to create process smoke directory.');
}

$bootstrapCode = <<<'PHP'
<?php

use Tihloh\Prefab\Background\BackgroundContext;
use Tihloh\Prefab\Background\BackgroundManager;

$background = new BackgroundManager([
    'path' => __JOURNAL__,
    'max_workers' => 1,
    'max_pending' => 1,
    'max_bytes' => 1024 * 1024,
    'max_payload_bytes' => 8192,
    'worker_max_tasks' => 2,
    'worker_max_lifetime' => 10,
    'idle_us' => 1000,
    'retry_base_ms' => 1,
    'retry_max_ms' => 5,
    'fsync' => false,
]);

$background->handler('test.process', function (array $payload, BackgroundContext $context): void {
    if (($payload['sleep_us'] ?? 0) > 0) {
        usleep((int) $payload['sleep_us']);
    }

    file_put_contents(
        __OUTPUT__,
        json_encode([
            'value' => $payload['value'] ?? null,
            'occurred_at' => $context->occurredAt,
            'sequence' => $context->sequence,
        ], JSON_THROW_ON_ERROR) . PHP_EOL,
        FILE_APPEND | LOCK_EX,
    );
});
PHP;

$bootstrapCode = str_replace(
    ['__JOURNAL__', '__OUTPUT__'],
    [var_export($journal, true), var_export($output, true)],
    $bootstrapCode,
);
file_put_contents($bootstrap, $bootstrapCode);

$background = new BackgroundManager([
    'path' => $journal,
    'max_workers' => 1,
    'max_pending' => 1,
    'max_bytes' => 1024 * 1024,
    'max_payload_bytes' => 8192,
    'fsync' => false,
]);
$background->handler('test.process', static function (): void {});

$firstOccurred = '2026-09-28T06:31:12.100001Z';
$secondOccurred = '2026-09-28T06:31:12.100002Z';

$background->run('test.process', [
    'value' => 1,
    'sleep_us' => 150000,
], $firstOccurred);

$command = [
    PHP_BINARY,
    realpath(__DIR__ . '/../bin/prefab-background'),
    'work',
    '--bootstrap=' . $bootstrap,
];

$process = proc_open(
    $command,
    [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ],
    $pipes,
    dirname(__DIR__),
);

if (!is_resource($process)) {
    throw new RuntimeException('Unable to start Prefab Background worker process.');
}

fclose($pipes[0]);

$second = $background->run('test.process', [
    'value' => 2,
    'sleep_us' => 0,
], $secondOccurred);

assert($second->occurredAt === $secondOccurred);

$deadline = microtime(true) + 5;
do {
    $status = proc_get_status($process);
    if (!$status['running']) {
        break;
    }
    usleep(10000);
} while (microtime(true) < $deadline);

if (($status['running'] ?? false) === true) {
    proc_terminate($process);
    throw new RuntimeException('Prefab Background process smoke worker did not stop.');
}

$stdout = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$exit = proc_close($process);

if ($exit !== 0 && ($status['exitcode'] ?? 0) !== 0) {
    throw new RuntimeException('Background worker failed: ' . trim((string) $stderr . (string) $stdout));
}

$lines = file($output, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
assert(count($lines) === 2);

$first = json_decode($lines[0], true, flags: JSON_THROW_ON_ERROR);
$secondResult = json_decode($lines[1], true, flags: JSON_THROW_ON_ERROR);

assert($first['value'] === 1);
assert($first['occurred_at'] === $firstOccurred);
assert($secondResult['value'] === 2);
assert($secondResult['occurred_at'] === $secondOccurred);
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
$delete($root);

echo "Prefab Background process smoke test passed", PHP_EOL;
