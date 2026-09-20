# Prefab Theme

**Prefab Theme** is a framework-independent theme layer for Bootstrap applications.

> One package. Install only the themes the application actually wants.

## Architecture

```text
Prefab Core
   ↓
Bootstrap + Prefab Theme
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

Prefab Core is installed transitively. Bootstrap stays an application dependency. Load Bootstrap first, then Prefab Theme.

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
    'components' => [
        'admin' => true,
    ],
]);
```

The `themes` setting means enabled for this application, not every theme that happens to be installed.

### Prefab Core configuration

Theme also participates in normal Prefab configuration and runtime diagnostics:

```php
use Tihloh\Prefab\PrefabConfig;
use Tihloh\Prefab\Theme\ThemeManager;

PrefabConfig::set([
    'modules' => [
        'theme' => [
            'mode' => 'system',
            'components' => [
                'admin' => true,
            ],
        ],
    ],
]);

$themes = new ThemeManager([
    'public_path' => __DIR__ . '/public/assets/prefab-theme',
]);
```

Direct `ThemeManager` configuration wins over PrefabConfig. Theme registers itself as the `theme` module and provides the `theme_manager` capability through PrefabRuntime.

## Publish assets

Run this during installation or deployment, not on every request:

```php
$themes->publish();
```

This publishes the core CSS/JS and the bundled fallback theme outside `vendor/`, so downloaded themes are not removed by Composer updates.

## Render

Apply user appearance once per request when preferences are available:

```php
$themes->apply($userAppearance ?? []);
```

Then render without passing the appearance repeatedly:

```php
<!doctype html>
<html <?= $themes->attributes() ?>>
<head>
    <link rel="stylesheet" href="/assets/bootstrap.min.css">
    <?= $themes->styles() ?>
</head>
<body>
    <!-- Normal Bootstrap markup -->

    <script src="/assets/bootstrap.bundle.min.js"></script>
    <?= $themes->scripts() ?>
</body>
</html>
```

If no user appearance is supplied, `attributes()`, `styles()` or `scripts()` lazily resolve the application defaults automatically. `resolve()` remains available when an application explicitly needs a separate `ThemeAppearance` value.

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

$themes->apply($userAppearance);
```

A missing/null user theme means inherit the application default. User values are ignored when the developer disables that setting.

## Admin UI components

Enable the optional admin layer:

```php
'components' => [
    'admin' => true,
],
```

Bootstrap still owns buttons, forms, tables, modals, dropdowns and other standard controls. Prefab supplies reusable application-level structures that Bootstrap does not define.

Initial admin classes include:

```text
pf-shell / pf-workspace
pf-topbar
pf-sidebar / pf-nav
pf-main
pf-page / pf-page-header / pf-page-actions
pf-toolbar
pf-panel
pf-data-panel / pf-table-wrap / pf-data-footer
pf-stats / pf-stat
pf-status
pf-empty
pf-details
pf-timeline
pf-record-header
pf-settings
```

Example shell:

```html
<div class="pf-shell">
    <aside class="pf-sidebar">
        <div class="pf-sidebar-header">My App</div>

        <div class="pf-sidebar-body">
            <nav class="pf-nav">
                <a class="pf-nav-link active" href="#">Dashboard</a>
                <a class="pf-nav-link" href="#">Documents</a>
            </nav>
        </div>
    </aside>

    <div class="pf-workspace">
        <header class="pf-topbar">
            <div class="pf-topbar-start">Dashboard</div>
            <div class="pf-topbar-end">
                <button class="btn btn-primary">New</button>
            </div>
        </header>

        <main class="pf-main">
            <div class="pf-page">
                <div class="pf-page-header">
                    <div>
                        <h1 class="pf-page-title">Documents</h1>
                        <div class="pf-page-subtitle">Manage incoming documents</div>
                    </div>

                    <div class="pf-page-actions">
                        <button class="btn btn-primary">Add Document</button>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>
```

The shell deliberately uses normal flex behavior: if the sidebar or topbar is omitted from the HTML, the remaining content automatically uses the freed space. No special `no-sidebar` or `no-topbar` class is required.

Example status:

```html
<span class="pf-status pf-status-warning">Pending</span>
<span class="pf-status pf-status-success">Approved</span>
<span class="pf-status pf-status-danger">Rejected</span>
```

Example data toolbar:

```html
<div class="pf-toolbar">
    <div class="pf-toolbar-start">
        <input class="form-control" type="search" placeholder="Search">
    </div>

    <div class="pf-toolbar-end">
        <button class="btn btn-outline-secondary">Filter</button>
        <button class="btn btn-primary">Add</button>
    </div>
</div>
```

The admin layer is optional, uses semantic Prefab tokens, and automatically follows the active downloaded theme.

## Client switching

Prefab Theme exposes `window.PrefabTheme`:

```js
PrefabTheme.setTheme('default');
PrefabTheme.setMode('dark');
PrefabTheme.setDensity('compact');
PrefabTheme.toggleMode();
PrefabTheme.toggleDensity();
PrefabTheme.get();
```

User-facing controls can be added to any normal HTML or Bootstrap component with concise Theme-owned directives:

```html
<button pf:theme="win11">Windows 11</button>
<button pf:theme="default">Default</button>

<button pf:theme-mode="light">Light</button>
<button pf:theme-mode="dark">Dark</button>
<button pf:theme-mode="system">System</button>
<button pf:theme-mode="toggle">Toggle mode</button>

<button pf:theme-density="comfortable">Comfortable</button>
<button pf:theme-density="compact">Compact</button>
<button pf:theme-density="toggle">Toggle density</button>
```

Select controls are also supported:

```html
<select pf:theme-mode>
    <option value="light">Light</option>
    <option value="dark">Dark</option>
    <option value="system">System</option>
</select>
```

The global `pf:mode` attribute remains unclaimed. Theme owns only `pf:theme`, `pf:theme-mode` and `pf:theme-density`.

These controls respect the developer's user policy. A user-triggered change applies immediately and persists by default.

When the application provides a persistence endpoint:

```php
'save_url' => '/account/appearance',
```

the browser runtime POSTs the current theme, mode and density after a change. If the endpoint is unavailable or not configured, Prefab Theme falls back to `localStorage`.

Temporary previews can use the JavaScript API with persistence disabled:

```js
PrefabTheme.setTheme('win11', false);
PrefabTheme.setMode('dark', false);
```

## Floating mode toggle

A built-in floating Light/Dark toggle is optional and disabled by default:

```php
'toggle' => [
    'enabled' => true,
    'position' => 'bottom-right',
],
```

Supported positions are `bottom-right`, `bottom-left`, `top-right` and `top-left`. The toggle is only rendered when user mode changes are permitted by application policy.

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
- Prefab Core configuration/runtime integration
- Optional reusable admin application components

Remote theme catalog/download/update UI can be added on top of this installer without changing application markup or the resolver.
