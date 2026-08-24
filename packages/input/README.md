# Prefab Input

**Prefab Input** turns raw/untrusted PHP input into clean, normalized, typed, validated and deeply whitelisted application data.

> Define exactly what your application accepts. Everything else stays outside the validated result.

It combines closely related input-boundary concerns:

- validation;
- trimming and normalization;
- type casting;
- safe field whitelisting;
- defaults and conditional rules;
- nested arrays and wildcard paths;
- multiple/repeated form rows;
- `$_FILES` normalization;
- multipart file validation;
- JSON request bodies;
- custom rules and transformers;
- friendly validation errors.

Prefab Input is standalone. It does not require Routes, Database, Users, Auth, Permissions, Logs, Laravel, or another framework.

## Requirements

- PHP 8.1 or newer
- `ext-fileinfo` for secure MIME detection
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

$result = Input::from($_POST)->process([
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

`validated()` contains only schema-declared fields that passed processing.

---

# 2. One input pipeline

Incoming values often need several steps before business logic:

```text
"  Christian  "   → "Christian"
"USER@MAIL.COM"   → "user@mail.com"
"25"              → 25
"true"            → true
""                → null
```

Prefab Input handles this as one pipeline:

```text
raw input
   ↓
whitelist/schema
   ↓
normalize
   ↓
cast
   ↓
validate
   ↓
safe validated data
```

---

# 3. Schema as a whitelist

Suppose a client sends:

```php
[
    'name' => 'Christian',
    'email' => 'user@example.com',
    'role' => 'admin',
    'is_superuser' => true,
]
```

but the schema declares only:

```php
$result = Input::from($data)->process([
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

Undeclared client fields never become validated application data.

---

# 4. Compact and array schemas

Compact syntax:

```php
'name' => 'trim|required|string|max:100'
```

Array syntax:

```php
'name' => [
    'trim',
    'required',
    'string',
    'max:100',
]
```

Array schemas can also contain callable validation rules.

---

# 5. Transformations and casting

Built-in operations include:

| Operation | Effect |
|---|---|
| `trim` | Trim string whitespace |
| `lowercase` | Lowercase a string |
| `uppercase` | Uppercase a string |
| `null_if_empty` | Convert `''` to `null` |
| `string` | Cast sensible scalar/stringable values |
| `integer` | Cast a valid integer representation |
| `float` | Cast numeric input to float |
| `boolean` | Cast common boolean forms |
| `array` | Keep arrays or wrap a scalar in an array |

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

Invalid casts produce validation errors rather than silently pretending the requested type was produced.

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
distinct
file
image
mimes
mimetypes
min_size
max_size
dimensions
```

---

# 7. Required, nullable, sometimes and defaults

```php
'name' => 'required|string'
'middle_name' => 'nullable|trim|string|max:100'
'bio' => 'sometimes|trim|string|max:500'
'active' => 'default:true|boolean'
'page' => 'default:1|integer|min:1'
```

`nullable` accepts an absent/null/empty value as `null`. `sometimes` ignores an absent field completely. `default` applies only when the field is absent.

---

# 8. Conditional validation

```php
'company_name' => 'required_if:type,business|trim|string'
'phone_extension' => 'required_with:phone|string'
```

These rules also work inside wildcard rows when the referenced rule path uses the same wildcard positions.

Example:

```php
'items.*.type' => 'required|in:product,service',
'items.*.sku' => 'required_if:items.*.type,product|string',
```

For `items.3.sku`, Prefab compares `items.3.type`.

---

# 9. Nested input

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

Validated output:

```php
[
    'user' => [
        'name' => 'Christian',
        'email' => 'user@example.com',
    ],
]
```

---

# 10. Array form inputs

PHP automatically converts HTML names such as:

```html
<input name="tags[]" value="PHP">
<input name="tags[]" value="Go">
```

into:

```php
[
    'tags' => ['PHP', 'Go'],
]
```

Process each item using `*`:

```php
$result = Input::from($_POST)->process([
    'tags' => 'required|array|max:10|distinct',
    'tags.*' => 'trim|required|string',
]);
```

---

# 11. Repeated/nested rows with wildcards

HTML:

```html
<input name="items[0][product_id]" value="10">
<input name="items[0][qty]" value="2">

<input name="items[1][product_id]" value="15">
<input name="items[1][qty]" value="3">
```

PHP produces:

```php
[
    'items' => [
        ['product_id' => '10', 'qty' => '2'],
        ['product_id' => '15', 'qty' => '3'],
    ],
]
```

Prefab schema:

```php
$result = Input::from($_POST)->process([
    'items' => 'required|array|max:20',
    'items.*.product_id' => 'required|integer',
    'items.*.qty' => 'required|integer|min:1',
]);
```

Validated output:

```php
[
    'items' => [
        ['product_id' => 10, 'qty' => 2],
        ['product_id' => 15, 'qty' => 3],
    ],
]
```

Errors keep their concrete index:

```php
[
    'items.1.qty' => [
        'The items 1 qty field must be at least 1.',
    ],
]
```

Nested wildcards are also supported:

```php
'departments.*.employees.*.email' => 'trim|lowercase|required|email'
```

---

# 12. Deep whitelisting

Wildcard schemas do not copy undeclared fields from parent arrays.

Input:

```php
[
    'items' => [
        [
            'product_id' => 10,
            'qty' => 2,
            'price' => 0,
            'is_free' => true,
        ],
    ],
]
```

Schema:

```php
[
    'items' => 'array',
    'items.*.product_id' => 'integer',
    'items.*.qty' => 'integer',
]
```

Validated output:

```php
[
    'items' => [
        [
            'product_id' => 10,
            'qty' => 2,
        ],
    ],
]
```

This makes nested client input safer to pass to application services or persistence.

---

# 13. Standard multipart forms

For normal PHP `multipart/form-data` requests, PHP already parses the protocol into `$_POST` and `$_FILES`.

Use:

```php
$input = Input::fromRequest();
```

or explicitly:

```php
$input = Input::from($_POST, $_FILES);
```

HTML:

```html
<form method="post" enctype="multipart/form-data">
    <input name="title">
    <input type="file" name="document">
    <button type="submit">Save</button>
</form>
```

Process it naturally:

```php
$result = Input::fromRequest()->process([
    'title' => 'trim|required|string|max:200',
    'document' => 'required|file|mimes:pdf|max_size:20mb',
]);
```

Prefab does not reimplement the standard PHP multipart boundary parser. It normalizes PHP's already-parsed request data.

---

# 14. UploadedFile

Uploaded files become `UploadedFile` objects:

```php
use Tihloh\Prefab\Input\UploadedFile;

$file = $result->validated('document');

if ($file instanceof UploadedFile) {
    $file->name();
    $file->tmpPath();
    $file->size();
    $file->extension();
    $file->mime();
    $file->error();
    $file->isValid();
    $file->isImage();
    $file->dimensions();
}
```

`mime()` detects MIME from temporary file contents using `fileinfo`; it does not simply trust the browser-provided MIME string.

`UploadedFile` represents temporary input only. Permanent storage is intentionally outside Prefab Input.

---

# 15. File rules

Examples:

```php
'photo' => 'required|file|image|max_size:5mb'
'document' => 'required|file|mimes:pdf,doc,docx|max_size:20mb'
'avatar' => 'image|mimetypes:image/jpeg,image/png'
```

Rules:

| Rule | Meaning |
|---|---|
| `file` | Valid PHP upload |
| `image` | Upload is a readable image |
| `mimes:pdf,png` | Allowed extension plus detected MIME for supported formats |
| `mimetypes:application/pdf,image/png` | Allowed detected MIME |
| `min_size:1kb` | Minimum byte size |
| `max_size:10mb` | Maximum byte size |
| `dimensions:min_width=200,min_height=200` | Image dimension limits |

Supported size suffixes are `b`, `kb`, `mb`, and `gb`.

Dimension examples:

```php
'photo' => 'image|dimensions:min_width=200,min_height=200,max_width=4000,max_height=4000'
```

---

# 16. Multiple file uploads

HTML:

```html
<input type="file" name="attachments[]" multiple>
```

Prefab normalizes PHP's column-oriented `$_FILES` representation into:

```php
[
    'attachments' => [
        UploadedFile,
        UploadedFile,
    ],
]
```

Then:

```php
$result = Input::fromRequest()->process([
    'attachments' => 'array|max:10',
    'attachments.*' => 'file|max_size:10mb',
]);
```

---

# 17. Nested multipart rows

HTML can mix ordinary fields and files:

```html
<input name="employees[0][name]">
<input type="file" name="employees[0][photo]">
<input name="employees[1][name]">
<input type="file" name="employees[1][photo]">
```

Schema:

```php
$result = Input::fromRequest()->process([
    'employees' => 'required|array',
    'employees.*.name' => 'trim|required|string|max:100',
    'employees.*.photo' => 'nullable|image|max_size:5mb',
]);
```

`$_POST` and `$_FILES` are merged into one logical nested input tree before validation.

---

# 18. JSON requests

`Input::fromRequest()` detects `Content-Type: application/json` and reads `php://input` automatically:

```php
$result = Input::fromRequest()->process([
    'title' => 'trim|required|string|max:200',
    'priority' => 'default:0|integer|between:0,5',
    'tags' => 'sometimes|array|max:10',
    'tags.*' => 'trim|string',
]);
```

Invalid JSON produces a clear exception instead of silently converting the request into empty data.

Raw multipart parsing for unusual non-standard request flows can later belong to an HTTP transport layer; Prefab Input remains focused on input normalization and validation.

---

# 19. Result object

`process()` returns `InputResult`:

```php
$result->passes();
$result->fails();
$result->raw();
$result->all();
$result->validated();
$result->validated('document');
$result->errors();
$result->first();
$result->first('email');
$result->value('user.email');
```

Meaning:

```text
raw()       → original normalized request tree before schema processing
all()       → declared fields after transformations, including invalid values
validated() → only declared fields that passed processing
errors()    → field-indexed validation messages
```

Prefer `validated()` when passing request data into application services.

---

# 20. Friendly attributes and messages

```php
$input->attributes([
    'email_address' => 'Email address',
    'items.*.qty' => 'Item quantity',
]);

$input->messages([
    'email.required' => 'Please enter your email address.',
    'items.*.qty.min' => ':attribute must be at least :value.',
]);
```

Wildcard pattern messages/attributes apply to their concrete paths unless an even more specific concrete key is configured.

---

# 21. Custom rules

```php
$input->rule('even', function ($field, $value) {
    return is_int($value) && $value % 2 === 0
        ? null
        : 'The value must be an even integer.';
});

$result = $input->process([
    'quantity' => 'integer|even',
]);
```

Custom rule callbacks receive field, current value, raw data, parsed parameters and existence state.

This is also the extension point for database-aware `unique`/`exists` behavior without making Prefab Database mandatory.

---

# 22. Inline callable rules

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

Return `null` when valid, or a non-empty error string when invalid.

---

# 23. Custom transformations

```php
$input->transform('phone', function ($value) {
    return preg_replace('/[^0-9+]/', '', (string) $value);
});

$result = $input->process([
    'phone' => 'trim|phone|required',
]);
```

Custom transformers receive the current value, parameters, field name and raw data.

---

# 24. Database-aware rules without a hard dependency

```php
$input->rule('unique_email', function ($field, $value) use ($users) {
    return $users->findByEmail((string) $value) === null
        ? null
        : 'That email address is already in use.';
});

$result = $input->process([
    'email' => 'trim|lowercase|required|email|unique_email',
]);
```

A future Database adapter may provide reusable resolvers while the Input core stays database-independent.

---

# 25. Responsibility boundary

Prefab Input owns the incoming-data boundary:

```text
Browser / API
      ↓
POST / JSON / multipart
      ↓
Prefab Input
├── normalize
├── cast
├── validate
├── file inspection
└── whitelist
      ↓
application-safe data
```

It deliberately does **not** own permanent file storage, directories, cloud storage, public URLs, database persistence, HTTP responses, or routing.

A future file-storage package could consume `UploadedFile` without changing Input itself.

---

# 26. API quick reference

| API | Purpose |
|---|---|
| `Input::from($data, $files = [])` | Build from explicit arrays |
| `Input::fromRequest()` | Read normal PHP form/multipart or JSON request data |
| `process()` | Normalize/cast/validate/whitelist using a schema |
| `rule()` | Register a custom validation rule |
| `transform()` | Register a custom transformer |
| `attributes()` | Configure friendly field labels |
| `messages()` | Configure validation messages |
| `Input::normalizeFiles()` | Normalize PHP `$_FILES` manually |

`InputResult`:

| API | Purpose |
|---|---|
| `passes()` / `fails()` | Validation status |
| `raw()` | Original input tree |
| `all()` | Processed declared fields |
| `validated()` | Safe validated output or one validated path |
| `errors()` | Error bag |
| `first()` | First error globally or for a field |
| `value()` | Read a processed value by dot path |

`UploadedFile`:

| API | Purpose |
|---|---|
| `name()` | Original client filename |
| `tmpPath()` | PHP temporary path |
| `size()` | File size in bytes |
| `extension()` | Original filename extension |
| `mime()` | Content-detected MIME |
| `error()` | PHP upload error code |
| `isValid()` | Valid temporary upload state |
| `isImage()` | Detect readable image |
| `dimensions()` | Image width/height |

---

# 27. Design philosophy

Prefab Input stays simple for ordinary forms:

```php
$result = Input::from($_POST)->process([
    'name' => 'trim|required',
]);
```

The same API scales to large dynamic forms and APIs:

```php
$result = Input::fromRequest()->process([
    'departments.*.employees.*.email' => 'trim|lowercase|required|email',
    'departments.*.employees.*.photo' => 'nullable|image|max_size:5mb',
]);
```

The core principle is: **turn untrusted external data into predictable application data without forcing a framework or persistence layer.**
