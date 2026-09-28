# Prefab Background

**Prefab Background** runs work outside the main PHP request **as soon as worker capacity is available**, without creating an unbounded number of PHP processes.

> Run this now, elsewhere.

It is intentionally not a scheduling API and does not expose delayed jobs, priorities or application-managed queue semantics.

## Why

Some secondary work should not hold the main request open:

- activity/audit log persistence;
- email/SMS delivery;
- notification fan-out;
- image derivatives;
- other independent side effects.

Spawning a new PHP process for every event can exhaust CPU/RAM under load. Prefab Background instead uses a fixed worker pool plus a bounded durable journal.

```text
web request
    │
    │ capture occurred_at now
    ↓
durable immediate handoff
    │
    ├── worker available → starts immediately
    │
    └── workers busy → journal keeps instruction safely
                           ↓
                    next worker takes it
```

If the configured journal capacity is full, the dispatcher waits until capacity becomes available. It never drops an accepted instruction and does not silently switch to synchronous execution.

## Installation

```bash
composer require tihloh/prefab-background
```

## Configure

Put shared worker setup in the application's normal Prefab bootstrap, for example `bootstrap/prefab.php`:

```php
use Tihloh\Prefab\Background\BackgroundManager;

$background = new BackgroundManager([
    'path' => __DIR__ . '/../storage/prefab/background',
    'max_workers' => 2,
]);

$background->handler('logs.write', function (array $payload): void {
    // Write the log using application/Prefab services.
});
```

The handler name is registered server-side. The instruction contains JSON-safe data only; arbitrary PHP code/classes are never serialized into the journal.

## Run work immediately in the background

```php
$receipt = $background->run('logs.write', [
    'actor_id' => 15,
    'action' => 'document.approved',
    'document_id' => 125,
]);
```

`run()` means **run as soon as possible in a background worker**. It does not mean schedule for later.

## Accurate event time

The event timestamp is captured in the caller before any backpressure wait:

```text
occurred_at = when the main process dispatched the event
accepted_at = when Background safely committed the instruction
processed_at = when a worker completed it
```

Handlers receive a `BackgroundContext`:

```php
use Tihloh\Prefab\Background\BackgroundContext;

$background->handler(
    'audit.write',
    function (array $payload, BackgroundContext $context): void {
        echo $context->occurredAt;
        echo $context->requestId;
        echo $context->sequence;
    }
);
```

`requestId + sequence` preserves the originating request's order even if several workers finish instructions in a different order.

An application may also provide its own originating timestamp:

```php
$background->run(
    'audit.write',
    $payload,
    '2026-09-28T06:31:12.123456Z',
);
```

All Background timestamps are normalized to UTC with microsecond precision.

## Worker

Start a worker from the application root:

```bash
vendor/bin/prefab-background work
```

The binary loads `bootstrap/prefab.php`, so the worker sees the same registered handlers and services as the application.

Check status:

```bash
vendor/bin/prefab-background status
```

Process one instruction for testing:

```bash
vendor/bin/prefab-background once
```

## Fixed worker ceiling

`max_workers` is a hard concurrency ceiling enforced with process locks:

```php
$background = new BackgroundManager([
    'max_workers' => 2,
]);
```

Starting a third worker does not increase background concurrency.

For production, start exactly the configured number of workers under systemd, Supervisor or Docker so crashed/recycled workers are restarted automatically.

## Bounded backpressure

The journal is bounded:

```php
$background = new BackgroundManager([
    'max_pending' => 1000,
    'max_bytes' => 32 * 1024 * 1024,
    'max_payload_bytes' => 64 * 1024,
]);
```

When either journal limit is reached:

```text
main request
    ↓
capture occurred_at
    ↓
journal full
    ↓
sleep efficiently / wait for worker capacity
    ↓
commit instruction
    ↓
main request continues
```

There is no drop, refusal or synchronous fallback for journal saturation.

This tradeoff is deliberate: with finite workers and finite storage, preserving every instruction necessarily means the caller can eventually experience backpressure during sustained overload.

## Retry behavior

A handler exception does not delete the instruction. It remains in the durable journal and is retried with bounded exponential backoff.

```php
[
    'retry_base_ms' => 250,
    'retry_max_ms' => 30000,
]
```

There is no default maximum retry count, so failed work is retained instead of silently discarded.

## Worker protection

Conservative defaults protect the server:

```php
[
    'max_workers' => 2,
    'worker_max_tasks' => 500,
    'worker_max_lifetime' => 1800,
    'task_timeout' => 30,
]
```

Workers recycle after the task/lifetime limit. Run them under a process supervisor so a fresh worker starts automatically.

`task_timeout` applies PHP's execution-time limit inside the background worker where supported. Blocking extensions/system calls may have platform-specific timeout behavior, so OS/container resource controls are still recommended for heavy workloads.

## Docker

Run the web process and workers separately:

```yaml
services:
  web:
    # normal PHP-FPM service

  background:
    # same application image/source
    command: vendor/bin/prefab-background work
    restart: unless-stopped
    deploy:
      replicas: 2
```

Keep the configured `max_workers` aligned with the worker replicas. The journal path must be shared by the web and worker containers.

For a single-host bind mount, for example:

```yaml
volumes:
  - ./storage:/var/www/html/storage
```

## systemd

One practical approach is a templated service with two instances:

```ini
[Service]
WorkingDirectory=/srv/my-app
ExecStart=/usr/bin/php /srv/my-app/vendor/bin/prefab-background work
Restart=always
RestartSec=1
Nice=10
```

Then enable only as many instances as `max_workers` permits.

## Defaults

```text
max_workers          2
max_pending          1000
max_bytes            32 MB
max_payload_bytes    64 KB
wait_us              10 ms
idle_us              25 ms
retry_base_ms        250 ms
retry_max_ms         30 s
worker_max_tasks     500
worker_max_lifetime  30 min
task_timeout         30 s
fsync                true
```

## Status

```php
$background->status();
```

returns the journal size, configured/busy workers and registered handlers.

## Responsibility boundary

Prefab Background owns:

```text
immediate handoff
bounded durable journal
worker concurrency ceiling
retry retention
backpressure
worker lifecycle
event timing metadata
```

It does not own:

- application business logic;
- a scheduler;
- delayed jobs;
- cron;
- handler-specific authorization;
- database transactions of the originating request;
- operating-system process supervision.

A background instruction executes in another PHP process and therefore cannot share the caller's open database transaction, object memory or request globals. Pass stable IDs/data and let the handler load what it needs.

## Design rule

> **Background work must never be allowed to consume unbounded server resources. Main application health has priority over background throughput, while accepted instructions are preserved.**


## Prefab Logs integration

Prefab Logs can opt into Background without changing normal application logging calls:

```php
$background = new BackgroundManager([
    'path' => __DIR__ . '/../storage/prefab/background',
    'max_workers' => 2,
]);

$logs = new LogManager([
    'database' => $pdo,
    'background' => true,
]);

$logs->record([
    'action' => 'document.approved',
    'subject_type' => 'document',
    'subject_id' => 1001,
]);
```

With `background => true`, Logs captures `occurred_at` in the originating request before any Background backpressure wait. The returned string is the Background instruction ID; the worker later performs the repository insert.

`created_at` remains the database persistence time, while `occurred_at` is the actual application event time.
