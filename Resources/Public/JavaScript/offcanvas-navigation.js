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

        // Auto-open the dropdown containing the current page, mobile-menu only
        // (this handler only ever runs when the offcanvas is actually toggled,
        // i.e. via the toggler button, which is hidden at desktop widths).
        if (window.bootstrap && window.bootstrap.Dropdown) {
            offcanvasNav.querySelectorAll('.navbar-nav > .nav-item').forEach(function (navItem) {
                if (!navItem.querySelector('.active')) {
                    return;
                }
                const dropdownToggle = navItem.querySelector(':scope > .nav-link-toggle');
                if (dropdownToggle) {
                    window.bootstrap.Dropdown.getOrCreateInstance(dropdownToggle).show();
                }
            });
        }
    });

    offcanvasNav.addEventListener('hide.bs.offcanvas', function () {
        toggler.classList.add('collapsed');
        toggler.setAttribute('aria-expanded', 'false');
    });
});
