# Prefab Image

**Prefab Image** provides fast, framework-independent image inspection, resizing, cropping, conversion and browser delivery for PHP applications.

> Process once. Store optimized variants. Serve static files directly whenever possible.

The package uses PHP GD directly and keeps the hot path small: no ORM, service container, reflection or automatic database work.

## Requirements

- PHP 8.1 or newer
- `ext-gd`
- `ext-exif` is optional for JPEG auto-orientation

## Installation

When published:

```bash
composer require tihloh/prefab-image
```

## Quick start

```php
use Tihloh\Prefab\Image\Image;

Image::open(__DIR__ . '/uploads/photo.jpg')
    ->autoOrient()
    ->resize(1200)
    ->format('webp')
    ->quality(82)
    ->save(__DIR__ . '/public/images/photo.webp');
```

`resize(1200)` preserves the aspect ratio and does not upscale by default.

## Resize inside a box

```php
Image::open($source)
    ->resize(1200, 800)
    ->save($target);
```

The image fits inside `1200x800` while preserving its aspect ratio.

## Cover / thumbnail

```php
Image::open($source)
    ->cover(300, 300)
    ->format('webp')
    ->save($target);
```

Shortcut:

```php
Image::open($source)
    ->thumbnail(300, 300)
    ->save($target);
```

`cover()` scales and center-crops to fill the requested dimensions. It does not upscale unless explicitly requested.

## Crop, rotate and flip

```php
Image::open($source)
    ->crop(100, 50, 500, 300)
    ->rotate(90)
    ->flip('horizontal')
    ->save($target);
```

Flip directions:

```text
horizontal
vertical
both
```

## Auto orientation

```php
Image::open($source)
    ->autoOrient()
    ->save($target);
```

When `ext-exif` is available, JPEG EXIF orientation is applied. Without EXIF support the call is a no-op.

## Formats and quality

```php
Image::open($source)
    ->format('webp')
    ->quality(82)
    ->save($target);
```

Supported formats depend on the installed GD build:

```text
JPEG
PNG
GIF
WebP
AVIF
```

Prefab checks encoder support before using optional formats such as WebP and AVIF.

## Save, encode or display

Save to a file:

```php
$image->save(__DIR__ . '/public/photo.webp');
```

Return encoded binary data:

```php
$bytes = $image->encode();
```

Display the PHP response directly as an image:

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Tihloh\Prefab\Image\Image;

Image::open(__DIR__ . '/storage/photo.jpg')
    ->resize(640)
    ->format('webp')
    ->quality(82)
    ->display();
```

Then the PHP endpoint can be used directly:

```html
<img src="/image.php" alt="Photo">
```

`display()` emits the image body and the correct `Content-Type`. It does not call `exit`, so request termination remains under application control.

## Browser cache headers

```php
Image::open($source)
    ->resize(640)
    ->format('webp')
    ->display([
        'max_age' => 86400,
    ]);
```

For versioned image URLs:

```php
$image->display([
    'max_age' => 31536000,
    'immutable' => true,
]);
```

## Cheap image inspection

When only dimensions or MIME information are needed, do not decode the full bitmap:

```php
$info = Image::inspect($path);

$info->width();
$info->height();
$info->ratio();
$info->mime();
$info->type();
$info->size();
```

This is useful for generating correct HTML dimensions:

```html
<img src="/images/photo.webp" width="640" height="480" loading="lazy" decoding="async">
```

## Cached derivatives

For frequently requested thumbnails and resized images, avoid decoding and processing the source on every request.

```php
Image::generate(
    __DIR__ . '/uploads/photo.jpg',
    __DIR__ . '/public/cache/photo-640.webp',
    fn (Image $image) => $image
        ->autoOrient()
        ->resize(640)
        ->format('webp')
        ->quality(82),
);
```

If the target exists and is at least as new as the source, `generate()` returns immediately **before decoding the source image**.

This is the preferred pattern for dynamic image endpoints that also maintain a disk cache.

## Fast delivery strategy

For public images, the fastest normal path is:

```text
Browser
  ↓
Nginx / Apache / CDN
  ↓
optimized static image
```

Avoid this for every page load when the result can be cached:

```text
Browser
  ↓
PHP
  ↓
GD decode
  ↓
resize
  ↓
encode
```

Use PHP processing when generating a derivative, serving protected images or producing a one-off response. Reuse the generated static derivative afterward whenever possible.

## Responsive variants

A practical upload workflow can generate several widths once:

```php
foreach ([320, 640, 1024, 1600] as $width) {
    Image::generate(
        $source,
        __DIR__ . "/public/images/photo-{$width}.webp",
        fn (Image $image) => $image
            ->autoOrient()
            ->resize($width)
            ->format('webp')
            ->quality(82),
    );
}
```

Then let the browser choose the smallest useful file:

```html
<img
    src="/images/photo-640.webp"
    srcset="
        /images/photo-320.webp 320w,
        /images/photo-640.webp 640w,
        /images/photo-1024.webp 1024w,
        /images/photo-1600.webp 1600w
    "
    sizes="(max-width: 640px) 100vw, 640px"
    loading="lazy"
    decoding="async"
    width="640"
    height="480"
>
```

## Performance design

The processing object is deliberately mutable. Fluent calls such as `quality()` and `format()` do not clone the decoded bitmap. Transformations replace the current GD image resource and release the previous one as soon as it is no longer needed.

Key rules:

```text
inspect without decoding when possible
never upscale unless requested
avoid hidden image copies
process derivatives once
cache before decoding
serve generated files directly
let the browser select responsive sizes
```

## API quick reference

| API | Purpose |
|---|---|
| `Image::open()` | Decode an image file |
| `Image::fromString()` | Decode image bytes |
| `Image::inspect()` | Read dimensions/MIME without full processing |
| `Image::generate()` | Generate a derivative only when stale/missing |
| `resize()` | Fit while preserving aspect ratio |
| `cover()` / `thumbnail()` | Fill and center-crop |
| `crop()` | Crop a specific rectangle |
| `rotate()` | Rotate image |
| `flip()` | Flip horizontally/vertically |
| `autoOrient()` | Apply JPEG EXIF orientation when available |
| `format()` | Choose output format |
| `quality()` | Choose output quality |
| `save()` | Atomically save to disk |
| `encode()` | Return encoded bytes |
| `display()` | Emit image HTTP response |
| `width()` / `height()` / `ratio()` | Read current dimensions |
| `mime()` | Read current output MIME |

## Scope

Prefab Image stays focused on image processing and delivery. It does not attempt to become a graphics editor.

Out of scope for the small core package:

```text
OCR
face recognition
AI enhancement
image generation
complex drawing/text layout
full photo filter suites
video processing
```

Those capabilities can be separate packages or adapters when an application needs them.
