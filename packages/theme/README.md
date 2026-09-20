# Prefab Theme

**Prefab Theme** is a framework-independent theme layer for Bootstrap applications.

> One package. Install only the themes the application actually wants.

## Architecture

```text
Bootstrap
   ↓
Prefab Theme Core
   ↓
Installed theme
   ↓
Application policy
   ↓
User preference (only when allowed)
```

The package includes one small fallback theme. Additional themes are downloaded or uploaded later as theme ZIP files; they are not separate Composer packages.

## Installation

```bash
composer require tihloh/prefab-theme
```

Bootstrap stays an application dependency. Load Bootstrap first, then Prefab Theme.

## Configure

```php
use Tihloh\Prefab\Theme\ThemeManager;

$themes = new ThemeManager([
    'public_path' => __DIR__ . '/public/assets/prefab-theme',
    'asset_url' => '/assets/prefab-theme',
    'default' => 'default',
    'mode' => 'system',
    'density' => 'comfortable',
    'themes' => [
        'default' => ['modes' => ['light', 'dark']],
    ],
    'user' => [
        'enabled' => true,
        'theme' => false,
        'mode' => true,
        'density' => true,
    ],
]);
```

The `themes` setting means enabled for this application, not every theme that happens to be installed.

## Publish assets

Run this during installation or deployment, not on every request:

```php
$themes->publish();
```

This publishes the core CSS/JS and the bundled fallback theme outside `vendor/`, so downloaded themes are not removed by Composer updates.

## Render

```php
$appearance = $themes->resolve($userAppearance ?? []);
```

```php
<!doctype html>
<html <?= $themes->attributes($appearance) ?>>
<head>
    <link rel="stylesheet" href="/assets/bootstrap.min.css">
    <?= $themes->styles($appearance) ?>
</head>
<body>
    <!-- Normal Bootstrap markup -->

    <script src="/assets/bootstrap.bundle.min.js"></script>
    <?= $themes->scripts($appearance) ?>
</body>
</html>
```

Application markup remains normal Bootstrap:

```html
<div class="card">
    <div class="card-body">
        <input class="form-control" type="text">
        <button class="btn btn-primary">Save</button>
    </div>
</div>
```

Do not create theme-specific application classes such as `win11-card` or `dark-input`.

## User preferences

The application owns preference storage. Prefab Theme resolves those values against developer policy:

```php
$userAppearance = [
    'theme' => null,
    'mode' => 'dark',
    'density' => 'compact',
];

$appearance = $themes->resolve($userAppearance);
```

A missing/null user theme means inherit the application default. User values are ignored when the developer disables that setting.

## Client switching

Prefab Theme exposes `window.PrefabTheme`:

```js
PrefabTheme.setTheme('default');
PrefabTheme.setMode('dark');
PrefabTheme.setDensity('compact');
PrefabTheme.get();
```

Or use built-in data attributes without application JavaScript:

```html
<button data-prefab-mode="light">Light</button>
<button data-prefab-mode="dark">Dark</button>

<select data-prefab-density-select>
    <option value="comfortable">Comfortable</option>
    <option value="compact">Compact</option>
</select>
```

Those controls respect the developer's user policy.

If the application provides a persistence endpoint:

```php
'save_url' => '/account/appearance',
```

the browser runtime POSTs the current theme, mode and density after a change. The application remains responsible for authentication, validation and storage.

## Theme format

```text
win11/
├── theme.json
├── base.css
├── light.css
└── dark.css
```

Example manifest:

```json
{
  "id": "win11",
  "name": "Windows 11",
  "version": "1.0.0",
  "base": "base.css",
  "modes": {
    "light": "light.css",
    "dark": "dark.css"
  },
  "supports": {
    "density": true
  }
}
```

The theme CSS defines semantic Prefab tokens such as `--pf-bg`, `--pf-surface`, `--pf-text`, `--pf-border` and `--pf-primary`. Core maps them onto Bootstrap components.

## Install a downloaded theme

ZIP installation requires PHP `ext-zip`:

```php
$theme = $themes->installer()->installZip('/tmp/win11.zip');
$themes->refresh();
```

Explicit update:

```php
$themes->installer()->installZip('/tmp/win11-1.1.0.zip', replace: true);
```

Remove:

```php
$themes->installer()->uninstall('win11');
$themes->refresh();
```

The installer rejects executable/server-side files and unsafe archive paths. The built-in `default` theme cannot be replaced or removed by a downloaded archive.

## Diagnostics

```php
$themes->explain($userAppearance ?? []);
```

This reports effective appearance, installed themes, application-enabled themes, registry errors and user policy.

## Initial scope

- Bootstrap theme layer and semantic tokens
- Application theme/mode/density policy
- Optional user-level theme/mode/density
- Light / Dark / System
- Dynamic browser switching
- One bundled fallback theme
- External theme discovery
- ZIP theme installation outside `vendor/`

Remote theme catalog/download/update UI can be added on top of this installer without changing application markup or the resolver.
