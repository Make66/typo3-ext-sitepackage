window.addEventListener('DOMContentLoaded', function () {

    /*
     * Smooth Scroll
     *
     * Copy of bk2k/bootstrap-package's bootstrap.smoothscroll.js with one fix:
     * the original only compares pathname + hostname, so a link to the same
     * page with a different query string plus an anchor (e.g. calendarize's
     * month navigation "/aktuelles?...[month]=11...#c802") was treated as an
     * in-page jump — preventDefault() and scroll to #c802 instead of loading
     * the next month. We also require the query string to match.
     */
    const ankers = document.querySelectorAll('a[href*="#"]:not([href$="#"])');
    ankers.forEach(function (anker) {
        anker.addEventListener('click', function (event) {
            const element = event.currentTarget;
            if (location.pathname.replace(/^\//, '') === element.pathname.replace(/^\//, '')
                && location.hostname === element.hostname
                && location.search === element.search) {
                let target = document.querySelectorAll(element.hash.replace(/(:|\.|\[|\]|,|=|\/)/g, '\\$1'));
                target = target.length && target || document.querySelectorAll('[name=' + element.hash.slice(1) + ']');
                if (target.length) {
                    event.preventDefault();
                    let targetOffset = target[0].getBoundingClientRect().top + window.scrollY;
                    const navbar = document.querySelector('.navbar-fixed-top');
                    if (navbar && targetOffset !== 0) {
                        targetOffset -= navbar.getBoundingClientRect().height;
                    }
                    target[0].setAttribute('tabindex', '-1');
                    target[0].focus();
                    scroll({ top: targetOffset, behavior: "smooth" });
                    return false;
                }
            }
        });
    });

    /*
     * Scroll to top
     */
    document.querySelector('.scroll-top').addEventListener('click', function (event) {
        event.currentTarget.blur();
    });

    window.addEventListener('scroll', function (event) {
        if (window.scrollY > 300) {
            document.querySelector('.scroll-top').classList.add('scroll-top-visible');
        } else {
            document.querySelector('.scroll-top').classList.remove('scroll-top-visible');
        }
    });

});
