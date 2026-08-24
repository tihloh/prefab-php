# Prefab Input

**Prefab Input** turns raw/untrusted PHP input into clean, normalized, typed, validated and whitelisted data.

> Define what your application accepts. Everything else stays outside the validated result.

It combines the closely related concerns that normally happen before business logic:

- validation;
- trimming and sanitizing-style transformations;
- normalization;
- type casting;
- field filtering/whitelisting;
- defaults;
- nested input;
- conditional rules;
- custom rules and transformers;
- friendly validation errors;
- safe validated-data extraction.

Prefab Input is standalone. It does not require Routes, Database, Users, Auth, Permissions, Logs, Laravel, or another framework.

## Requirements

- PHP 8.1 or newer
- Composer when installed as a package

## Installation

When published:

```bash
composer require tihloh/prefab-input
```

---

# 1. Quick start

```php
use Tihloh\Prefab\Input\Input;

$input = new Input($_POST);

$result = $input->process([
    'name' => 'trim|required|string|max:100',
    'email' => 'trim|lowercase|required|email',
    'age' => 'nullable|integer|min:18',
    'active' => 'default:true|boolean',
]);

if ($result->fails()) {
    print_r($result->errors());
    return;
}

$data = $result->validated();
```

`validated()` contains only fields declared by the schema and only fields that passed processing.

---

# 2. Why Input is broader than validation

Incoming application data rarely needs only a yes/no validation check.

A real request often needs to become:

```text
"  Christian  "   → "Christian"
"USER@MAIL.COM"   → "user@mail.com"
"25"              → 25
"true"            → true
""                → null
```

Prefab Input handles those steps in one predictable pipeline before the data reaches a database, service or business object.

---

# 3. Schema as a whitelist

Suppose the browser sends:

```php
$_POST = [
    'name' => 'Christian',
    'email' => 'user@example.com',
    'role' => 'admin',
    'is_superuser' => true,
];
```

but your schema contains only:

```php
$result = (new Input($_POST))->process([
    'name' => 'trim|required|string',
    'email' => 'trim|lowercase|required|email',
]);
```

Then:

```php
$result->validated();
```

returns only:

```php
[
    'name' => 'Christian',
    'email' => 'user@example.com',
]
```

Undeclared fields do not automatically become trusted application data.

---

# 4. Compact and array schemas

Compact pipe syntax:

```php
'name' => 'trim|required|string|max:100'
```

Array syntax is useful for readability and callable rules:

```php
'name' => [
    'trim',
    'required',
    'string',
    'max:100',
]
```

Both forms use the same processor.

---

# 5. Built-in transformations and casting

Built-in transformations include:

| Operation | Effect |
|---|---|
| `trim` | Trim surrounding whitespace from strings |
| `lowercase` | Convert strings to lowercase |
| `uppercase` | Convert strings to uppercase |
| `null_if_empty` | Convert an empty string to `null` |
| `string` | Cast to string |
| `integer` | Cast a valid integer representation to `int` |
| `float` | Cast numeric input to `float` |
| `boolean` | Cast common boolean representations to `bool` |
| `array` | Preserve arrays or wrap a scalar as an array |

Example:

```php
$result = Input::from([
    'email' => '  ADMIN@EXAMPLE.COM ',
    'age' => '35',
    'active' => 'true',
])->process([
    'email' => 'trim|lowercase|email',
    'age' => 'integer|min:18',
    'active' => 'boolean',
]);
```

Validated output:

```php
[
    'email' => 'admin@example.com',
    'age' => 35,
    'active' => true,
]
```

Invalid integer/float/boolean casts produce validation errors rather than silently being accepted as correctly typed data.

---

# 6. Core validation rules

Built-in rules include:

```text
required
nullable
sometimes
required_if
required_with
email
url
string
integer
float
numeric
boolean
array
date
min
max
between
in
not_in
same
different
regex
confirmed
```

Examples:

```php
$result = $input->process([
    'email' => 'required|email',
    'age' => 'integer|between:18,100',
    'status' => 'in:draft,published,archived',
]);
```

---

# 7. Required, nullable and sometimes

Required:

```php
'name' => 'required|string'
```

Nullable:

```php
'middle_name' => 'nullable|trim|string|max:100'
```

If the supplied nullable value is `null` or an empty string, validation stops for that field and the processed value becomes `null`.

Sometimes:

```php
'bio' => 'sometimes|trim|string|max:500'
```

If `bio` is absent, Prefab ignores the field completely. If it is present, the remaining operations are applied.

---

# 8. Defaults

Defaults apply only when a field is absent:

```php
'active' => 'default:true|boolean'
```

Supported literal defaults recognize:

```text
true
false
null
```

Other values remain strings unless a following cast transforms them.

Example:

```php
'page' => 'default:1|integer|min:1'
```

---

# 9. Conditional required rules

Require a field when another field has a specific value:

```php
'company_name' => 'required_if:type,business|trim|string'
```

Require a field when another field is present and non-empty:

```php
'phone_extension' => 'required_with:phone|string'
```

These rules allow common form/API conditions without manually writing branching validation code.

---

# 10. Minimum, maximum and between

For numbers, size means numeric value:

```php
'age' => 'integer|min:18|max:100'
```

For strings, size means string length:

```php
'name' => 'trim|string|between:2,100'
```

For arrays, size means item count.

---

# 11. Field comparison

Same value:

```php
'email_confirmation' => 'same:email'
```

Different value:

```php
'new_email' => 'different:old_email'
```

Password-style confirmation has a convenience rule:

```php
'password' => 'required|string|min:8|confirmed'
```

which compares against:

```text
password_confirmation
```

---

# 12. Nested input

Dot notation reads and writes nested arrays:

```php
$result = Input::from([
    'user' => [
        'name' => ' Christian ',
        'email' => 'USER@EXAMPLE.COM',
    ],
])->process([
    'user.name' => 'trim|required|string',
    'user.email' => 'trim|lowercase|required|email',
]);
```

Validated output preserves nesting:

```php
[
    'user' => [
        'name' => 'Christian',
        'email' => 'user@example.com',
    ],
]
```

---

# 13. Result object

`process()` returns `InputResult`.

```php
$result->passes();
$result->fails();
$result->raw();
$result->all();
$result->validated();
$result->errors();
$result->first();
$result->first('email');
$result->value('user.email');
```

The distinction is intentional:

```text
raw()       → original untrusted input
all()       → schema fields after transformations, including invalid fields
validated() → schema fields that passed processing
errors()    → validation messages
```

Prefer `validated()` when passing request data to application services or persistence.

---

# 14. Friendly attribute names

Generated messages normally derive their label from the field name.

Customize them:

```php
$input->attributes([
    'email_address' => 'Email address',
    'user.first_name' => 'First name',
]);
```

Then errors are more suitable for user interfaces.

---

# 15. Custom messages

Customize one field/rule:

```php
$input->messages([
    'email.required' => 'Please enter your email address.',
]);
```

Or customize a rule globally for that Input instance:

```php
$input->messages([
    'required' => ':attribute is required.',
]);
```

`:attribute` is replaced with the friendly field name.

---

# 16. Custom validation rules

Register a reusable named rule:

```php
$input->rule('even', function ($field, $value) {
    if (!is_int($value) || $value % 2 !== 0) {
        return 'The value must be an even integer.';
    }

    return null;
});
```

Use it normally:

```php
$result = $input->process([
    'quantity' => 'integer|even',
]);
```

Custom rule callbacks receive the field, current value, original raw data, parsed rule parameters and an existence flag.

This is also the extension point for database-aware rules such as `unique` and `exists`.

---

# 17. Inline callable rules

For one-off application rules, place a callable directly in an array schema:

```php
$result = $input->process([
    'username' => [
        'trim',
        'required',
        function ($field, $value) {
            return str_contains((string) $value, ' ')
                ? 'Username cannot contain spaces.'
                : null;
        },
    ],
]);
```

Returning `null` means valid. Returning a non-empty string adds that string to the field's errors.

---

# 18. Custom transformations

Register reusable normalization behavior:

```php
$input->transform('phone', function ($value) {
    return preg_replace('/[^0-9+]/', '', (string) $value);
});
```

Then:

```php
'phone' => 'trim|phone|required'
```

Custom transformers receive the current value, parameters, field name and original raw input.

---

# 19. Database-aware rules without a hard dependency

Prefab Input deliberately does not require Prefab Database.

An application can register a rule backed by any storage system:

```php
$input->rule('unique_email', function ($field, $value) use ($users) {
    return $users->findByEmail((string) $value) === null
        ? null
        : 'That email address is already in use.';
});
```

Then:

```php
'email' => 'trim|lowercase|required|email|unique_email'
```

A future Prefab Database integration can supply standard `unique`/`exists` resolvers without changing the core Input API.

---

# 20. Routes / HTTP usage

Prefab Input does not depend on a router or `$_POST`.

Use it with any array source:

```php
$input = new Input($_POST);
```

JSON request body:

```php
$data = json_decode(file_get_contents('php://input'), true) ?? [];
$result = (new Input($data))->process($schema);
```

With Prefab Routes, a controller or middleware can process request data before business logic. Future Prefab HTTP integration can provide a cleaner request object while Input itself stays transport-independent.

---

# 21. Practical user-creation example

```php
$input = new Input($_POST);

$result = $input->process([
    'name' => 'trim|required|string|max:100',
    'email' => 'trim|lowercase|required|email|max:200',
    'password' => 'required|string|min:8|confirmed',
    'active' => 'default:true|boolean',
]);

if ($result->fails()) {
    renderForm($result->errors());
    return;
}

$user = $users->create($result->validated());
```

Unexpected form fields never reach `create()` through `validated()`.

---

# 22. Practical API example

```php
$payload = json_decode(file_get_contents('php://input'), true) ?? [];

$result = Input::from($payload)->process([
    'title' => 'trim|required|string|max:200',
    'priority' => 'default:0|integer|between:0,5',
    'published' => 'default:false|boolean',
    'tags' => 'sometimes|array|max:10',
]);

if ($result->fails()) {
    http_response_code(422);
    echo json_encode([
        'errors' => $result->errors(),
    ]);
    return;
}

$service->create($result->validated());
```

---

# 23. API quick reference

`Input`:

| API | Purpose |
|---|---|
| `new Input($data)` | Create an input processor |
| `Input::from($data)` | Static constructor |
| `data()` | Replace raw input |
| `process()` | Process a schema |
| `rule()` | Register a custom validation rule |
| `transform()` | Register a custom transformer |
| `attributes()` | Set friendly field names |
| `messages()` | Override validation messages |

`InputResult`:

| API | Purpose |
|---|---|
| `passes()` | Whether no validation errors exist |
| `fails()` | Whether validation errors exist |
| `raw()` | Original input |
| `all()` | Processed schema fields |
| `validated()` | Safe fields that passed processing |
| `errors()` | All field errors |
| `first()` | First error globally or for a field |
| `value()` | Read a processed field using dot notation |

---

# 24. Design philosophy

Prefab Input owns the boundary between raw external data and application-trusted data.

```text
Raw request/form/API data
          ↓
      Prefab Input
          ↓
trim / normalize / cast
          ↓
       validate
          ↓
      whitelist
          ↓
   validated() data
          ↓
business logic / persistence
```

A small application can use one schema and `validated()`. A larger application can add reusable custom rules, custom transformers, database-aware checks and request integrations without replacing the input-processing model.

The core principle is: **raw input is not application data until the application explicitly defines and validates what it accepts.**
