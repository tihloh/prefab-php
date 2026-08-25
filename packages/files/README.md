# Prefab Files

**Prefab Files** provides framework-independent file storage, retrieval, organization, metadata and named-disk management for PHP applications.

> Input decides whether a file is acceptable. Files decides where and how it is stored.

Prefab Files is standalone. Small applications can configure one local root and immediately call `put()`/`read()`. Larger systems can add named disks, public URLs, collision policies, checksums, storage usage, lifecycle hooks, temporary signed URLs and custom storage drivers without replacing the basic API.

## Requirements

- PHP 8.1 or newer
- `ext-fileinfo`
- Composer when installed as a package

## Installation

When published:

```bash
composer require tihloh/prefab-files
```

---

# 1. Quick start

```php
use Tihloh\Prefab\Files\FileManager;

$files = new FileManager([
    'root' => __DIR__ . '/storage',
]);

$info = $files->put(
    'documents/report.txt',
    'Hello Prefab',
);

$content = $files->read('documents/report.txt');
```

Nothing else is required for a small application.

---

# 2. Named disks

```php
$files = new FileManager([
    'default' => 'private',
    'disks' => [
        'private' => [
            'driver' => 'local',
            'root' => __DIR__ . '/storage/private',
        ],
        'public' => [
            'driver' => 'local',
            'root' => __DIR__ . '/public/uploads',
            'url' => '/uploads',
        ],
    ],
]);
```

Use the default disk:

```php
$files->put('documents/a.pdf', $contents);
```

Choose another disk:

```php
$files->put('avatars/user.jpg', $contents, 'public');
```

Inspect disks:

```php
$files->names();
$files->defaultName();
$files->hasDisk('public');
$files->useDefault('public');
```

Public/private intent is deliberately easiest to express at **disk level**. A disk with a configured base URL can produce public URLs; a private disk without one cannot.

---

# 3. Safe relative paths

Application paths are relative to the selected disk root:

```php
$files->put('documents/2026/report.pdf', $contents);
```

If the disk root is:

```text
/var/www/app/storage/private
```

the physical path is:

```text
/var/www/app/storage/private/documents/2026/report.pdf
```

Traversal attempts are rejected:

```text
../.env
../../config.php
documents/../../../secret.txt
```

Applications should normally persist the **storage-relative path**, not the machine-specific absolute path.

---

# 4. Atomic local writes

`LocalDisk` writes string and stream content to a temporary file first and then renames it into place.

```text
write temporary file
        ↓
complete successfully
        ↓
rename to final path
```

This reduces the chance of readers observing a partially written local file.

---

# 5. Strings, streams and existing files

String contents:

```php
$files->put('exports/report.csv', $csv);
```

Stream:

```php
$stream = fopen($sourcePath, 'rb');

try {
    $files->putStream('archives/backup.zip', $stream);
} finally {
    fclose($stream);
}
```

Existing local file:

```php
$files->putFile(
    '/tmp/generated-report.pdf',
    'reports/2026/report.pdf',
);
```

For large content, prefer streams.

---

# 6. Prefab Input integration

`prefab-files` does not depend on `prefab-input`, but `storeUploaded()` accepts an upload-like object exposing `tmpPath()`.

```php
$result = Input::fromRequest()->process([
    'document' => 'required|file|mimes:pdf|max_size:20mb',
]);

if ($result->fails()) {
    return $result->errors();
}

$stored = $files->storeUploaded(
    $result->validated('document'),
    'documents',
);
```

The responsibility split is intentional:

```text
HTTP upload
    ↓
Prefab Input
├── normalize
├── MIME/extension validation
├── size/image rules
└── whitelist
    ↓
validated UploadedFile
    ↓
Prefab Files
├── choose disk
├── choose stored path/name
├── store/stream
├── retrieve
└── organize
```

Prefab Files generates a random filename by default instead of trusting the original browser-supplied filename.

---

# 7. Filename collision policies

The default behavior is `overwrite`, preserving the simple historical API.

```php
$files->put('reports/report.pdf', $data);
```

Choose a policy when needed:

```php
$files->put('reports/report.pdf', $data, [
    'collision' => 'error',
]);
```

Supported policies:

| Policy | Behavior |
|---|---|
| `overwrite` | Replace the existing destination |
| `error` | Throw if the destination already exists |
| `skip` | Keep the existing file and return its metadata |
| `rename` | Generate `name-1.ext`, `name-2.ext`, etc. |

Example:

```php
$stored = $files->put('reports/report.pdf', $data, [
    'collision' => 'rename',
]);
```

If `report.pdf` already exists, the returned path may be:

```text
reports/report-1.pdf
```

The same collision option is available for stream writes, `putFile()`, uploads, copy and move operations.

---

# 8. Reading and streams

Read the entire file:

```php
$content = $files->read('documents/report.txt');
```

Read as a stream:

```php
$stream = $files->readStream('documents/video.mp4');

try {
    // consume stream
} finally {
    fclose($stream);
}
```

The caller owns returned streams.

---

# 9. File metadata

```php
$info = $files->info('documents/report.pdf');
```

`FileInfo` exposes:

```php
$info->path();
$info->name();
$info->filename();
$info->directory();
$info->extension();
$info->size();
$info->mime();
$info->modifiedAt();
$info->url();
$info->checksum();
$info->toArray();
```

MIME type is detected from the stored contents using `fileinfo`, not only from the extension.

---

# 10. Checksums

```php
$sha256 = $files->checksum('documents/report.pdf');
```

Choose another PHP-supported hash algorithm:

```php
$sha512 = $files->checksum(
    'documents/report.pdf',
    'sha512',
);
```

Checksums are useful for transfer verification, corruption checks, duplicate detection and change detection.

---

# 11. File and directory size

File size:

```php
$bytes = $files->size('documents/report.pdf');
```

Directory usage:

```php
$bytes = $files->directorySize('documents');
```

Entire selected disk:

```php
$bytes = $files->usage();
```

Prefab exposes measurements but deliberately does not turn Files into a billing/quota system. Applications can implement quotas using these values when needed.

---

# 12. Public URLs

Only disks configured with `url` produce a direct public URL:

```php
$url = $files->url(
    'avatars/user.jpg',
    'public',
);
```

Result:

```text
/uploads/avatars/user.jpg
```

A private disk returns `null` from `url()`.

This keeps private/public intent explicit without maintaining fragile per-file visibility state in the core package.

---

# 13. Temporary signed URLs

For local private files, Prefab can generate an **application URL** containing an expiry and HMAC signature.

Configure once:

```php
$files = new FileManager([
    'default' => 'private',
    'temporary_url' => '/files/temp',
    'signing_key' => $_ENV['FILES_SIGNING_KEY'],
    'disks' => [
        'private' => [
            'driver' => 'local',
            'root' => __DIR__ . '/storage/private',
        ],
    ],
]);
```

Generate a 10-minute URL:

```php
$url = $files->temporaryUrl(
    'documents/private.pdf',
    600,
);
```

A route/controller verifies the query parameters:

```php
$valid = $files->verifyTemporaryUrl(
    $_GET['path'] ?? '',
    (int) ($_GET['expires'] ?? 0),
    $_GET['signature'] ?? '',
    $_GET['disk'] ?? null,
);
```

If valid, the application may stream the file. Files signs/verifies the token but intentionally does **not** bypass Auth or Permissions. Authorization policy remains the application's responsibility.

Future object-storage drivers may implement their own native presigned URL behavior through an adapter without changing application-level storage paths.

---

# 14. Transport-neutral downloads

```php
$download = $files->download(
    'documents/report.pdf',
    'Annual Report 2026.pdf',
);
```

The returned `FileDownload` exposes:

```php
$download->stream();
$download->filename();
$download->size();
$download->mime();
$download->headers();
```

Prefab Files does **not** automatically call `header()` or terminate the request. The host application, Prefab HTTP, another framework or a controller decides how to emit the response.

Inline delivery is available:

```php
$download = $files->download(
    'documents/report.pdf',
    inline: true,
);
```

---

# 15. Copy, move and delete

```php
$files->copy(
    'documents/a.pdf',
    'archive/a.pdf',
);
```

```php
$files->move(
    'incoming/a.pdf',
    'documents/a.pdf',
);
```

```php
$files->delete('documents/a.pdf');
```

Copy/move support the same collision policies as writes.

---

# 16. Directories

Create:

```php
$files->makeDirectory('documents/2026');
```

Check:

```php
$files->directoryExists('documents/2026');
```

List immediate directories:

```php
$directories = $files->directories('documents');
```

List recursively:

```php
$directories = $files->directories(
    'documents',
    recursive: true,
);
```

Delete an empty directory:

```php
$files->deleteDirectory('documents/old');
```

Delete recursively:

```php
$files->deleteDirectory(
    'temporary/import-123',
    recursive: true,
);
```

---

# 17. Listing files

Direct files:

```php
$list = $files->files('documents');
```

Recursive:

```php
$list = $files->files(
    'documents',
    recursive: true,
);
```

The result is an array of `FileInfo` objects rather than backend-specific filesystem entries.

---

# 18. Driver capability detection

Storage backends do not all behave identically. Instead of pretending they do, adapters advertise optional capabilities:

```php
$files->supports('checksum');
$files->supports('atomic_write');
$files->supports('local_path');
$files->supports('public_url', 'public');
```

For the built-in local driver:

```text
atomic_write  ✓
checksum      ✓
local_path    ✓
directories   ✓
usage         ✓
public_url    ✓ only when a base URL is configured
```

A future S3-compatible driver may support native temporary URLs but not a local filesystem path.

---

# 19. Lifecycle hooks

Applications can observe file operations without making Files depend on Logs, Events or another module.

```php
$files->on('stored', function ($file, $disk, $context) {
    // audit, metrics, indexing, etc.
});
```

Available manager events include:

```text
stored
deleted
copied
moved
```

A future Prefab Events or Logs adapter can subscribe to these hooks while Files remains standalone.

Hooks are synchronous and lightweight. Heavy background processing belongs in a jobs/events layer rather than the storage core.

---

# 20. Protected/private files

Private files should normally live outside the web server document root:

```text
project/
├── public/
│   └── index.php
└── storage/
    └── private/
        └── documents/
```

A protected route can authorize and then create a download descriptor:

```php
$routes->get('/documents/{id}/download', 'DocumentController@download')
    ->auth()
    ->permission('documents.download');
```

Then the controller resolves the stored path and uses:

```php
$download = $files->download($storedPath);
```

Confidential files should not depend on obscure filenames for protection.

---

# 21. Custom storage drivers

`FileManager` depends on `DiskInterface`, not `LocalDisk` specifically.

The contract includes:

```text
put / putStream
read / readStream
exists / delete
copy / move
files / directories
makeDirectory / deleteDirectory
info / checksum / size / directorySize
path / url
supports
```

Possible adapters include:

```text
S3-compatible object storage
MinIO / Cloudflare R2 style adapters
SMB/network storage
SFTP
memory/testing storage
framework filesystem adapters
```

A custom disk can be registered directly:

```php
$files->add('cloud', new MyCloudDisk());
$files->useDefault('cloud');
```

Cloud/network SDKs remain outside the small core package until an adapter explicitly needs them.

---

# 22. What Prefab Files deliberately does not do

To preserve the Prefab goal, Files stays focused on storage infrastructure.

It does **not** own:

```text
upload validation        → Prefab Input
image resize/crop        → separate image capability
PDF generation           → separate PDF capability
spreadsheet generation   → separate spreadsheet capability
virus scanning           → adapter/hook integration
document workflows       → application/domain module
authentication           → Prefab Auth
authorization policy     → Prefab Permissions
database document model  → application/domain data
```

This prevents `prefab-files` from becoming a mini framework.

---

# 23. Practical document upload

```php
$inputResult = Input::fromRequest()->process([
    'title' => 'trim|required|string|max:200',
    'document' => 'required|file|mimes:pdf|max_size:20mb',
]);

if ($inputResult->fails()) {
    return $inputResult->errors();
}

$data = $inputResult->validated();

$stored = $files->storeUploaded(
    $data['document'],
    'documents/' . date('Y'),
    diskOrOptions: 'private',
);

$record = [
    'title' => $data['title'],
    'stored_path' => $stored->path(),
    'original_name' => $data['document']->name(),
    'mime' => $stored->mime(),
    'size' => $stored->size(),
    'sha256' => $files->checksum($stored->path()),
];
```

Persist the relative path and business metadata in the application's own database model.

---

# 24. API quick reference

| API | Purpose |
|---|---|
| `add()` / `disk()` | Register or obtain a storage disk |
| `names()` / `hasDisk()` | Inspect configured disks |
| `useDefault()` | Change the default disk |
| `put()` / `putStream()` | Store contents |
| `putFile()` | Copy an existing local file into storage |
| `storeUploaded()` | Store an upload-like object |
| `read()` / `readStream()` | Retrieve contents |
| `download()` | Create a transport-neutral download descriptor |
| `exists()` / `delete()` | Check/remove a file |
| `copy()` / `move()` | Copy or move a file |
| `info()` | Read file metadata |
| `checksum()` | Hash stored contents |
| `size()` | Return one file's size |
| `directorySize()` | Sum a directory tree |
| `usage()` | Sum the selected disk |
| `path()` | Resolve backend/local path when supported |
| `url()` | Resolve a direct public URL when configured |
| `temporaryUrl()` | Generate an expiring signed application URL |
| `verifyTemporaryUrl()` | Verify expiry/signature/existence |
| `files()` | List files |
| `directories()` | List directories |
| `directoryExists()` | Check a directory |
| `makeDirectory()` / `deleteDirectory()` | Manage directories |
| `supports()` | Query backend capabilities |
| `on()` | Register lifecycle hooks |
| `uniqueName()` | Generate a cryptographically random filename |

---

# 25. Design philosophy

```text
Small application
      ↓
one local root
      ↓
put / read

Application grows
      ↓
private + public disks
      ↓
collision policies + checksums + downloads

Application grows further
      ↓
temporary URLs + hooks + usage
      ↓
custom/network/cloud driver
```

The simple API remains valid throughout:

```php
$files->put('report.txt', 'Hello');
```

The core principle is: **keep storage simple for small applications, expose the abstractions large systems actually need, and keep validation, authorization and specialized file processing outside the storage core.**
