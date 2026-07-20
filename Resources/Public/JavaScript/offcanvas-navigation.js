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

    // Mobile menu: submenus are open by default every time the panel opens.
    // Must wait for "shown" (past tense, fired after the offcanvas finishes
    // its opening transition and moves focus into the panel) rather than
    // "show" (fired immediately on toggle) — opening the dropdowns while the
    // offcanvas's own focus trap is still settling makes Bootstrap's dropdown
    // treat that focus shift as an outside interaction and auto-close them
    // again within ~100ms. Users can still collapse/reopen individual ones by
    // clicking their top-level link, which doubles as the dropdown toggle
    // (see MainNavigation.html).
    offcanvasNav.addEventListener('shown.bs.offcanvas', function () {
        if (window.bootstrap && window.bootstrap.Dropdown) {
            offcanvasNav.querySelectorAll('.navbar-nav > .nav-item > .nav-link-main[data-bs-toggle="dropdown"]').forEach(function (dropdownToggle) {
                window.bootstrap.Dropdown.getOrCreateInstance(dropdownToggle).show();
            });
        }
    });

    offcanvasNav.addEventListener('hide.bs.offcanvas', function () {
        toggler.classList.add('collapsed');
        toggler.setAttribute('aria-expanded', 'false');
    });
});
