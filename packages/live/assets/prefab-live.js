(() => {
    'use strict';

    const componentSelector = '[pf\\:component]';
    const loadingSelector = '[pf\\:loading]';
    const errorSelector = '[pf\\:error]';
    const requestState = new WeakMap();
    const errorState = new WeakMap();
    const debounceTimers = new WeakMap();

    const decodeSnapshot = (encoded) => {
        const value = encoded.replace(/-/g, '+').replace(/_/g, '/');
        const padded = value + '='.repeat((4 - value.length % 4) % 4);
        const bytes = Uint8Array.from(atob(padded), c => c.charCodeAt(0));
        return JSON.parse(new TextDecoder().decode(bytes));
    };

    const encodeSnapshot = (snapshot) => {
        const bytes = new TextEncoder().encode(JSON.stringify(snapshot));
        let binary = '';
        bytes.forEach(byte => binary += String.fromCharCode(byte));
        return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
    };

    const modelDirective = (element) => {
        if (!(element instanceof Element)) return null;

        for (const attribute of element.attributes) {
            if (attribute.name !== 'pf:model' && !attribute.name.startsWith('pf:model.')) continue;
            const modifiers = attribute.name === 'pf:model'
                ? []
                : attribute.name.slice('pf:model.'.length).split('.').filter(Boolean);
            return {path: attribute.value.trim(), modifiers};
        }

        return null;
    };

    const modelElements = (root) => Array.from(root.querySelectorAll('input,select,textarea'))
        .filter(element => modelDirective(element)?.path);

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
            const directive = modelDirective(element);
            if (!directive?.path) continue;

            const value = readValue(element);
            if (value !== undefined) updates[directive.path] = value;
        }

        return updates;
    };

    const debounceDelay = (modifiers) => {
        const index = modifiers.indexOf('debounce');
        if (index < 0) return 0;
        const token = modifiers[index + 1] || '300ms';
        const match = token.match(/^(\d+)(ms)?$/i);
        return match ? Math.max(0, Number(match[1])) : 300;
    };

    const fieldLoadingMatches = (element, fields) => {
        const target = (element.getAttribute('pf:loading') || '').trim();
        return target === '' || fields.includes(target);
    };

    const setLoading = (root, active, fields = []) => {
        root.setAttribute('aria-busy', active ? 'true' : 'false');
        root.querySelectorAll(loadingSelector).forEach(element => {
            element.hidden = !(active && fieldLoadingMatches(element, fields));
        });
    };

    const getPath = (data, path) => {
        let value = data;
        for (const segment of path.split('.')) {
            if (!value || typeof value !== 'object' || !(segment in value)) return undefined;
            value = value[segment];
        }
        return value;
    };

    const setElementValue = (element, value) => {
        if (value === undefined) return;

        if (element instanceof HTMLInputElement) {
            if (element.type === 'checkbox') {
                element.checked = Boolean(value);
                return;
            }
            if (element.type === 'radio') {
                element.checked = String(value) === element.value;
                return;
            }
        }

        if (element instanceof HTMLSelectElement && element.multiple && Array.isArray(value)) {
            const selected = new Set(value.map(String));
            Array.from(element.options).forEach(option => option.selected = selected.has(option.value));
            return;
        }

        element.value = value === null ? '' : String(value);
    };

    const syncModelValues = (root, snapshot) => {
        modelElements(root).forEach(element => {
            const directive = modelDirective(element);
            if (directive?.path) setElementValue(element, getPath(snapshot, directive.path));
        });
    };

    const errorEntries = (errors) => errors && typeof errors === 'object' ? errors : {};

    const renderErrors = (root) => {
        const errors = errorState.get(root) || {};

        root.querySelectorAll(errorSelector).forEach(element => {
            const field = (element.getAttribute('pf:error') || '').trim();
            const messages = field && Array.isArray(errors[field]) ? errors[field] : [];
            element.textContent = messages[0] || '';
            element.hidden = messages.length === 0;
            if (!element.hasAttribute('aria-live')) element.setAttribute('aria-live', 'polite');
        });

        modelElements(root).forEach(element => {
            const field = modelDirective(element)?.path;
            if (!field) return;
            if (Array.isArray(errors[field]) && errors[field].length) {
                element.setAttribute('aria-invalid', 'true');
            } else {
                element.removeAttribute('aria-invalid');
            }
        });
    };

    const mergeErrors = (root, data, replace = false) => {
        const next = replace ? {} : {...(errorState.get(root) || {})};
        const validated = Array.isArray(data.validated) ? data.validated : [];

        validated.forEach(field => delete next[field]);
        Object.entries(errorEntries(data.errors)).forEach(([field, messages]) => {
            if (Array.isArray(messages) && messages.length) next[field] = messages;
        });

        errorState.set(root, next);
    };

    const focusIdentity = (element) => {
        if (!(element instanceof Element)) return null;
        const key = element.getAttribute('pf:key');
        if (key) return {type: 'key', value: key};
        const model = modelDirective(element)?.path;
        if (model) return {type: 'model', value: model};
        const name = element.getAttribute('name');
        if (name) return {type: 'name', value: name};
        if (element.id) return {type: 'id', value: element.id};
        return null;
    };

    const findByIdentity = (root, identity) => {
        if (!identity) return null;

        if (identity.type === 'model') {
            return modelElements(root).find(element => modelDirective(element)?.path === identity.value) || null;
        }

        const candidates = identity.type === 'key'
            ? root.querySelectorAll('[pf\\:key]')
            : identity.type === 'name'
                ? root.querySelectorAll('[name]')
                : root.querySelectorAll('[id]');

        return Array.from(candidates).find(element => {
            if (identity.type === 'key') return element.getAttribute('pf:key') === identity.value;
            if (identity.type === 'name') return element.getAttribute('name') === identity.value;
            return element.id === identity.value;
        }) || null;
    };

    const replaceContentPreservingFocus = (root, html, snapshot) => {
        const active = document.activeElement instanceof Element && root.contains(document.activeElement)
            ? document.activeElement
            : null;
        const identity = focusIdentity(active);
        const selection = active && 'selectionStart' in active
            ? [active.selectionStart, active.selectionEnd]
            : null;

        root.innerHTML = html;
        syncModelValues(root, snapshot);

        const replacement = findByIdentity(root, identity);
        if (!(replacement instanceof HTMLElement)) return;

        replacement.focus({preventScroll: true});
        if (selection && 'setSelectionRange' in replacement) {
            const length = String(replacement.value ?? '').length;
            replacement.setSelectionRange(
                Math.min(selection[0] ?? length, length),
                Math.min(selection[1] ?? length, length),
            );
        }
    };

    const initializeRoot = (root) => {
        if (!(root instanceof Element)) return;
        if (!errorState.has(root)) errorState.set(root, {});
        setLoading(root, false);
        renderErrors(root);
    };

    const request = async (root, options = {}) => {
        const state = requestState.get(root) || {sequence: 0, controller: null};
        state.sequence++;
        const sequence = state.sequence;
        state.controller?.abort();
        state.controller = new AbortController();
        requestState.set(root, state);

        const action = options.action || null;
        const fields = Array.isArray(options.validate) ? options.validate : [];
        const updates = options.updates || (action ? collectUpdates(root) : {});
        setLoading(root, true, fields);

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
                updates,
                validate: fields,
                action: action ? {method: action, params: []} : null,
            };

            const response = await fetch(endpoint, {
                method: 'POST',
                headers,
                body: JSON.stringify(payload),
                credentials: 'same-origin',
                signal: state.controller.signal,
            });

            if (!response.ok) {
                throw new Error(`Prefab Live request failed with HTTP ${response.status}.`);
            }

            const data = await response.json();
            if (!data || typeof data.html !== 'string' || !data.snapshot || typeof data.checksum !== 'string') {
                throw new Error('Prefab Live received an invalid response.');
            }

            if ((requestState.get(root)?.sequence || 0) !== sequence) return;

            mergeErrors(root, data, Boolean(action));
            replaceContentPreservingFocus(root, data.html, data.snapshot);
            root.setAttribute('pf:snapshot', encodeSnapshot(data.snapshot));
            root.setAttribute('pf:checksum', data.checksum);
            renderErrors(root);
            root.dispatchEvent(new CustomEvent('prefab:live:updated', {bubbles: true, detail: data}));
        } catch (error) {
            if (error?.name === 'AbortError') return;
            root.dispatchEvent(new CustomEvent('prefab:live:error', {bubbles: true, detail: {error}}));
            console.error(error);
        } finally {
            if ((requestState.get(root)?.sequence || 0) === sequence) {
                setLoading(root, false, fields);
            }
        }
    };

    const sendModel = (element) => {
        const directive = modelDirective(element);
        const root = element.closest(componentSelector);
        if (!directive?.path || !root) return;

        const value = readValue(element);
        if (value === undefined) return;

        request(root, {
            updates: {[directive.path]: value},
            validate: [directive.path],
        });
    };

    const scheduleModel = (element) => {
        const directive = modelDirective(element);
        if (!directive) return;
        const delay = debounceDelay(directive.modifiers);

        const current = debounceTimers.get(element);
        if (current) clearTimeout(current);

        if (delay <= 0) {
            sendModel(element);
            return;
        }

        const timer = setTimeout(() => {
            debounceTimers.delete(element);
            sendModel(element);
        }, delay);
        debounceTimers.set(element, timer);
    };

    document.querySelectorAll(componentSelector).forEach(initializeRoot);

    document.addEventListener('input', event => {
        const element = event.target instanceof Element ? event.target : null;
        const directive = modelDirective(element);
        if (!directive || directive.modifiers.includes('blur')) return;
        if (!directive.modifiers.includes('live') && !directive.modifiers.includes('debounce')) return;
        scheduleModel(element);
    });

    document.addEventListener('change', event => {
        const element = event.target instanceof Element ? event.target : null;
        const directive = modelDirective(element);
        if (!directive || directive.modifiers.includes('blur')) return;
        if (!directive.modifiers.includes('live') && !directive.modifiers.includes('debounce')) return;

        if (element instanceof HTMLSelectElement
            || (element instanceof HTMLInputElement && ['checkbox', 'radio'].includes(element.type))) {
            scheduleModel(element);
        }
    });

    document.addEventListener('focusout', event => {
        const element = event.target instanceof Element ? event.target : null;
        const directive = modelDirective(element);
        if (!directive?.modifiers.includes('blur')) return;
        scheduleModel(element);
    });

    document.addEventListener('click', event => {
        const target = event.target instanceof Element ? event.target.closest('[pf\\:click]') : null;
        if (!target) return;

        const root = target.closest(componentSelector);
        const method = target.getAttribute('pf:click');
        if (!root || !method) return;

        event.preventDefault();
        request(root, {action: method});
    });

    document.addEventListener('submit', event => {
        const form = event.target instanceof HTMLFormElement ? event.target : null;
        if (!form || !form.hasAttribute('pf:submit')) return;

        const root = form.closest(componentSelector);
        const method = form.getAttribute('pf:submit');
        if (!root || !method) return;

        event.preventDefault();
        request(root, {action: method});
    });

    new MutationObserver(mutations => {
        mutations.forEach(mutation => {
            mutation.addedNodes.forEach(node => {
                if (!(node instanceof Element)) return;
                if (node.matches(componentSelector)) initializeRoot(node);
                node.querySelectorAll?.(componentSelector).forEach(initializeRoot);
            });
        });
    }).observe(document.documentElement, {childList: true, subtree: true});
})();
