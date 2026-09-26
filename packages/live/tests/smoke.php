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


final class AvailabilityForm extends Component
{
    public string $email = '';
    public bool $saved = false;

    protected function liveChecks(): array
    {
        return [
            'email' => fn (mixed $value): ?string => $value === 'taken@example.com'
                ? 'Email is already registered.'
                : null,
        ];
    }

    #[Action]
    public function save(): void
    {
        if (!$this->validate()) {
            return;
        }

        $this->saved = true;
    }

    public function render(): string
    {
        $email = htmlspecialchars($this->email, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return '<input pf:model.live.debounce.400ms="email" value="' . $email . '">'
            . '<small pf:error="email"></small>'
            . '<small pf:loading="email">Checking...</small>';
    }
}

if (class_exists(\Tihloh\Prefab\Input\Input::class)) {
    final class RegistrationForm extends Component
    {
        public string $email = '';
        public string $username = '';
        public bool $saved = false;

        protected function rules(): array
        {
            return [
                'email' => 'trim|lowercase|required|email',
                'username' => 'trim|lowercase|required|string|max:30',
            ];
        }

        protected function liveChecks(): array
        {
            return [
                'email' => fn (mixed $value): ?string => $value === 'taken@example.com'
                    ? 'Email is already registered.'
                    : null,
                'username' => fn (mixed $value): ?string => $value === 'admin'
                    ? 'Username is already taken.'
                    : null,
            ];
        }

        #[Action]
        public function save(): void
        {
            if (!$this->validate()) {
                return;
            }

            $this->saved = true;
        }

        public function render(): string
        {
            return '<form pf:submit="save">'
                . '<input pf:model.live.debounce.400ms="email" value="' . htmlspecialchars($this->email) . '">'
                . '<small pf:error="email"></small>'
                . '<input pf:model.blur="username" value="' . htmlspecialchars($this->username) . '">'
                . '<small pf:error="username"></small>'
                . '</form>';
        }
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

$registry = (new ComponentRegistry())
    ->register('counter', Counter::class)
    ->register('availability', AvailabilityForm::class);

if (class_exists(\Tihloh\Prefab\Input\Input::class)) {
    $registry->register('registration', RegistrationForm::class);
}
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


$availabilityHtml = $live->mount('availability');
$availabilityId = attr($availabilityHtml, 'pf:id');
$availabilityChecksum = attr($availabilityHtml, 'pf:checksum');
$availabilitySnapshot = decodeSnapshot(attr($availabilityHtml, 'pf:snapshot'));

$availability = $live->handle([
    'id' => $availabilityId,
    'component' => 'availability',
    'snapshot' => $availabilitySnapshot,
    'checksum' => $availabilityChecksum,
    'updates' => ['email' => 'taken@example.com'],
    'validate' => ['email'],
    'action' => null,
], 'csrf-token');

assert($availability['snapshot']['email'] === 'taken@example.com');
assert($availability['validated'] === ['email']);
assert($availability['errors']['email'][0] === 'Email is already registered.');

$availability = $live->handle([
    'id' => $availability['id'],
    'component' => $availability['component'],
    'snapshot' => $availability['snapshot'],
    'checksum' => $availability['checksum'],
    'updates' => ['email' => 'free@example.com'],
    'validate' => ['email'],
    'action' => null,
], 'csrf-token');

assert($availability['errors'] === []);
assert($availability['snapshot']['email'] === 'free@example.com');

$availability = $live->handle([
    'id' => $availability['id'],
    'component' => $availability['component'],
    'snapshot' => $availability['snapshot'],
    'checksum' => $availability['checksum'],
    'updates' => ['email' => 'taken@example.com'],
    'action' => ['method' => 'save', 'params' => []],
], 'csrf-token');

assert($availability['snapshot']['saved'] === false);
assert($availability['errors']['email'][0] === 'Email is already registered.');

if (class_exists(\Tihloh\Prefab\Input\Input::class)) {
    $registrationHtml = $live->mount('registration');
    $registration = $live->handle([
        'id' => attr($registrationHtml, 'pf:id'),
        'component' => 'registration',
        'snapshot' => decodeSnapshot(attr($registrationHtml, 'pf:snapshot')),
        'checksum' => attr($registrationHtml, 'pf:checksum'),
        'updates' => ['email' => '  TAKEN@EXAMPLE.COM  '],
        'validate' => ['email'],
        'action' => null,
    ], 'csrf-token');

    assert($registration['snapshot']['email'] === 'taken@example.com');
    assert($registration['errors']['email'][0] === 'Email is already registered.');

    $registration = $live->handle([
        'id' => $registration['id'],
        'component' => $registration['component'],
        'snapshot' => $registration['snapshot'],
        'checksum' => $registration['checksum'],
        'updates' => ['email' => 'USER@EXAMPLE.COM', 'username' => 'NewUser'],
        'action' => ['method' => 'save', 'params' => []],
    ], 'csrf-token');

    assert($registration['snapshot']['email'] === 'user@example.com');
    assert($registration['snapshot']['username'] === 'newuser');
    assert($registration['snapshot']['saved'] === true);
    assert($registration['errors'] === []);
}

echo "Prefab Live smoke test passed", PHP_EOL;
