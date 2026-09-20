(() => {
    'use strict';

    const node = document.getElementById('prefab-theme-config');
    if (!node) return;

    let config;
    try { config = JSON.parse(node.textContent || '{}'); }
    catch { return; }

    const root = document.documentElement;
    const mediaDark = window.matchMedia('(prefers-color-scheme: dark)');
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

    const actualMode = () => state.mode === 'system'
        ? (mediaDark.matches ? 'dark' : 'light')
        : state.mode;

    const syncBootstrap = () => {
        const mode = actualMode();
        if (mode === 'light' || mode === 'dark') root.dataset.bsTheme = mode;
    };

    const syncLinks = () => {
        const theme = config.themes?.[state.theme];
        if (!theme) return false;

        const base = document.querySelector('[data-prefab-theme-base]');
        if (theme.base) {
            if (base) base.href = theme.base;
            else {
                const link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = theme.base;
                link.dataset.prefabThemeBase = '';
                document.head.appendChild(link);
            }
        } else if (base) {
            base.remove();
        }

        document.querySelectorAll('[data-prefab-theme-mode]').forEach(link => link.remove());
        const modes = theme.modes || {};

        if (state.mode === 'system') {
            for (const mode of ['light', 'dark']) {
                if (!modes[mode]) continue;
                const link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = modes[mode];
                link.media = '(prefers-color-scheme: ' + mode + ')';
                link.dataset.prefabThemeMode = mode;
                document.head.appendChild(link);
            }
        } else if (modes[state.mode]) {
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = modes[state.mode];
            link.dataset.prefabThemeMode = state.mode;
            document.head.appendChild(link);
        }
        return true;
    };

    const dispatch = source => {
        document.dispatchEvent(new CustomEvent('prefab:themechange', {
            detail: { ...state, actualMode: actualMode(), source }
        }));
    };

    const save = () => {
        if (!config.saveUrl) return;
        fetch(config.saveUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ theme: state.theme, mode: state.mode, density: state.density })
        }).catch(() => {});
    };

    const apply = (source = 'api', persist = true) => {
        root.dataset.theme = state.theme;
        root.dataset.mode = state.mode;
        root.dataset.density = state.density;
        syncLinks();
        syncBootstrap();
        dispatch(source);
        if (persist) save();
    };

    const api = {
        get: () => ({ ...state, actualMode: actualMode() }),
        setTheme(theme, persist = true) {
            theme = String(theme || '').toLowerCase();
            if (!config.themes?.[theme]) return false;
            state.theme = theme;
            const modes = modesFor(theme);
            if (state.mode === 'system' && !supportsSystem(theme)) state.mode = modes[0] || 'light';
            else if (state.mode !== 'system' && !modes.includes(state.mode)) state.mode = modes[0] || 'light';
            apply('theme', persist);
            return true;
        },
        setMode(mode, persist = true) {
            mode = String(mode || '').toLowerCase();
            const modes = modesFor(state.theme);
            if (mode === 'system' ? !supportsSystem(state.theme) : !modes.includes(mode)) return false;
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
        }
    };

    window.PrefabTheme = api;
    syncBootstrap();

    const onSystemChange = () => {
        if (state.mode !== 'system') return;
        syncBootstrap();
        dispatch('system');
    };
    mediaDark.addEventListener?.('change', onSystemChange);

    document.addEventListener('keydown', event => {
        if (event.key !== 'Escape') return;
        const sidebar = document.querySelector('.pf-sidebar.is-open');
        if (sidebar) setSidebar(sidebar, false);
    });

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
        if (control) control.setAttribute('aria-expanded', open ? 'true' : 'false');
        document.dispatchEvent(new CustomEvent('prefab:sidebarchange', {
            detail: { open, sidebar }
        }));
    };

    document.addEventListener('click', event => {
        const theme = event.target.closest('[data-prefab-theme]');
        const mode = event.target.closest('[data-prefab-mode]');
        const density = event.target.closest('[data-prefab-density]');
        const sidebarToggle = event.target.closest('[data-prefab-sidebar-toggle]');
        const sidebarClose = event.target.closest('[data-prefab-sidebar-close]');

        if (theme && userAllows('theme')) api.setTheme(theme.dataset.prefabTheme);
        if (mode && userAllows('mode')) api.setMode(mode.dataset.prefabMode);
        if (density && userAllows('density')) api.setDensity(density.dataset.prefabDensity);

        if (sidebarToggle) {
            const sidebar = sidebarFor(sidebarToggle);
            setSidebar(sidebar, !sidebar?.classList.contains('is-open'), sidebarToggle);
        }

        if (sidebarClose) {
            setSidebar(sidebarFor(sidebarClose), false);
        }
    });

    document.addEventListener('change', event => {
        const target = event.target;
        if (!(target instanceof HTMLSelectElement)) return;
        if (target.matches('[data-prefab-theme-select]') && userAllows('theme')) api.setTheme(target.value);
        if (target.matches('[data-prefab-mode-select]') && userAllows('mode')) api.setMode(target.value);
        if (target.matches('[data-prefab-density-select]') && userAllows('density')) api.setDensity(target.value);
    });
})();
