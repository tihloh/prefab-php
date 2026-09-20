<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Tihloh\Prefab\Live\Attributes\Action;
use Tihloh\Prefab\Live\Attributes\Locked;
use Tihloh\Prefab\Live\Component;
use Tihloh\Prefab\Live\ComponentRegistry;
use Tihloh\Prefab\Live\LiveManager;

final class Counter extends Component
{
    public int $count = 0;
    public string $label = 'Count';

    #[Locked]
    public int $ownerId = 42;

    protected function mount(int $start = 0): void
    {
        $this->count = $start;
    }

    #[Action]
    public function increment(): void
    {
        $this->count++;
    }

    public function dangerousHelper(): void
    {
        $this->count = 999;
    }

    public function render(): string
    {
        return '<button pf:click="increment">' . htmlspecialchars($this->label) . ': ' . $this->count . '</button>'
            . '<span pf:loading>Loading...</span>';
    }
}

function attr(string $html, string $name): string
{
    preg_match('/' . preg_quote($name, '/') . '="([^"]+)"/', $html, $matches);
    assert(isset($matches[1]));
    return html_entity_decode($matches[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function decodeSnapshot(string $encoded): array
{
    $encoded = strtr($encoded, '-_', '+/');
    $encoded .= str_repeat('=', (4 - strlen($encoded) % 4) % 4);
    $json = base64_decode($encoded, true);
    assert(is_string($json));
    $state = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    assert(is_array($state));
    return $state;
}

$registry = (new ComponentRegistry())->register('counter', Counter::class);
$live = new LiveManager(
    $registry,
    str_repeat('k', 32),
    '/prefab/live',
    'csrf-token',
    fn (?string $token): bool => $token === 'csrf-token',
);

$html = $live->mount('counter', ['start' => 2]);
assert(str_contains($html, 'pf:component="counter"'));
assert(str_contains($html, 'Count: 2'));

$id = attr($html, 'pf:id');
$checksum = attr($html, 'pf:checksum');
$snapshot = decodeSnapshot(attr($html, 'pf:snapshot'));
assert($snapshot['count'] === 2);

$response = $live->handle([
    'id' => $id,
    'component' => 'counter',
    'snapshot' => $snapshot,
    'checksum' => $checksum,
    'updates' => ['count' => '7', 'label' => 'Clicks'],
    'action' => ['method' => 'increment', 'params' => []],
], 'csrf-token');

assert($response['snapshot']['count'] === 8);
assert($response['snapshot']['label'] === 'Clicks');
assert(str_contains($response['html'], 'Clicks: 8'));

$tampered = $snapshot;
$tampered['count'] = 500;
$tamperRejected = false;
try {
    $live->handle([
        'id' => $id,
        'component' => 'counter',
        'snapshot' => $tampered,
        'checksum' => $checksum,
        'updates' => [],
        'action' => null,
    ], 'csrf-token');
} catch (RuntimeException) {
    $tamperRejected = true;
}
assert($tamperRejected);

$unexposedRejected = false;
try {
    $live->handle([
        'id' => $id,
        'component' => 'counter',
        'snapshot' => $response['snapshot'],
        'checksum' => $response['checksum'],
        'updates' => [],
        'action' => ['method' => 'dangerousHelper', 'params' => []],
    ], 'csrf-token');
} catch (InvalidArgumentException) {
    $unexposedRejected = true;
}
assert($unexposedRejected);

$lockedRejected = false;
try {
    $live->handle([
        'id' => $id,
        'component' => 'counter',
        'snapshot' => $response['snapshot'],
        'checksum' => $response['checksum'],
        'updates' => ['ownerId' => '99'],
        'action' => null,
    ], 'csrf-token');
} catch (InvalidArgumentException) {
    $lockedRejected = true;
}
assert($lockedRejected);

$csrfRejected = false;
try {
    $live->handle([
        'id' => $id,
        'component' => 'counter',
        'snapshot' => $response['snapshot'],
        'checksum' => $response['checksum'],
        'updates' => [],
        'action' => null,
    ], 'wrong-token');
} catch (RuntimeException) {
    $csrfRejected = true;
}
assert($csrfRejected);

echo "Prefab Live smoke test passed", PHP_EOL;
