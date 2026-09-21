# Prefab Theme Showcase

Interactive demo of the current **Prefab Theme** surface.

It demonstrates:

- Theme initialization and lazy appearance resolution
- `pf:theme`, `pf:theme-mode` and `pf:theme-density`
- Light / Dark / System
- Comfortable / Compact density
- User preference persistence through the Theme browser runtime
- Optional floating mode toggle
- Bundled `default` theme plus an external `showcase` theme
- Responsive `pf-shell`, sidebar and topbar
- Page headers and actions
- Toolbars
- Panels and data panels
- Stats
- Semantic statuses
- Record headers
- Details
- Timeline
- Settings
- Empty states
- Bootstrap buttons, cards, forms, alerts, badges, progress, list groups, accordion, tabs, dropdowns, collapse, modal, offcanvas, toast and pagination
- Semantic theme token swatches
- `ThemeManager::explain()` diagnostics

## Run

```bash
cd examples/theme-showcase
composer update
php -S 127.0.0.1:8080
```

Open:

```text
http://127.0.0.1:8080
```

Bootstrap is loaded from jsDelivr for the demo. Prefab Theme itself is loaded from the local monorepo through Composer path repositories.

The demo intentionally enables user theme, mode and density changes. Since no `save_url` is configured, appearance changes are persisted in browser `localStorage`.

The custom sample theme lives in:

```text
themes/showcase/
├── theme.json
├── base.css
├── light.css
└── dark.css
```

It is discovered through:

```php
'themes_path' => __DIR__ . '/themes',
```

so it also demonstrates loading an installed theme outside the package itself.
