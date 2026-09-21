# Prefab Theme Showcase

Interactive demo of the current **Prefab Theme** surface.

It demonstrates:

- Theme initialization and lazy appearance resolution
- `pf:theme`, `pf:theme-mode` and `pf:theme-density`
- Light / Dark / System
- Comfortable / Compact density
- User preference persistence through the Theme browser runtime
- Optional floating mode toggle
- Bundled `default` fallback plus `showcase`, `win11`, `vscode`, `minimal`, `ubuntu`, `macos` and `android` demo themes
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

The optional demo themes live under:

```text
themes/
├── showcase/
├── win11/
├── vscode/
└── minimal/
```

Each theme is a normal Prefab Theme directory with `theme.json`, `base.css`, `light.css` and `dark.css`. They are intentionally kept in the showcase instead of the package's built-in `themes/` directory, so the Theme engine itself still ships only the `default` fallback theme.

## Visual identities

The showcase presets intentionally change more than color:

- **Windows 11** — floating acrylic-like chrome, rounded surfaces, Mica-style background treatment and inset active navigation.
- **VS Code** — compact square controls, editor-like navigation, dense tables, monospace metadata and flat panels.
- **Minimal** — editorial spacing, borderless/flat surfaces, underline navigation, restrained controls and wide whitespace.
- **Ubuntu** — dark aubergine navigation rail, orange active states, strong headers and angular status/timeline details.
- **macOS** — floating translucent window treatment, glass surfaces, rounded controls and desktop-style window dots.
- **Android** — Material-inspired tonal surfaces, large rounded cards, pill navigation/buttons, filled controls and larger touch targets.

All of these differences are implemented by theme CSS over the same Bootstrap + Prefab markup.
