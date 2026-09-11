# Prefab Files Visibility Model

Prefab Files should distinguish storage intent without becoming an authorization system.

## Visibility classes

### Public

Files intended to be directly reachable by a browser or CDN.

Typical storage:

```text
public/uploads/
public/assets/
object storage bucket/prefix with public delivery
```

Expected behavior:

- direct URL may be available
- web server/CDN may serve the file without PHP
- suitable for avatars, public images, downloadable public assets and generated derivatives

### Private

Files that must not be directly exposed by the storage layer.

Typical storage:

```text
storage/private/
private object-storage prefix/bucket
```

Expected behavior:

- no direct public URL by default
- application authorizes access first
- delivery uses a controlled download/stream or temporary signed URL
- suitable for documents, requirements, contracts, reports and other protected uploads

### Temporary

Short-lived working files and generated intermediate artifacts.

Typical storage:

```text
storage/tmp/
/tmp/prefab/
temporary object-storage prefix
```

Expected behavior:

- not public by default
- disposable
- supports expiry/cleanup policy
- suitable for imports, generated archives, upload staging, transformed images and transient exports

## Important boundary

Visibility is a **storage policy**, not an authorization rule.

```text
public   = storage may expose directly
private  = storage must not expose directly
temporary = storage is short-lived/disposable
```

Prefab Permissions or application policy still decides whether a user may access a private file.

## Recommended configuration

Visibility should be expressible through named disks:

```php
$files = new FileManager([
    'default' => 'private',
    'disks' => [
        'public' => [
            'driver' => 'local',
            'visibility' => 'public',
            'root' => __DIR__ . '/public/uploads',
            'url' => '/uploads',
        ],
        'private' => [
            'driver' => 'local',
            'visibility' => 'private',
            'root' => __DIR__ . '/storage/private',
        ],
        'temporary' => [
            'driver' => 'local',
            'visibility' => 'temporary',
            'root' => __DIR__ . '/storage/tmp',
            'ttl' => 86400,
        ],
    ],
]);
```

A disk name and its visibility are related but should not be treated as identical. An application may name disks `avatars`, `documents`, or `exports` while still declaring their visibility explicitly.

## API direction

Useful manager-level APIs can include:

```php
$files->visibility();
$files->visibility('avatars');
$files->isPublic('avatars');
$files->isPrivate('documents');
$files->isTemporary('exports');
```

For convenience, applications may later use intent-oriented accessors:

```php
$files->public();
$files->private();
$files->temporary();
```

These should resolve configured disks rather than create hidden storage roots.

## URL rules

A direct `url()` should only succeed for storage that explicitly supports public delivery.

Private and temporary storage should return `null` from direct public URL generation unless a driver explicitly provides a controlled temporary/presigned URL API.

## Temporary cleanup

Prefab Files may expose cleanup primitives such as:

```php
$files->pruneTemporary();
$files->pruneTemporary(olderThan: 86400);
```

Files should not silently run a scheduler. Recurring cleanup belongs to the host application or a future Prefab Scheduler/Queue integration.

## Migration direction

The current named-disk API remains valid. Visibility should be added as an optional disk capability/configuration value so existing installations continue working.

Recommended compatibility behavior:

- configured `visibility` wins
- a disk with a public base URL may infer `public` for legacy configuration
- a disk without a public URL defaults to `private`
- `temporary` must be explicit because it carries lifecycle semantics

This keeps the existing API stable while giving Files a clear long-term storage model.
