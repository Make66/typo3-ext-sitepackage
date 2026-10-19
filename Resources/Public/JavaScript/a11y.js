(() => {
    'use strict';

    const KEY = 'a11y-settings';
    const LIMITS = { fontStep: 5, spacingStep: 3 }; // +50 % Schrift, +0.12em Abstand
    const DEFAULTS = {
        fontStep: 0, spacingStep: 0,
        font: 'default', contrast: 'default',
        cursor: false, highlight: false, hideImages: false
    };
    const root = document.documentElement;

    let state = { ...DEFAULTS };
    try {
        state = { ...DEFAULTS, ...JSON.parse(localStorage.getItem(KEY) || '{}') };
    } catch (e) { /* localStorage nicht verfuegbar */ }

    const apply = () => {
        root.style.setProperty('--a11y-font-scale', String(1 + state.fontStep * 0.1));
        root.style.setProperty('--a11y-letter-spacing', (state.spacingStep * 0.04) + 'em');
        root.dataset.a11yFont = state.font;
        root.dataset.a11yContrast = state.contrast;
        root.toggleAttribute('data-a11y-cursor', state.cursor);
        root.toggleAttribute('data-a11y-highlight', state.highlight);
        root.toggleAttribute('data-a11y-hide-images', state.hideImages);
    };

    const save = () => {
        try { localStorage.setItem(KEY, JSON.stringify(state)); } catch (e) { /* ignorieren */ }
        apply();
    };

    // Sofort anwenden, damit es beim Seitenwechsel nicht flackert (Script im <head> laden)
    apply();

    document.addEventListener('DOMContentLoaded', () => {
        const tools = document.getElementById('a11y-tools');
        if (!tools) { return; }
        const toggle = tools.querySelector('.a11y-toggle');
        const panel = tools.querySelector('.a11y-panel');

        // Formularfelder mit gespeichertem Zustand synchronisieren
        const syncControls = () => {
            panel.querySelectorAll('input[type=radio]').forEach((el) => {
                el.checked = state[el.name] === el.value;
            });
            panel.querySelectorAll('input[type=checkbox]').forEach((el) => {
                el.checked = Boolean(state[el.name]);
            });
        };
        syncControls();

        // Panel oeffnen/schliessen
        const setOpen = (open) => {
            toggle.setAttribute('aria-expanded', String(open));
            panel.hidden = !open;
        };
        toggle.addEventListener('click', () => setOpen(panel.hidden));
        tools.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !panel.hidden) { setOpen(false); toggle.focus(); }
        });
        document.addEventListener('click', (e) => {
            if (!panel.hidden && !tools.contains(e.target)) { setOpen(false); }
        });

        // Plus/Minus-Buttons
        panel.querySelectorAll('[data-a11y-step]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const [key, delta] = btn.dataset.a11yStep.split(':');
                state[key] = Math.min(LIMITS[key], Math.max(0, state[key] + Number(delta)));
                save();
            });
        });

        // Radios und Schalter
        panel.addEventListener('change', (e) => {
            const el = e.target;
            if (el.type === 'radio') { state[el.name] = el.value; }
            if (el.type === 'checkbox') { state[el.name] = el.checked; }
            save();
        });

        // Zuruecksetzen
        panel.querySelector('[data-a11y-reset]').addEventListener('click', () => {
            state = { ...DEFAULTS };
            save();
            syncControls();
        });
    });
})();