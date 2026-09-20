(() => {
    'use strict';

    const componentSelector = '[pf\\:component]';
    const loadingSelector = '[pf\\:loading]';
    const busy = new WeakSet();

    const decodeSnapshot = (encoded) => {
        const value = encoded.replace(/-/g, '+').replace(/_/g, '/');
        const padded = value + '='.repeat((4 - value.length % 4) % 4);
        return JSON.parse(decodeURIComponent(Array.from(atob(padded), c => '%' + c.charCodeAt(0).toString(16).padStart(2, '0')).join('')));
    };

    const encodeSnapshot = (snapshot) => {
        const json = JSON.stringify(snapshot);
        const bytes = unescape(encodeURIComponent(json));
        return btoa(bytes).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
    };

    const modelElements = (root) => Array.from(root.querySelectorAll('[pf\\:model]'));

    const readValue = (element) => {
        if (element instanceof HTMLInputElement) {
            if (element.type === 'checkbox') return element.checked;
            if (element.type === 'radio') return element.checked ? element.value : undefined;
        }

        if (element instanceof HTMLSelectElement && element.multiple) {
            return Array.from(element.selectedOptions).map(option => option.value);
        }

        return element.value;
    };

    const collectUpdates = (root) => {
        const updates = {};

        for (const element of modelElements(root)) {
            const path = element.getAttribute('pf:model');
            if (!path) continue;

            const value = readValue(element);
            if (value !== undefined) updates[path] = value;
        }

        return updates;
    };

    const setLoading = (root, active) => {
        root.setAttribute('aria-busy', active ? 'true' : 'false');
        root.querySelectorAll(loadingSelector).forEach(element => {
            element.hidden = !active;
        });
    };

    const request = async (root, method = null) => {
        if (busy.has(root)) return;
        busy.add(root);
        setLoading(root, true);

        try {
            const endpoint = root.getAttribute('pf:endpoint') || '/prefab/live';
            const csrf = root.getAttribute('pf:csrf');
            const headers = {'Content-Type': 'application/json', 'Accept': 'application/json'};
            if (csrf) headers['X-CSRF-Token'] = csrf;

            const payload = {
                id: root.getAttribute('pf:id'),
                component: root.getAttribute('pf:component'),
                snapshot: decodeSnapshot(root.getAttribute('pf:snapshot') || ''),
                checksum: root.getAttribute('pf:checksum'),
                updates: collectUpdates(root),
                action: method ? {method, params: []} : null,
            };

            const response = await fetch(endpoint, {
                method: 'POST',
                headers,
                body: JSON.stringify(payload),
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error(`Prefab Live request failed with HTTP ${response.status}.`);
            }

            const data = await response.json();
            if (!data || typeof data.html !== 'string' || !data.snapshot || typeof data.checksum !== 'string') {
                throw new Error('Prefab Live received an invalid response.');
            }

            root.innerHTML = data.html;
            root.setAttribute('pf:snapshot', encodeSnapshot(data.snapshot));
            root.setAttribute('pf:checksum', data.checksum);
            root.querySelectorAll(loadingSelector).forEach(element => element.hidden = true);
            root.dispatchEvent(new CustomEvent('prefab:live:updated', {bubbles: true, detail: data}));
        } catch (error) {
            root.dispatchEvent(new CustomEvent('prefab:live:error', {bubbles: true, detail: {error}}));
            console.error(error);
        } finally {
            setLoading(root, false);
            busy.delete(root);
        }
    };

    document.querySelectorAll(componentSelector).forEach(root => setLoading(root, false));

    document.addEventListener('click', event => {
        const target = event.target instanceof Element ? event.target.closest('[pf\\:click]') : null;
        if (!target) return;

        const root = target.closest(componentSelector);
        const method = target.getAttribute('pf:click');
        if (!root || !method) return;

        event.preventDefault();
        request(root, method);
    });

    document.addEventListener('submit', event => {
        const form = event.target instanceof HTMLFormElement ? event.target : null;
        if (!form || !form.hasAttribute('pf:submit')) return;

        const root = form.closest(componentSelector);
        const method = form.getAttribute('pf:submit');
        if (!root || !method) return;

        event.preventDefault();
        request(root, method);
    });
})();
