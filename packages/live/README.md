# Prefab Live

**Prefab Live** adds server-driven reactive PHP components to Prefab without requiring a JavaScript framework.

> Keep application state and actions in PHP. Use small `pf:*` attributes to connect the browser to the server.

Prefab Live v0.1 focuses on the core reactive loop:

- PHP component classes;
- public component state;
- `pf:model` state binding;
- `pf:click` actions;
- `pf:submit` form actions;
- `pf:loading` request state;
- signed snapshots;
- explicit `#[Action]` methods;
- `#[Locked]` state that may be rendered but not client-updated;
- optional CSRF validation;
- component-local HTML replacement;
- lifecycle hooks: `mount()`, `hydrate()`, `dehydrate()`;
- lightweight error bags on components;
- a small framework-independent browser runtime.

Prefab Live does not require Laravel, Livewire, React, Vue, Alpine or jQuery.

## Requirements

- PHP 8.1 or newer
- Composer
- a route/endpoint capable of receiving JSON POST requests

## Installation

When published:

```bash
composer require tihloh/prefab-live
```

## 1. Create a component

```php
use Tihloh\Prefab\Live\Attributes\Action;
use Tihloh\Prefab\Live\Attributes\Locked;
use Tihloh\Prefab\Live\Component;

final class Counter extends Component
{
    public int $count = 0;

    #[Locked]
    public int $ownerId = 0;

    protected function mount(int $start = 0): void
    {
        $this->count = $start;
        $this->ownerId = 42;
    }

    #[Action]
    public function increment(): void
    {
        $this->count++;
    }

    public function render(): string
    {
        return <<<HTML
        <button pf:click="increment">Count: {$this->count}</button>
        <span pf:loading>Working...</span>
        HTML;
    }
}
```

Only methods marked with `#[Action]` may be called by browser requests.

## 2. Register components

The browser sends a component alias, never a PHP class name. The server resolves the alias through an explicit registry:

```php
use Tihloh\Prefab\Live\ComponentRegistry;

$registry = new ComponentRegistry();
$registry->register('counter', Counter::class);
```

Factories are also supported for dependency injection:

```php
$registry->register('users.search', fn () => new UserSearch($users));
```

## 3. Create the Live manager

Use a private application signing key of at least 32 bytes:

```php
use Tihloh\Prefab\Live\LiveManager;

$live = new LiveManager(
    registry: $registry,
    signingKey: $_ENV['PREFAB_LIVE_KEY'],
    endpoint: '/prefab/live',
);
```

Do not expose `PREFAB_LIVE_KEY` to the browser or commit it to source control.

## 4. Mount a component

```php
<?= $live->mount('counter', ['start' => 5]) ?>
```

Prefab Live wraps the rendered component with its alias, instance ID, signed snapshot, endpoint and optional CSRF token.

## 5. Add the browser runtime

Serve `assets/prefab-live.js` from your application's public assets and load it once:

```html
<script src="/assets/prefab-live.js" defer></script>
```

No build step is required.

## 6. Handle the Live endpoint

The endpoint receives JSON and returns the result of `handle()` as JSON.

Plain PHP example:

```php
$payload = LiveManager::decodeRequest(file_get_contents('php://input') ?: '');
$response = $live->handle(
    $payload,
    $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null,
);

header('Content-Type: application/json');
echo json_encode($response, JSON_THROW_ON_ERROR);
```

Prefab Live itself does not call `exit()` or force a response abstraction. Your application or router owns HTTP output and error handling.

## 7. `pf:model`

Bind normal form controls to public component properties:

```php
final class ProfileForm extends Component
{
    public string $name = '';
    public bool $active = false;

    #[Action]
    public function save(): void
    {
        // Persist $this->name and $this->active.
    }

    public function render(): string
    {
        $name = htmlspecialchars($this->name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $checked = $this->active ? 'checked' : '';

        return <<<HTML
        <form pf:submit="save">
            <input name="name" pf:model="name" value="{$name}">
            <label>
                <input type="checkbox" pf:model="active" {$checked}>
                Active
            </label>
            <button type="submit">Save</button>
            <span pf:loading hidden>Saving...</span>
        </form>
        HTML;
    }
}
```

In v0.1, `pf:model` values are collected when a Live action is sent. Automatic live/debounced model requests are intentionally deferred to a later version.

Nested array paths are supported:

```html
<input pf:model="filters.search">
<input pf:model="address.city">
```

The top-level public property must be an array for nested updates.

## 8. Forms

```html
<form pf:submit="save">
    <input pf:model="name">
    <input pf:model="email">
    <button type="submit">Save</button>
    <span pf:loading>Saving...</span>
</form>
```

The browser runtime prevents the normal form submission, sends current model values plus the action, then replaces only that component's rendered HTML.

## 9. Loading UI

Any element with `pf:loading` is hidden when idle and visible while the component request is running:

```html
<button pf:click="refresh">Refresh</button>
<span pf:loading>Loading...</span>
```

## 10. Lifecycle

A component may define:

```php
protected function mount(int $userId): void
{
    // Initial mount only.
}

protected function hydrate(): void
{
    // After signed state is restored on a Live request.
}

protected function dehydrate(): void
{
    // Final cleanup hook after rendering/snapshot creation.
}
```

Lifecycle methods are infrastructure hooks, not browser-callable actions.

## 11. Component errors

Components have a small error bag for render-time validation feedback:

```php
#[Action]
public function save(): void
{
    $this->clearErrors();

    if (trim($this->name) === '') {
        $this->addError('name', 'Name is required.');
        return;
    }
}
```

Then in `render()`:

```php
$error = $this->error('name');
```

Prefab Input can later be used inside actions for richer validation without making it mandatory for Prefab Live.

## 12. Security model

A Live request contains:

```text
component alias
instance ID
signed previous snapshot
model updates
optional action
```

The server performs this sequence:

```text
request
  ↓
verify signed snapshot
  ↓
resolve alias through ComponentRegistry
  ↓
restore public state
  ↓
apply explicit model updates
  ↓
call only a #[Action] method
  ↓
render
  ↓
sign next snapshot
  ↓
response
```

Important rules:

1. Browser requests never choose arbitrary PHP classes.
2. Public methods are not remotely callable unless they have `#[Action]`.
3. Previous state is protected by an HMAC checksum.
4. Mark identifiers or server-controlled public state with `#[Locked]` when the browser must not update them.
5. Public component state is browser-visible. Do not put passwords, API secrets, access tokens or other secrets in public properties.
6. Authorization still belongs in your application/action. A signed request does not replace permission checks.
7. Use HTTPS in production.
8. Use your application's CSRF validation for authenticated browser sessions.

## 13. CSRF integration

Pass the rendered CSRF token and a validator:

```php
$live = new LiveManager(
    registry: $registry,
    signingKey: $_ENV['PREFAB_LIVE_KEY'],
    endpoint: '/prefab/live',
    csrfToken: $session->csrfToken(),
    csrfValidator: fn (?string $token): bool => $session->validateCsrf($token),
);
```

The browser runtime sends the token in `X-CSRF-Token`.

## 14. State types

Prefab Live v0.1 public state is intentionally simple:

```text
null
string
int
float
bool
array of JSON-safe values
```

Objects, database connections, service objects and resources belong in private/protected properties or constructor-injected dependencies, not serialized public state.

Typed public scalar properties are safely coerced from browser form values where possible.

## 15. Request protocol

Example request:

```json
{
  "id": "f12ab34cd56ef789",
  "component": "counter",
  "snapshot": {"count": 5},
  "checksum": "...",
  "updates": {"count": "7"},
  "action": {"method": "increment", "params": []}
}
```

Example response:

```json
{
  "id": "f12ab34cd56ef789",
  "component": "counter",
  "html": "<button pf:click=\"increment\">Count: 8</button>",
  "snapshot": {"count": 8},
  "checksum": "..."
}
```

## 16. v0.1 responsibility boundary

Prefab Live owns:

```text
PHP component state
      ↕
signed Live protocol
      ↕
tiny browser bridge
      ↕
component-local DOM replacement
```

It does not own:

- your database/business model;
- application authorization policy;
- page routing;
- a template engine;
- frontend styling;
- general JavaScript application state;
- file uploads yet;
- nested Live components yet;
- polling/lazy loading yet;
- URL/query-string binding yet;
- automatic/debounced live model syncing yet.

Those features can be added after the base protocol is stable.

## 17. Design principle

Prefab Live follows the wider Prefab rule:

> **Prefab automates reusable plumbing. Your application keeps control of behavior and architecture.**

For ordinary interactive PHP screens, the target experience is:

```text
normal PHP
    +
normal HTML
    +
a few pf:* attributes
    =
server-driven reactive UI
```
