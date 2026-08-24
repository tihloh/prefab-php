# Prefab Files

**Prefab Files** provides framework-independent file storage, retrieval, organization, metadata and named-disk management for PHP applications.

> Input decides whether a file is acceptable. Files decides where and how it is stored.

Prefab Files is standalone. It does not require Prefab Input, Routes, Database, Auth, Permissions, Logs, Laravel, or another framework.

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
```

Read it:

```php
$content = $files->read('documents/report.txt');
```

Delete it:

```php
$files->delete('documents/report.txt');
```

---

# 2. Named disks

A larger application can configure several storage locations:

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

Or choose another disk:

```php
$files->put(
    'avatars/user-25.jpg',
    $contents,
    disk: 'public',
);
```

Inspect configured disks:

```php
$files->names();
$files->defaultName();
$files->hasDisk('public');
```

Change the default:

```php
$files->useDefault('public');
```

---

# 3. Local storage

The first built-in driver is `LocalDisk`.

```php
use Tihloh\Prefab\Files\LocalDisk;

$disk = new LocalDisk(
    __DIR__ . '/storage',
);
```

Or with a public base URL:

```php
$disk = new LocalDisk(
    __DIR__ . '/public/uploads',
    '/uploads',
);
```

The local driver creates its root when necessary.

---

# 4. Storage paths

Paths passed to Prefab Files are relative to the selected disk root.

```php
$files->put('documents/2026/report.pdf', $contents);
```

A private disk rooted at:

```text
/var/www/app/storage/private
```

stores that file at:

```text
/var/www/app/storage/private/documents/2026/report.pdf
```

Applications therefore do not need to concatenate absolute storage paths throughout business code.

---

# 5. Path safety

Local storage rejects path traversal.

These are rejected:

```text
../.env
../../config.php
documents/../../../secret.txt
```

Storage paths are normalized before filesystem operations, preventing callers from escaping the configured disk root through `..` segments.

This is an important safety boundary, but applications should still use authorization when deciding **who** may access a stored private file.

---

# 6. Atomic local writes

Local `put()` and `putStream()` write to a temporary file first and then rename it into place.

Conceptually:

```text
write temporary file
        ↓
write succeeds completely
        ↓
rename into final path
```

This reduces the chance of readers seeing a partially written local file.

---

# 7. Writing strings

```php
$info = $files->put(
    'exports/report.csv',
    $csv,
);
```

`put()` returns `FileInfo` for the stored file.

---

# 8. Writing streams

For larger content, streams avoid loading the entire file into one PHP string:

```php
$stream = fopen($sourcePath, 'rb');

try {
    $info = $files->putStream(
        'archives/backup.zip',
        $stream,
    );
} finally {
    fclose($stream);
}
```

---

# 9. Copying an existing local file into storage

```php
$info = $files->putFile(
    '/tmp/generated-report.pdf',
    'reports/2026/report.pdf',
);
```

The source file is read as a stream and copied into the selected storage disk.

---

# 10. Prefab Input upload integration

`prefab-files` does not require `prefab-input`, but it understands upload-like objects that expose `tmpPath()`.

With Prefab Input:

```php
$result = Input::fromRequest()->process([
    'document' => 'required|file|mimes:pdf|max_size:20mb',
]);

if ($result->fails()) {
    return $result->errors();
}

$upload = $result->validated('document');

$stored = $files->storeUploaded(
    $upload,
    'documents',
);
```

Prefab Files generates a random name by default rather than trusting the original client filename as the stored filename.

The original name is still available on the Input `UploadedFile` if the application wants to store it as business metadata.

---

# 11. Choosing the stored filename

Automatic random name:

```php
$stored = $files->storeUploaded(
    $upload,
    'documents',
);
```

Explicit application-controlled name:

```php
$stored = $files->storeUploaded(
    $upload,
    'documents',
    'document-25.pdf',
);
```

Generate a random name manually:

```php
$name = $files->uniqueName('pdf');
```

Example result:

```text
0d2f3de89af847a3f8e61aa5d3f558e4.pdf
```

---

# 12. Reading files

Read all contents:

```php
$content = $files->read('documents/report.txt');
```

For large files, use the disk stream API:

```php
$stream = $files->disk()->readStream('documents/video.mp4');
```

The caller owns the returned stream and should close it after use.

---

# 13. Existence and metadata

```php
if ($files->exists('documents/report.pdf')) {
    $info = $files->info('documents/report.pdf');
}
```

`FileInfo` exposes:

```php
$info->path();
$info->name();
$info->extension();
$info->size();
$info->mime();
$info->modifiedAt();
$info->url();
$info->toArray();
```

MIME information is detected from stored file contents through `fileinfo`, not merely from the filename extension.

---

# 14. Public URLs

Only a disk configured with a base URL produces public URLs.

```php
$files = new FileManager([
    'disks' => [
        'public' => [
            'driver' => 'local',
            'root' => __DIR__ . '/public/uploads',
            'url' => '/uploads',
        ],
    ],
]);
```

Then:

```php
$url = $files->url(
    'avatars/user.jpg',
    'public',
);
```

may return:

```text
/uploads/avatars/user.jpg
```

A private disk without `url` returns `null` from `url()`.

Prefab Files therefore does not accidentally claim that every stored file is publicly reachable.

---

# 15. Absolute local path

For integrations that genuinely need the physical path:

```php
$absolute = $files->path('documents/report.pdf');
```

This is useful for native programs, PDF libraries, antivirus scanners, image processors, or other local tools.

Prefer storage-relative paths in ordinary application data so the storage backend can change later.

---

# 16. Copy and move

```php
$copy = $files->copy(
    'documents/a.pdf',
    'archive/a.pdf',
);
```

Move/rename:

```php
$moved = $files->move(
    'incoming/a.pdf',
    'documents/a.pdf',
);
```

Both return metadata for the destination file.

---

# 17. Directories

Create a directory:

```php
$files->makeDirectory('documents/2026');
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

Recursive deletion should be used deliberately because it removes all child files/directories.

---

# 18. Listing files

List direct files:

```php
$list = $files->files('documents');
```

Recursive listing:

```php
$list = $files->files(
    'documents',
    recursive: true,
);
```

The result is an array of `FileInfo` objects rather than backend-specific filesystem entries.

---

# 19. Protected/private downloads

Private files should normally live outside the web server's public document root.

Example:

```text
project/
├── public/
│   └── index.php
└── storage/
    └── private/
        └── documents/
```

A route can authorize the request and then stream the file:

```php
$routes->get('/documents/{id}/download', 'DocumentController@download')
    ->auth()
    ->permission('documents.download');
```

The controller can resolve the stored relative path and use Prefab Files to read/stream it.

This keeps confidential files out of directly public directories.

---

# 20. Public files

Files intended to be directly served by Apache/Nginx may use a public disk rooted under the document root:

```text
public/uploads/
```

Prefab Files manages the contents; the web server still serves the actual HTTP static response efficiently.

---

# 21. Storage contract

The manager depends on `DiskInterface`, not `LocalDisk` specifically.

The contract includes operations for:

```text
put / putStream
read / readStream
exists / delete
copy / move
files
makeDirectory / deleteDirectory
info
path
url
```

This allows future storage adapters such as:

```text
S3-compatible object storage
SMB/network storage
SFTP
memory/testing disk
framework filesystem adapter
```

without changing application code that uses `FileManager`.

---

# 22. Custom disk

Implement `DiskInterface`:

```php
final class MyCloudDisk implements DiskInterface
{
    // Implement the storage operations.
}
```

Register it:

```php
$files = new FileManager();
$files->add('cloud', new MyCloudDisk());
$files->useDefault('cloud');
```

This keeps cloud-specific SDK dependencies outside the core package.

---

# 23. Responsibility boundary

Prefab Input and Prefab Files deliberately solve different problems:

```text
HTTP upload
    ↓
Prefab Input
├── normalize $_FILES
├── upload error
├── MIME validation
├── extension rules
├── size limits
├── image rules
└── whitelist
    ↓
validated UploadedFile
    ↓
Prefab Files
├── choose disk
├── choose stored path/name
├── write/stream
├── read
├── copy/move/delete
├── metadata
└── URL/path resolution
```

Files does not need to become an HTTP request parser, and Input does not need to become a storage filesystem.

---

# 24. Practical document upload

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
    disk: 'private',
);

$record = [
    'title' => $data['title'],
    'stored_path' => $stored->path(),
    'original_name' => $data['document']->name(),
    'mime' => $stored->mime(),
    'size' => $stored->size(),
];
```

Persist the storage-relative path (`stored_path`) rather than an absolute machine-specific path.

---

# 25. API quick reference

`FileManager`:

| API | Purpose |
|---|---|
| `add()` | Register a disk |
| `disk()` | Get a named/default disk |
| `names()` | List configured disk names |
| `hasDisk()` | Test for a configured disk |
| `useDefault()` | Change the default disk |
| `put()` | Store string contents |
| `putStream()` | Store a stream |
| `putFile()` | Copy an existing file into storage |
| `storeUploaded()` | Store an upload-like object |
| `read()` | Read file contents |
| `exists()` | Test whether a file exists |
| `delete()` | Delete a file |
| `copy()` | Copy a file |
| `move()` | Move/rename a file |
| `info()` | Read metadata |
| `path()` | Resolve backend/local path |
| `url()` | Resolve public URL when available |
| `files()` | List files |
| `makeDirectory()` | Create a directory |
| `deleteDirectory()` | Delete a directory |
| `uniqueName()` | Generate a random filename |

---

# 26. Design philosophy

Prefab Files starts with local storage but does not make local storage part of the application's business logic.

```text
Small application
      ↓
one local root

Application grows
      ↓
private + public disks

Application grows further
      ↓
custom/network/cloud disk
```

Application code continues using the same manager API.

The core principle is: **store files through a small storage contract, keep private/public intent explicit, and keep HTTP upload validation separate from persistence.**
