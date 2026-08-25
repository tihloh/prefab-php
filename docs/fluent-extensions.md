# Prefab Fluent Extensions

> **Add a block. Gain a capability. Improve the blocks you already have.**

Prefab Fluent Extensions let an optional module contribute methods to compatible objects without creating a hard package dependency.

```php
$users->update($id, $data)
      ->notify()
      ->email();
```

`prefab-users` does not need to depend on Notifications or Messaging. The module that owns a capability registers its extension with `PrefabRuntime`.

## Three integration mechanisms

Prefab keeps these concepts distinct:

1. **Auto-Wiring** — infrastructure connects automatically, e.g. Auth discovers Users or Logs.
2. **Fluent Extensions** — an installed module contributes an explicit fluent action such as `notify()`, `email()`, `auth()`, `can()` or `validate()`.
3. **Object Interoperability** — compatible values pass naturally between blocks, e.g. Input upload → Files storage.

## Runtime API

A provider registers an extension:

```php
PrefabRuntime::extend(
    target: SomeOperation::class,
    method: 'notify',
    handler: function (SomeOperation $operation, ...$arguments) {
        // perform notification
        return $operation; // preserve fluent chaining
    },
    provider: 'notifications',
);
```

Compatible objects opt into extension dispatch with `FluentExtensions`:

```php
final class SomeOperation
{
    use FluentExtensions;
}
```

The object can then inspect what the current Prefab composition provides:

```php
$operation->hasExtension('notify');
$operation->extensions();
```

Global diagnostics also expose registered extensions:

```php
PrefabRuntime::inspect()['extensions'];
```

## Missing extensions

A missing extension is never silently ignored:

```php
$operation->notify();
```

If no compatible module registered `notify`, Prefab throws a catchable `BadMethodCallException` explaining that the fluent extension is unavailable. Base functionality remains unaffected.

## Conflicts

Providers may declare priorities. If multiple compatible providers have the same highest priority for the same method, Prefab throws a clear ambiguity error rather than guessing.

## Initial extension direction

| Provider | Compatible target | Extension |
|---|---|---|
| Auth | Route objects | `auth()` |
| Permissions | Route objects | `can()` |
| Input | Route objects | `validate()` |
| Notifications | operation/result objects with recipients | `notify()` |
| Messaging | operation/result objects with external recipients | `email()` |
| Logs | auditable operation/result objects | `audit()` |

These are capability goals, not permission for every module to extend every object. Each extension must have a natural semantic fit.

## Design rules

- The provider owns the extension implementation.
- The target module must not require the provider package.
- Removing an optional provider removes only its extension, not base functionality.
- Fluent extensions express explicit application intent; they must not silently invent business rules.
- Automatic infrastructure behavior belongs to Auto-Wiring, not fluent methods.
- Prefer ordinary object interoperability when a new fluent method adds no clarity.
- Extension handlers should normally return the target when chaining is appropriate.
- Missing or ambiguous extensions produce catchable errors, never `die()`/`exit()`.

## Example growth

```text
prefab-users
    $users->update(...)

+ prefab-notifications
    $users->update(...)->notify()

+ prefab-messaging
    $users->update(...)->notify()->email()

+ prefab-logs
    standard user operations may also be automatically audited
```

The result is a richer assembled API without turning the standalone packages into tightly coupled dependencies.
