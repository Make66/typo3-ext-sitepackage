/*
 * Overrides bk2k/bootstrap-package's own bootstrap.stickyheader.js (wired up
 * in Configuration/TypoScript/Setup/basis.typoscript). The original toggles
 * .navbar-transition on a single hard `120 < window.scrollY` check. Every
 * mandant's .navbar-transition rules (logo/brand shrinking, image fade-outs,
 * margin resets — see fileadmin/templates/<mandant>/custom.scss) carry
 * `transition: all .3s`, so each toggle restarts a 300ms animation.
 *
 * Three attempts before this one, each fixing a real problem and exposing
 * the next:
 *
 * 1. Spatial hysteresis (enter 120 / leave 90) — looked fixed under
 *    `window.scrollTo()` jumps, wasn't: real wheel/trackpad input drives
 *    Chromium's own smooth-scroll easing, which overshoots by more than a
 *    30px gap.
 * 2. Time debounce instead — fixed the momentum jitter, but then a genuine
 *    structural bug showed up on short pages (buergerstiftung's
 *    /die-stiftung): #page-header is position:sticky, i.e. part of normal
 *    document flow, so collapsing it shortens the *whole document* by its
 *    own height delta. Near the bottom of a page not much taller than that
 *    delta, collapsing pulls the max scroll position down with it, the
 *    browser clamps scrollY backward past the threshold, that un-collapses
 *    the header, which lengthens the document again, and continued
 *    scrolling pushes scrollY back past the threshold — repeating for as
 *    long as the user scrolls.
 * 3. A "freeze near the live bottom" guard, then a header-height-derived
 *    threshold — both still read `window.scrollY` as the source of truth,
 *    so both were still fundamentally exposed to the same clamp. They just
 *    changed *where* it could still happen (and, on properly long pages
 *    with a merely well-padded end — e.g. a real footer — the guard could
 *    swallow the threshold and disable the effect on a page that isn't
 *    actually short).
 *
 * The actual bug, underneath all three: window.scrollY is not a reliable
 * signal for "how far did the user mean to scroll." The browser silently
 * clamps it whenever *our own* class toggle shortens the document — that
 * clamp is a side effect of our own action, not user input, and nothing
 * that reads scrollY directly can tell the two apart.
 *
 * Fixed by not reading scrollY for decisions at all. Instead, track
 * *intended* scroll distance ourselves, accumulated directly from real
 * input events (wheel deltas, touch drags, key presses). A programmatic
 * clamp never fires a wheel/touch/key event, so it's structurally invisible
 * to this tracking, regardless of page height, header size, or how many
 * times the class has already toggled. intentY is clamped to
 * [0, expandedMaxScroll], where expandedMaxScroll is measured once at load
 * (before the header has ever collapsed) and never re-read afterward — so
 * it can't itself be affected by later collapses either.
 *
 * Plain 'scroll' is kept only as a low-trust fallback for scrollbar
 * dragging (the one common input method with no wheel/touch/key event of
 * its own) — deltas are capped small enough to filter out our own
 * collapse-triggered clamp jumps (which are roughly the header's own
 * collapse delta, generally 100+ px) while still catching genuine drag
 * input. This is the one remaining approximation; everything else is exact.
 *
 * BUG FOUND IN THE FIRST VERSION OF THIS FILE: that 'scroll' fallback was
 * unconditional — but a normal wheel-driven scroll *also* fires 'scroll'
 * events as the browser applies it, on top of the 'wheel' events already
 * being tracked. Every ordinary scroll was being counted twice
 * (wheelPixels(e) from 'wheel', then the resulting position change again
 * from 'scroll'), roughly doubling the effective scroll rate — reported as
 * the header jumping on every page, not just short ones. Fixed by gating
 * the 'scroll' fallback behind a cooldown after the last real wheel/touch/
 * key event, long enough to cover trackpad momentum's tail (which can keep
 * firing 'wheel' events for a second or more after the user's fingers
 * leave the trackpad) — 'scroll' only contributes once that cooldown has
 * genuinely elapsed, which in practice means only scrollbar dragging.
 *
 * THRESHOLD was the header's own natural height — simple, and it's what
 * made /die-stiftung's short-page math work out, but it means real body
 * content spends that entire scroll distance sliding underneath a
 * still-full-size sticky header before anything shrinks, worst on mandants
 * with visually tall headers (buergerstiftung: ~324px). Changed to trigger
 * at the point scrolling would otherwise start covering the page's actual
 * first content block: `.frame-group-container` is bk2k's own generic
 * wrapper around every content element (Templates/ViewHelpers/Frame/
 * Index.html), so its first occurrence's distance from the top, minus the
 * header's own height, is "how far until this content would start
 * disappearing under the full-size header." Both measured once at load
 * (same stable-reference principle as expandedMaxScroll above) — a page's
 * hero/carousel content before the first real frame (e.g. buergerstiftung's
 * homepage) still scrolls under the header at full size, same as before;
 * only content-bearing frames are protected. Falls back to the old
 * header-height threshold if a page has no `.frame-group-container` at all.
 */
window.addEventListener('DOMContentLoaded', function () {

    var stickyheader = document.querySelectorAll('.navbar-fixed-top');
    if (stickyheader.length < 1) {
        return;
    }
    var header = stickyheader[0];

    // Clean read: .navbar-transition has never been applied yet at this
    // point, so this is the header's true natural/expanded height.
    var headerHeight = header.getBoundingClientRect().height;

    var firstFrame = document.querySelector('.frame-group-container');
    var THRESHOLD = headerHeight;
    if (firstFrame) {
        var firstFrameTop = firstFrame.getBoundingClientRect().top + window.scrollY;
        THRESHOLD = Math.max(0, firstFrameTop - headerHeight);
    }

    var DEBOUNCE_MS = 150;

    // Stable reference, measured once. Deliberately never re-read — that's
    // what makes it safe to clamp intentY against, unlike the live
    // document height, which changes every time we toggle the class.
    var expandedMaxScroll = Math.max(0, document.documentElement.scrollHeight - window.innerHeight);

    var intentY = Math.min(window.scrollY, expandedMaxScroll);
    var applied = intentY > THRESHOLD;
    var pending = applied;
    var timer = null;

    function apply() {
        header.classList.toggle('navbar-transition', applied);
    }

    function commit() {
        timer = null;
        if (pending !== applied) {
            applied = pending;
            apply();
        }
    }

    function setDesired(desired) {
        if (desired !== pending) {
            pending = desired;
            if (timer) {
                clearTimeout(timer);
                timer = null;
            }
            if (pending !== applied) {
                timer = setTimeout(commit, DEBOUNCE_MS);
            }
        }
    }

    function addIntent(delta) {
        intentY = Math.max(0, Math.min(expandedMaxScroll, intentY + delta));
        setDesired(intentY > THRESHOLD);
    }

    // Wheel deltas aren't reliably in pixels across browsers — Firefox
    // defaults to DOM_DELTA_LINE (small integers, e.g. 1-5), some setups use
    // DOM_DELTA_PAGE. Normalize to an approximate pixel value; exact
    // precision doesn't matter here, only getting broadly the right scale.
    function wheelPixels(e) {
        if (e.deltaMode === 1) { // DOM_DELTA_LINE
            return e.deltaY * 20;
        }
        if (e.deltaMode === 2) { // DOM_DELTA_PAGE
            return e.deltaY * window.innerHeight;
        }
        return e.deltaY; // DOM_DELTA_PIXEL
    }

    var SCROLL_FALLBACK_COOLDOWN_MS = 1200;
    var lastRealInputTime = 0;

    function noteRealInput() {
        lastRealInputTime = performance.now();
    }

    window.addEventListener('wheel', function (e) {
        noteRealInput();
        addIntent(wheelPixels(e));
    }, { passive: true });

    var touchStartY = null;
    window.addEventListener('touchstart', function (e) {
        noteRealInput();
        touchStartY = e.touches.length ? e.touches[0].clientY : null;
    }, { passive: true });
    window.addEventListener('touchmove', function (e) {
        if (touchStartY === null || !e.touches.length) {
            return;
        }
        noteRealInput();
        var y = e.touches[0].clientY;
        addIntent(touchStartY - y); // swiping up (finger moves up) scrolls down
        touchStartY = y;
    }, { passive: true });

    var KEY_STEP = {
        ArrowDown: 40,
        ArrowUp: -40,
        PageDown: window.innerHeight * 0.9,
        PageUp: -window.innerHeight * 0.9,
        ' ': window.innerHeight * 0.9,
    };
    window.addEventListener('keydown', function (e) {
        if (e.key === 'Home') {
            noteRealInput();
            intentY = 0;
            setDesired(false);
        } else if (e.key === 'End') {
            noteRealInput();
            intentY = expandedMaxScroll;
            setDesired(intentY > THRESHOLD);
        } else if (Object.prototype.hasOwnProperty.call(KEY_STEP, e.key)) {
            noteRealInput();
            addIntent(e.key === ' ' && e.shiftKey ? -KEY_STEP[' '] : KEY_STEP[e.key]);
        }
    });

    // Scrollbar-drag fallback — only trusted once well clear of any recent
    // wheel/touch/key input (see file comment above for why), and even then
    // only small deltas, to filter out our own collapse-triggered clamp
    // jumps (roughly the header's own collapse delta, generally 100+ px).
    var lastKnownScrollY = window.scrollY;
    window.addEventListener('scroll', function () {
        var y = window.scrollY;
        var delta = y - lastKnownScrollY;
        lastKnownScrollY = y;
        if (performance.now() - lastRealInputTime < SCROLL_FALLBACK_COOLDOWN_MS) {
            return;
        }
        if (delta !== 0 && Math.abs(delta) <= 60) {
            addIntent(delta);
        }
    }, { passive: true });

    apply();

});
