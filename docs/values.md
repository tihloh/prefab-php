# Universal Values

Prefab Core provides `val()` as a small fluent layer over ordinary PHP values.

The goal is simple: keep normal PHP values, but make common conversion, cleanup, formatting, nested access and fallback operations easier to compose.

## Start simple

```php
$name = val(' Christian ')->trim();
$age = val('42')->toInt();
```

A value can continue through more operations:

```php
$age = val($input)
    ->toInt()
    ->fallback(0);
```

Prefab keeps the fluent wrapper until a terminal operation is requested. PHP cannot make one value simultaneously be a native integer and an object that still accepts `->` methods.

## Native value when required

Most Prefab-aware code can keep the wrapper. Use `value()` when external/native PHP code specifically requires the underlying value:

```php
$age = val('42')->toInt()->value();

is_int($age); // true
```

## Defaults and fallback

`default()` handles unavailable/empty input without replacing meaningful falsey values:

```php
val(null)->default('Guest');
val('')->default('Guest');
val(0)->default(100);       // remains 0
val(false)->default(true);  // remains false
```

`fallback()` can recover after a failed conversion:

```php
$age = val($input)
    ->toInt()
    ->fallback(0);
```

Use `orFail()` when the original failure should be propagated instead.

## Nested values

Use `get()` for dot-path access:

```php
$name = val($data)
    ->get('user.profile.name')
    ->default('Guest');
```

This replaces repetitive nested `isset()` / null-coalescing logic while keeping the result fluent.

## Common conversions

```php
val('42')->toInt();
val('12.50')->toFloat();
val('true')->toBool();
val('2026-09-03')->toDateTime();
```

Conversions remain fluent so fallback can be attached after them.

## String operations

```php
$name = val(' christian ')
    ->trim()
    ->upper();

echo $name; // CHRISTIAN
```

String output can be used directly because the wrapper supports string conversion.

## Formatting

Formatting is a terminal display operation and returns a string:

```php
echo val(12500)->format('currency', ['currency' => 'PHP']);
echo val('09171234567')->format('phone');
echo val(0.75)->format('percent');
echo val('2026-09-03')->format('date');
```

Use formatting for presentation. Keep raw/converted values for persistence and calculations.

## Inspection

```php
val('hello')->isString();
val('123')->isNumber();
val('test@example.com')->isEmail();
val('192.168.1.1')->isIp();
val('https://example.com')->isUrl();
val(null)->isNull();
```

Inspection methods return native booleans.

## Database interoperability

Prefab Core's query builder understands `Value` objects and unwraps them at the PDO boundary:

```php
$db->table('users')->insert([
    'name' => val($_POST['name'] ?? null)
        ->trim()
        ->default('Unknown'),

    'age' => val($_POST['age'] ?? null)
        ->toInt()
        ->fallback(0),
]);
```

The same applies to query values:

```php
$user = $db->table('users')
    ->where('age', val($_GET['age'] ?? null)->toInt()->fallback(0))
    ->first();
```

You do not need `->value()` at these Prefab database boundaries.

If a conversion is still in a failed state and no fallback was supplied, the database boundary propagates the failure instead of silently storing an invalid value.

## Practical form example

```php
$name = val($_POST['name'] ?? null)
    ->trim()
    ->default('Unknown');

$age = val($_POST['age'] ?? null)
    ->toInt()
    ->fallback(0);

$active = val($_POST['active'] ?? false)
    ->toBool()
    ->fallback(false);
```

## Design rule

`val()` is not intended to replace PHP's type system or become a large collection framework.

It provides small generic conveniences where native PHP is repetitive or scattered. Native values remain available through `value()`, and Prefab-owned boundaries should understand the wrapper where doing so is natural.
