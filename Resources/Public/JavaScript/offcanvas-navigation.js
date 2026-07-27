document.addEventListener('DOMContentLoaded', function () {
    const offcanvasNav = document.getElementById('mainnavigation');
    if (!offcanvasNav) {
        return;
    }

    const toggler = document.querySelector('[data-bs-target="#mainnavigation"]');
    if (!toggler) {
        return;
    }

    offcanvasNav.addEventListener('show.bs.offcanvas', function () {
        toggler.classList.remove('collapsed');
        toggler.setAttribute('aria-expanded', 'true');
    });

    // Auto-open the dropdown containing the current page, mobile-menu only.
    // Must wait for "shown" (past tense, fired after the offcanvas finishes
    // its opening transition and moves focus into the panel) rather than
    // "show" (fired immediately on toggle) — opening the dropdown while the
    // offcanvas's own focus trap is still settling makes Bootstrap's dropdown
    // treat that focus shift as an outside interaction and auto-close it
    // again within ~100ms.
    offcanvasNav.addEventListener('shown.bs.offcanvas', function () {
        if (window.bootstrap && window.bootstrap.Dropdown) {
            const activeToggle = offcanvasNav.querySelector('.navbar-nav > .nav-item > .nav-link-main.active[data-bs-toggle="dropdown"]');
            if (activeToggle) {
                window.bootstrap.Dropdown.getOrCreateInstance(activeToggle).show();
            }
        }
    });

    offcanvasNav.addEventListener('hide.bs.offcanvas', function () {
        toggler.classList.add('collapsed');
        toggler.setAttribute('aria-expanded', 'false');
    });
});
