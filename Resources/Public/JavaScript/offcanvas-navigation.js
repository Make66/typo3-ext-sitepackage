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

    offcanvasNav.addEventListener('hide.bs.offcanvas', function () {
        toggler.classList.add('collapsed');
        toggler.setAttribute('aria-expanded', 'false');
    });
});
