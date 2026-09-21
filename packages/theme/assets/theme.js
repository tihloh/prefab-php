(() => {
    'use strict';

    const node = document.getElementById('prefab-theme-config');
    if (!node) return;

    let config;
    try { config = JSON.parse(node.textContent || '{}'); }
    catch { return; }

    const root = document.documentElement;
    const mediaDark = window.matchMedia('(prefers-color-scheme: dark)');
    const storageKey = config.storageKey || 'prefab.theme';
    let state = { ...(config.current || {}) };

    const userAllows = setting => {
        const policy = config.user || {};
        if (!policy.enabled) return false;
        return Boolean(policy[setting] ?? policy['allow_' + setting] ?? false);
    };

    const modesFor = theme => Object.keys(config.themes?.[theme]?.modes || {});
    const supportsSystem = theme => {
        const modes = modesFor(theme);
        return modes.includes('light') && modes.includes('dark');
    };

    const normalizeHex = value => {
        value = String(value || '').trim().toLowerCase();

        if (/^#[0-9a-f]{6}$/.test(value)) return value;

        if (/^#[0-9a-f]{3}$/.test(value)) {
            return '#' + value.slice(1).split('').map(char => char + char).join('');
        }

        return null;
    };

    const normalizeAccent = value => {
        if (value === null || value === undefined || value === '') return null;

        value = String(value).trim().toLowerCase();
        if (value === 'default' || value === 'inherit') return null;
        if (config.accents?.[value]) return value;

        const hex = normalizeHex(value);
        return hex && config.customAccent ? hex : false;
    };

    const accentColor = accent => {
        if (!accent) return null;
        if (config.accents?.[accent]) return normalizeHex(config.accents[accent]);
        return normalizeHex(accent);
    };

    const accentContrast = color => {
        const hex = normalizeHex(color);
        if (!hex) return '#ffffff';

        const red = parseInt(hex.slice(1, 3), 16);
        const green = parseInt(hex.slice(3, 5), 16);
        const blue = parseInt(hex.slice(5, 7), 16);
        const luminance = ((red * 299) + (green * 587) + (blue * 114)) / 1000;

        return luminance >= 150 ? '#111111' : '#ffffff';
    };

    const actualMode = () => state.mode === 'system'
        ? (mediaDark.matches ? 'dark' : 'light')
        : state.mode;

    const syncBootstrap = () => {
        const mode = actualMode();
        if (mode === 'light' || mode === 'dark') root.dataset.bsTheme = mode;
    };

    const syncAccent = () => {
        let style = document.querySelector('[data-prefab-accent]');
        const color = accentColor(state.accent);

        if (!color) {
            delete root.dataset.accent;
            style?.remove();
            return;
        }

        root.dataset.accent = state.accent;

        if (!style) {
            style = document.createElement('style');
            style.dataset.prefabAccent = '';
            document.head.appendChild(style);
        }

        style.textContent = ':root{--pf-primary:' + color
            + ';--pf-primary-contrast:' + accentContrast(color) + '}';
    };

    const createThemeAsset = (value, mode = null, media = null) => {
        const inline = config.assetMode !== 'published';
        const node = document.createElement(inline ? 'style' : 'link');

        if (inline) {
            node.textContent = value || '';
        } else {
            node.rel = 'stylesheet';
            node.href = value || '';
        }

        if (mode !== null) node.dataset.prefabThemeMode = mode;
        else node.dataset.prefabThemeBase = '';

        if (media !== null) node.media = media;

        return node;
    };

    const syncThemeAssets = () => {
        const theme = config.themes?.[state.theme];
        if (!theme) return false;

        const base = document.querySelector('[data-prefab-theme-base]');
        if (theme.base) {
            const inline = config.assetMode !== 'published';
            const valid = base && (
                (inline && base.tagName === 'STYLE')
                || (!inline && base.tagName === 'LINK')
            );

            if (valid) {
                if (inline) base.textContent = theme.base;
                else base.href = theme.base;
            } else {
                base?.remove();
                document.head.appendChild(createThemeAsset(theme.base));
            }
        } else {
            base?.remove();
        }

        document
            .querySelectorAll('[data-prefab-theme-mode]')
            .forEach(node => node.remove());

        const modes = theme.modes || {};

        if (state.mode === 'system') {
            for (const mode of ['light', 'dark']) {
                if (!modes[mode]) continue;

                document.head.appendChild(createThemeAsset(
                    modes[mode],
                    mode,
                    '(prefers-color-scheme: ' + mode + ')'
                ));
            }
        } else if (modes[state.mode]) {
            document.head.appendChild(
                createThemeAsset(modes[state.mode], state.mode)
            );
        }

        return true;
    };

    const dispatch = source => {
        document.dispatchEvent(new CustomEvent('prefab:themechange', {
            detail: { ...state, actualMode: actualMode(), source }
        }));
    };

    const storedState = () => ({
        theme: state.theme,
        mode: state.mode,
        density: state.density,
        accent: state.accent ?? null
    });

    const storeLocal = () => {
        try {
            localStorage.setItem(storageKey, JSON.stringify(storedState()));
        } catch {}
    };

    const save = () => {
        if (!config.saveUrl) {
            storeLocal();
            return;
        }

        fetch(config.saveUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(storedState())
        })
            .then(response => {
                if (!response.ok) throw new Error('Theme preference save failed.');
            })
            .catch(storeLocal);
    };

    const apply = (source = 'api', persist = true) => {
        root.dataset.theme = state.theme;
        root.dataset.mode = state.mode;
        root.dataset.density = state.density;
        syncThemeAssets();
        syncAccent();
        syncBootstrap();
        dispatch(source);
        if (persist) save();
    };

    const api = {
        get: () => ({
            ...state,
            accentColor: accentColor(state.accent),
            actualMode: actualMode()
        }),

        setTheme(theme, persist = true) {
            theme = String(theme || '').toLowerCase();
            if (!config.themes?.[theme]) return false;

            state.theme = theme;
            const modes = modesFor(theme);

            if (state.mode === 'system' && !supportsSystem(theme)) {
                state.mode = modes[0] || 'light';
            } else if (state.mode !== 'system' && !modes.includes(state.mode)) {
                state.mode = modes[0] || 'light';
            }

            apply('theme', persist);
            return true;
        },

        setMode(mode, persist = true) {
            mode = String(mode || '').toLowerCase();
            const modes = modesFor(state.theme);

            if (mode === 'system' ? !supportsSystem(state.theme) : !modes.includes(mode)) {
                return false;
            }

            state.mode = mode;
            apply('mode', persist);
            return true;
        },

        setDensity(density, persist = true) {
            density = String(density || '').toLowerCase();
            if (!(config.densities || []).includes(density)) return false;

            state.density = density;
            apply('density', persist);
            return true;
        },

        setAccent(accent, persist = true) {
            const normalized = normalizeAccent(accent);
            if (normalized === false) return false;

            state.accent = normalized;
            apply('accent', persist);
            return true;
        },

        resetAccent(persist = true) {
            return this.setAccent(null, persist);
        },

        toggleMode(persist = true) {
            return this.setMode(actualMode() === 'dark' ? 'light' : 'dark', persist);
        },

        toggleDensity(persist = true) {
            const densities = config.densities || [];
            if (densities.length < 2) return false;

            const index = Math.max(0, densities.indexOf(state.density));
            return this.setDensity(densities[(index + 1) % densities.length], persist);
        }
    };

    const restoreLocal = () => {
        if (config.saveUrl) return;

        let saved;
        try { saved = JSON.parse(localStorage.getItem(storageKey) || 'null'); }
        catch { return; }

        if (!saved || typeof saved !== 'object') return;

        if (userAllows('theme') && config.themes?.[saved.theme]) {
            state.theme = String(saved.theme).toLowerCase();
        }

        if (userAllows('mode')) {
            const mode = String(saved.mode || '').toLowerCase();
            const modes = modesFor(state.theme);
            if (mode === 'system' ? supportsSystem(state.theme) : modes.includes(mode)) {
                state.mode = mode;
            }
        }

        if (
            userAllows('density')
            && (config.densities || []).includes(saved.density)
        ) {
            state.density = saved.density;
        }

        if (userAllows('accent') && Object.prototype.hasOwnProperty.call(saved, 'accent')) {
            const accent = normalizeAccent(saved.accent);
            if (accent !== false) state.accent = accent;
        }
    };

    const sidebarFor = control => {
        const selector = control?.dataset?.prefabSidebarTarget;

        if (selector) {
            try { return document.querySelector(selector); }
            catch { return null; }
        }

        return document.querySelector('.pf-sidebar');
    };

    const setSidebar = (sidebar, open, control = null) => {
        if (!sidebar) return;

        sidebar.classList.toggle('is-open', open);
        sidebar.dataset.open = open ? 'true' : 'false';

        if (control) {
            control.setAttribute('aria-expanded', open ? 'true' : 'false');
        }

        document.dispatchEvent(new CustomEvent('prefab:sidebarchange', {
            detail: { open, sidebar }
        }));
    };

    const runThemeControl = element => {
        if (!element) return false;

        if (element.hasAttribute('pf:theme') && userAllows('theme')) {
            const value = element.getAttribute('pf:theme') || element.value;
            return api.setTheme(value);
        }

        if (element.hasAttribute('pf:theme-mode') && userAllows('mode')) {
            const value = element.getAttribute('pf:theme-mode') || element.value;
            return value === 'toggle' ? api.toggleMode() : api.setMode(value);
        }

        if (element.hasAttribute('pf:theme-density') && userAllows('density')) {
            const value = element.getAttribute('pf:theme-density') || element.value;
            return value === 'toggle' ? api.toggleDensity() : api.setDensity(value);
        }

        if (element.hasAttribute('pf:theme-accent') && userAllows('accent')) {
            const value = element.getAttribute('pf:theme-accent') || element.value;
            return api.setAccent(value);
        }

        return false;
    };

    const injectFloatingToggle = () => {
        const toggle = config.toggle || {};
        if (!toggle.enabled || !userAllows('mode')) return;

        const button = document.createElement('button');
        const position = String(toggle.position || 'bottom-right')
            .replace(/[^a-z0-9-]/gi, '')
            .toLowerCase();

        button.type = 'button';
        button.className = 'pf-theme-toggle pf-theme-toggle-' + position;
        button.setAttribute('pf:theme-mode', 'toggle');
        button.setAttribute('aria-label', 'Toggle theme mode');
        button.setAttribute('title', 'Toggle theme mode');
        button.textContent = '◐';

        document.body.appendChild(button);
    };

    restoreLocal();
    window.PrefabTheme = api;
    apply('initial', false);

    mediaDark.addEventListener?.('change', () => {
        if (state.mode !== 'system') return;
        syncBootstrap();
        dispatch('system');
    });

    document.addEventListener('keydown', event => {
        if (event.key !== 'Escape') return;
        const sidebar = document.querySelector('.pf-sidebar.is-open');
        if (sidebar) setSidebar(sidebar, false);
    });

    document.addEventListener('click', event => {
        const target = event.target instanceof Element ? event.target : null;
        if (!target) return;

        const themeControl = target.closest(
            '[pf\\:theme], [pf\\:theme-mode], [pf\\:theme-density], [pf\\:theme-accent]'
        );

        if (
            themeControl
            && !(themeControl instanceof HTMLInputElement)
            && !(themeControl instanceof HTMLSelectElement)
        ) {
            event.preventDefault();
            runThemeControl(themeControl);
        }

        const sidebarToggle = target.closest('[data-prefab-sidebar-toggle]');
        const sidebarClose = target.closest('[data-prefab-sidebar-close]');

        if (sidebarToggle) {
            const sidebar = sidebarFor(sidebarToggle);
            setSidebar(
                sidebar,
                !sidebar?.classList.contains('is-open'),
                sidebarToggle
            );
        }

        if (sidebarClose) {
            setSidebar(sidebarFor(sidebarClose), false);
        }
    });

    document.addEventListener('change', event => {
        const target = event.target;
        if (!(target instanceof HTMLInputElement || target instanceof HTMLSelectElement)) {
            return;
        }

        if (
            target.matches(
                '[pf\\:theme], [pf\\:theme-mode], [pf\\:theme-density], [pf\\:theme-accent]'
            )
        ) {
            runThemeControl(target);
        }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', injectFloatingToggle, { once: true });
    } else {
        injectFloatingToggle();
    }
})();
