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
- Bootstrap component integration
- Semantic theme token swatches
- `ThemeManager::explain()` diagnostics

## Run from the monorepo

```bash
cd packages/theme/examples/theme-showcase
composer update
php -S 127.0.0.1:8080
```

## Run from the standalone prefab-theme repo

```bash
cd examples/theme-showcase
composer update
php -S 127.0.0.1:8080
```

Open:

```text
http://127.0.0.1:8080
```

Bootstrap is loaded from jsDelivr. The demo enables user theme, mode and density changes. With no `save_url`, appearance changes persist in browser `localStorage`.

The custom sample theme lives in:

```text
themes/showcase/
├── theme.json
├── base.css
├── light.css
└── dark.css
```
