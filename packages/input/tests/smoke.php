<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Tihloh\Prefab\Input\Input;
use Tihloh\Prefab\Input\UploadedFile;

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

// Scalar normalization, casting and top-level whitelist.
$result = Input::from([
    'name' => '  Chris  ',
    'email' => 'USER@EXAMPLE.COM',
    'age' => '35',
    'active' => 'true',
    'admin' => 1,
])->process([
    'name' => 'trim|required|string|max:20',
    'email' => 'trim|lowercase|required|email',
    'age' => 'integer|min:18',
    'active' => 'boolean',
]);

assertTrue($result->passes(), 'normal scalar input should pass');
assertTrue($result->validated() === [
    'name' => 'Chris',
    'email' => 'user@example.com',
    'age' => 35,
    'active' => true,
], 'scalar output should be normalized and whitelisted');

// Wildcard arrays, per-item casts and deep whitelist.
$result = Input::from([
    'items' => [
        ['product_id' => '10', 'qty' => '2', 'price' => '0', 'is_free' => true],
        ['product_id' => '15', 'qty' => '3', 'admin_only' => 'secret'],
    ],
])->process([
    'items' => 'required|array|max:10',
    'items.*.product_id' => 'required|integer',
    'items.*.qty' => 'required|integer|min:1',
]);

assertTrue($result->passes(), 'wildcard item input should pass');
assertTrue($result->validated() === [
    'items' => [
        ['product_id' => 10, 'qty' => 2],
        ['product_id' => 15, 'qty' => 3],
    ],
], 'wildcard output should deeply whitelist item fields');

// Wildcard errors preserve concrete indexes.
$result = Input::from([
    'items' => [
        ['qty' => '2'],
        ['qty' => '0'],
    ],
])->process([
    'items' => 'required|array',
    'items.*.qty' => 'required|integer|min:1',
]);

assertTrue($result->fails(), 'invalid wildcard item should fail');
assertTrue(isset($result->errors()['items.1.qty']), 'wildcard error should contain concrete item index');

// PHP $_FILES normalization and UploadedFile validation.
$tmp = tempnam(sys_get_temp_dir(), 'prefab_input_');
file_put_contents($tmp, "hello prefab\n");

$files = [
    'attachments' => [
        'name' => ['note.txt'],
        'type' => ['text/plain'],
        'tmp_name' => [$tmp],
        'error' => [UPLOAD_ERR_OK],
        'size' => [filesize($tmp)],
    ],
];

$result = Input::from([], $files)->process([
    'attachments' => 'required|array|max:3',
    'attachments.*' => 'required|file|mimes:txt|max_size:10kb',
]);

assertTrue($result->passes(), 'normalized uploaded file should pass file rules');
$file = $result->validated('attachments.0');
assertTrue($file instanceof UploadedFile, 'validated file should be an UploadedFile');
assertTrue($file->name() === 'note.txt', 'UploadedFile should preserve original name');

@unlink($tmp);

echo "Prefab Input smoke tests OK\n";
