<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Background;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use Tihloh\Prefab\PrefabConfig;
use Tihloh\Prefab\PrefabRuntime;

final class BackgroundManager
{
    private const DEFAULTS = [
        'path' => null,
        'max_workers' => 2,
        'max_pending' => 1000,
        'max_bytes' => 33554432,
        'max_payload_bytes' => 65536,
        'wait_us' => 10000,
        'idle_us' => 25000,
        'retry_base_ms' => 250,
        'retry_max_ms' => 30000,
        'worker_max_tasks' => 500,
        'worker_max_lifetime' => 1800,
        'task_timeout' => 30,
        'fsync' => true,
    ];

    private array $handlers = [];
    private array $resolved = [];
    private string $requestId;
    private int $sequence = 0;
    private bool $stopRequested = false;

    public function __construct(private array $config = [])
    {
        $this->requestId = bin2hex(random_bytes(12));
        PrefabRuntime::register('background', $this);
    }

    public function prefabConfigure(): void
    {
        $defaults = self::DEFAULTS;
        $defaults['path'] = (getcwd() ?: '.') . DIRECTORY_SEPARATOR . 'storage'
            . DIRECTORY_SEPARATOR . 'prefab' . DIRECTORY_SEPARATOR . 'background';

        foreach ($defaults as $key => $default) {
            $entry = PrefabConfig::resolve('background', $key, $this->config, $default);
            $this->resolved[$key] = $entry['value'];
            PrefabRuntime::recordResolution(
                'background',
                $key,
                $entry['source'],
                ['value' => is_scalar($entry['value']) || $entry['value'] === null ? $entry['value'] : get_debug_type($entry['value'])],
            );
        }

        $this->assertConfiguration();

        PrefabRuntime::provide(
            'background',
            $this,
            'prefab-background',
        );
    }

    public function handler(string $name, callable $handler): self
    {
        $name = $this->handlerName($name);
        $this->handlers[$name] = $handler;
        return $this;
    }

    public function hasHandler(string $name): bool
    {
        return isset($this->handlers[$this->handlerName($name)]);
    }

    public function handlers(): array
    {
        return array_keys($this->handlers);
    }

    public function run(string $handler, array $payload = [], ?string $occurredAt = null): BackgroundReceipt
    {
        $handler = $this->handlerName($handler);
        if (!isset($this->handlers[$handler])) {
            throw new InvalidArgumentException("Prefab Background handler is not registered: {$handler}");
        }

        $this->assertJsonSafe($payload, 'payload');
        $this->ensureDirectories();

        $occurredAt = $this->normalizeTime($occurredAt);
        $id = bin2hex(random_bytes(16));
        $sequence = ++$this->sequence;

        while (true) {
            $capacity = $this->openLock('capacity.lock');
            if (!flock($capacity, LOCK_EX)) {
                fclose($capacity);
                throw new RuntimeException('Prefab Background could not lock journal capacity.');
            }

            try {
                $acceptedAt = $this->now();
                $instruction = [
                    'id' => $id,
                    'handler' => $handler,
                    'payload' => $payload,
                    'occurred_at' => $occurredAt,
                    'accepted_at' => $acceptedAt,
                    'request_id' => $this->requestId,
                    'sequence' => $sequence,
                    'attempts' => 0,
                    'available_at_us' => $this->nowUs(),
                    'state' => 'pending',
                    'last_error' => null,
                ];

                $json = $this->encode($instruction);
                $size = strlen($json);
                if ($size > $this->intConfig('max_payload_bytes')) {
                    throw new InvalidArgumentException(
                        "Prefab Background payload exceeds max_payload_bytes ({$size} bytes)."
                    );
                }

                $stats = $this->journalStats();
                $hasCapacity = $stats['pending'] < $this->intConfig('max_pending')
                    && ($stats['bytes'] + $size) <= $this->intConfig('max_bytes');

                if ($hasCapacity) {
                    $this->writeInstruction($instruction, $json);
                    return new BackgroundReceipt(
                        id: $id,
                        occurredAt: $occurredAt,
                        acceptedAt: $acceptedAt,
                        requestId: $this->requestId,
                        sequence: $sequence,
                    );
                }
            } finally {
                flock($capacity, LOCK_UN);
                fclose($capacity);
            }

            usleep($this->intConfig('wait_us'));
        }
    }

    public function work(?int $maxTasks = null, ?int $maxLifetime = null): int
    {
        $this->ensureDirectories();
        [$slotHandle] = $this->acquireWorkerSlot();

        $maxTasks ??= $this->intConfig('worker_max_tasks');
        $maxLifetime ??= $this->intConfig('worker_max_lifetime');
        $started = time();
        $processed = 0;

        $this->installSignalHandlers();

        try {
            while (!$this->stopRequested) {
                if ($maxTasks > 0 && $processed >= $maxTasks) {
                    break;
                }

                if ($maxLifetime > 0 && (time() - $started) >= $maxLifetime) {
                    break;
                }

                if ($this->processOne()) {
                    $processed++;
                    continue;
                }

                usleep($this->intConfig('idle_us'));
            }
        } finally {
            flock($slotHandle, LOCK_UN);
            fclose($slotHandle);
        }

        return $processed;
    }

    public function workOnce(): bool
    {
        $this->ensureDirectories();
        [$slotHandle] = $this->acquireWorkerSlot();

        try {
            return $this->processOne();
        } finally {
            flock($slotHandle, LOCK_UN);
            fclose($slotHandle);
        }
    }

    public function status(): array
    {
        $this->ensureDirectories();
        $stats = $this->journalStats();

        return [
            'path' => $this->path(),
            'workers' => [
                'busy' => $this->busyWorkers(),
                'max' => $this->intConfig('max_workers'),
            ],
            'journal' => [
                'pending' => $stats['pending'],
                'bytes' => $stats['bytes'],
                'max_pending' => $this->intConfig('max_pending'),
                'max_bytes' => $this->intConfig('max_bytes'),
            ],
            'handlers' => $this->handlers(),
        ];
    }

    public function explain(): array
    {
        return PrefabRuntime::explain('background');
    }

    public function path(): string
    {
        $this->configured();
        return rtrim((string) $this->resolved['path'], '/\\');
    }

    private function processOne(): bool
    {
        $files = glob($this->pendingPath() . DIRECTORY_SEPARATOR . '*.json') ?: [];
        sort($files, SORT_STRING);

        foreach ($files as $file) {
            $handle = @fopen($file, 'c+');
            if (!is_resource($handle)) {
                continue;
            }

            if (!flock($handle, LOCK_EX | LOCK_NB)) {
                fclose($handle);
                continue;
            }

            $instruction = $this->readLocked($handle);
            if (($instruction['state'] ?? 'pending') === 'done') {
                flock($handle, LOCK_UN);
                fclose($handle);
                @unlink($file);
                continue;
            }

            if ((int) ($instruction['available_at_us'] ?? 0) > $this->nowUs()) {
                flock($handle, LOCK_UN);
                fclose($handle);
                continue;
            }

            $handlerName = (string) ($instruction['handler'] ?? '');
            $handler = $this->handlers[$handlerName] ?? null;
            $attempt = ((int) ($instruction['attempts'] ?? 0)) + 1;
            $instruction['attempts'] = $attempt;
            $instruction['last_started_at'] = $this->now();
            $instruction['available_at_us'] = $this->nowUs() + ($this->retryDelayMs($attempt) * 1000);
            $instruction['last_error'] = null;
            $this->writeLocked($handle, $instruction);

            if (!is_callable($handler)) {
                $instruction['last_error'] = "Handler is not registered: {$handlerName}";
                $instruction['last_failed_at'] = $this->now();
                $this->writeLocked($handle, $instruction);
                flock($handle, LOCK_UN);
                fclose($handle);
                return true;
            }

            $context = new BackgroundContext(
                id: (string) $instruction['id'],
                handler: $handlerName,
                occurredAt: (string) $instruction['occurred_at'],
                acceptedAt: (string) $instruction['accepted_at'],
                requestId: (string) $instruction['request_id'],
                sequence: (int) $instruction['sequence'],
                attempt: $attempt,
            );

            try {
                $timeout = $this->intConfig('task_timeout');
                if ($timeout > 0 && function_exists('set_time_limit')) {
                    @set_time_limit($timeout);
                }

                $handler((array) ($instruction['payload'] ?? []), $context);

                $instruction['state'] = 'done';
                $instruction['processed_at'] = $this->now();
                $instruction['last_error'] = null;
                $this->writeLocked($handle, $instruction);

                if (function_exists('set_time_limit')) {
                    @set_time_limit(0);
                }

                flock($handle, LOCK_UN);
                fclose($handle);
                @unlink($file);
                return true;
            } catch (Throwable $error) {
                if (function_exists('set_time_limit')) {
                    @set_time_limit(0);
                }

                $instruction['state'] = 'pending';
                $instruction['last_error'] = $error::class . ': ' . $error->getMessage();
                $instruction['last_failed_at'] = $this->now();
                $this->writeLocked($handle, $instruction);
                flock($handle, LOCK_UN);
                fclose($handle);
                return true;
            }
        }

        return false;
    }

    private function writeInstruction(array $instruction, string $json): void
    {
        $prefix = preg_replace('/[^0-9]/', '', (string) $instruction['occurred_at']) ?: (string) $this->nowUs();
        $prefix = substr($prefix, 0, 20);
        $final = $this->pendingPath() . DIRECTORY_SEPARATOR . $prefix . '-' . $instruction['id'] . '.json';
        $temp = $final . '.tmp-' . bin2hex(random_bytes(4));

        $handle = @fopen($temp, 'xb');
        if (!is_resource($handle)) {
            throw new RuntimeException('Prefab Background could not create a journal instruction.');
        }

        try {
            $this->writeBytes($handle, $json);
        } finally {
            fclose($handle);
        }

        if (!@rename($temp, $final)) {
            @unlink($temp);
            throw new RuntimeException('Prefab Background could not commit a journal instruction.');
        }
    }

    private function readLocked($handle): array
    {
        rewind($handle);
        $json = stream_get_contents($handle);
        if (!is_string($json) || trim($json) === '') {
            throw new RuntimeException('Prefab Background encountered an empty journal instruction.');
        }

        $instruction = json_decode($json, true);
        if (!is_array($instruction)) {
            throw new RuntimeException('Prefab Background encountered an invalid journal instruction.');
        }

        return $instruction;
    }

    private function writeLocked($handle, array $instruction): void
    {
        rewind($handle);
        if (!ftruncate($handle, 0)) {
            throw new RuntimeException('Prefab Background could not update a journal instruction.');
        }
        $this->writeBytes($handle, $this->encode($instruction));
    }

    private function writeBytes($handle, string $data): void
    {
        $length = strlen($data);
        $offset = 0;

        while ($offset < $length) {
            $written = fwrite($handle, substr($data, $offset));
            if ($written === false || $written === 0) {
                throw new RuntimeException('Prefab Background could not write journal data.');
            }
            $offset += $written;
        }

        if (!fflush($handle)) {
            throw new RuntimeException('Prefab Background could not flush journal data.');
        }

        if ($this->boolConfig('fsync') && function_exists('fsync')) {
            @fsync($handle);
        }
    }

    private function journalStats(): array
    {
        $pending = 0;
        $bytes = 0;

        foreach (glob($this->pendingPath() . DIRECTORY_SEPARATOR . '*.json') ?: [] as $file) {
            $pending++;
            $size = @filesize($file);
            if (is_int($size)) {
                $bytes += $size;
            }
        }

        return ['pending' => $pending, 'bytes' => $bytes];
    }

    private function busyWorkers(): int
    {
        $busy = 0;

        for ($slot = 1; $slot <= $this->intConfig('max_workers'); $slot++) {
            $handle = $this->openLock("worker-{$slot}.lock");
            if (!flock($handle, LOCK_EX | LOCK_NB)) {
                $busy++;
                fclose($handle);
                continue;
            }

            flock($handle, LOCK_UN);
            fclose($handle);
        }

        return $busy;
    }

    private function acquireWorkerSlot(): array
    {
        for ($slot = 1; $slot <= $this->intConfig('max_workers'); $slot++) {
            $handle = $this->openLock("worker-{$slot}.lock");
            if (flock($handle, LOCK_EX | LOCK_NB)) {
                return [$handle, $slot];
            }
            fclose($handle);
        }

        throw new RuntimeException(
            'Prefab Background maximum worker count is already active.'
        );
    }

    private function openLock(string $name)
    {
        $path = $this->locksPath() . DIRECTORY_SEPARATOR . $name;
        $handle = @fopen($path, 'c+');
        if (!is_resource($handle)) {
            throw new RuntimeException("Prefab Background could not open lock: {$path}");
        }
        return $handle;
    }

    private function ensureDirectories(): void
    {
        foreach ([$this->path(), $this->pendingPath(), $this->locksPath()] as $path) {
            if (!is_dir($path) && !@mkdir($path, 0770, true) && !is_dir($path)) {
                throw new RuntimeException("Prefab Background could not create directory: {$path}");
            }
        }
    }

    private function pendingPath(): string
    {
        return $this->path() . DIRECTORY_SEPARATOR . 'pending';
    }

    private function locksPath(): string
    {
        return $this->path() . DIRECTORY_SEPARATOR . 'locks';
    }

    private function retryDelayMs(int $attempt): int
    {
        $base = $this->intConfig('retry_base_ms');
        $max = $this->intConfig('retry_max_ms');
        $power = min(max(0, $attempt - 1), 16);
        return min($max, $base * (2 ** $power));
    }

    private function handlerName(string $name): string
    {
        $name = strtolower(trim($name));
        if ($name === '' || preg_match('/^[a-z][a-z0-9._-]*$/', $name) !== 1) {
            throw new InvalidArgumentException(
                'Prefab Background handler names may contain lowercase letters, numbers, dots, underscores and hyphens.'
            );
        }
        return $name;
    }

    private function normalizeTime(?string $value): string
    {
        if ($value === null || trim($value) === '') {
            return $this->now();
        }

        try {
            return (new DateTimeImmutable($value))
                ->setTimezone(new DateTimeZone('UTC'))
                ->format('Y-m-d\\TH:i:s.u\\Z');
        } catch (Throwable $error) {
            throw new InvalidArgumentException('Prefab Background occurred_at is invalid.', previous: $error);
        }
    }

    private function now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->format('Y-m-d\\TH:i:s.u\\Z');
    }

    private function nowUs(): int
    {
        return (int) round(microtime(true) * 1000000);
    }

    private function encode(array $value): string
    {
        return json_encode(
            $value,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION,
        );
    }

    private function assertJsonSafe(mixed $value, string $path): void
    {
        if ($value === null || is_scalar($value)) {
            return;
        }

        if (!is_array($value)) {
            throw new InvalidArgumentException(
                "Prefab Background payload must contain only JSON-safe scalar/array data: {$path}"
            );
        }

        foreach ($value as $key => $item) {
            $this->assertJsonSafe($item, $path . '.' . (string) $key);
        }
    }

    private function assertConfiguration(): void
    {
        foreach (['max_workers', 'max_pending', 'max_bytes', 'max_payload_bytes', 'wait_us', 'idle_us'] as $key) {
            if ((int) $this->resolved[$key] < 1) {
                throw new InvalidArgumentException("Prefab Background {$key} must be at least 1.");
            }
        }

        foreach (['retry_base_ms', 'retry_max_ms', 'worker_max_tasks', 'worker_max_lifetime', 'task_timeout'] as $key) {
            if ((int) $this->resolved[$key] < 0) {
                throw new InvalidArgumentException("Prefab Background {$key} cannot be negative.");
            }
        }

        if ($this->intConfig('max_payload_bytes') > $this->intConfig('max_bytes')) {
            throw new InvalidArgumentException(
                'Prefab Background max_payload_bytes cannot exceed max_bytes.'
            );
        }
    }

    private function configured(): void
    {
        if ($this->resolved === []) {
            $this->prefabConfigure();
        }
    }

    private function intConfig(string $key): int
    {
        $this->configured();
        return (int) $this->resolved[$key];
    }

    private function boolConfig(string $key): bool
    {
        $this->configured();
        return (bool) $this->resolved[$key];
    }

    private function installSignalHandlers(): void
    {
        if (!function_exists('pcntl_signal') || !function_exists('pcntl_async_signals')) {
            return;
        }

        pcntl_async_signals(true);

        if (defined('SIGTERM')) {
            pcntl_signal(SIGTERM, fn () => $this->stopRequested = true);
        }

        if (defined('SIGINT')) {
            pcntl_signal(SIGINT, fn () => $this->stopRequested = true);
        }
    }
}
