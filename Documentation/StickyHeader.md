# The sticky/shrinking header (`bootstrap.stickyheader.js`)

Covers `packages/sitepackage/Resources/Public/JavaScript/bootstrap.stickyheader.js`
and its wiring in `Configuration/TypoScript/Setup/basis.typoscript`. Written up
2026-08-15 after a multi-day debugging session (started while investigating the
bootstrap-package 13→15 upgrade — see [SCSS_migration.md](./SCSS_migration.md)'s
"Update 2026-08-14" section for that broader upgrade and the navigation/dropdown/
centering bugs found alongside this one).

## What it does

Every mandant's header (`#page-header.navbar-fixed-top`) shrinks once you've
scrolled far enough — smaller logo, smaller nav, less vertical space taken up.
The shrink itself is pure CSS: each mandant's own `custom.scss` has
`.navbar-transition .navbar-brand`/`.logo`/etc. rules with `transition: all .3s`.
This script's only job is deciding *when* to add/remove the `.navbar-transition`
class on the header.

bk2k/bootstrap-package ships its own version of this
(`vendor/bk2k/bootstrap-package/Resources/Public/JavaScript/Src/bootstrap.stickyheader.js`),
registered via `page.includeJSFooterlibs.bootstrap_stickyheader` in
`vendor/bk2k/bootstrap-package/Configuration/TypoScript/General/page.typoscript`.
The original is nine lines: `120 < window.scrollY` on every `scroll`/`resize`
event, nothing else. sitepackage overrides that TypoScript key (same pattern
already used for `includeCSS` and the RTE preset) to point at its own file
instead — `vendor/` is wiped on every `composer update`, so nothing durable can
live there.

## Why the original nine-line version had to go

Reported as the header "flickering" while scrolling on buergerstiftung. Fixing
it took five real iterations, each one fixing something genuine and exposing
the next problem underneath. Skip to [Current design](#current-design) if you
just need to know how it works today — this section is for whoever hits a
similar bug next and needs to know what's already been tried and why it
didn't fully work.

1. **Spatial hysteresis** (enter at 120px, leave at 90px). Verified with
   `window.scrollTo()` jumps in a test harness — looked fixed. Wasn't: real
   wheel/trackpad input drives the browser's own smooth-scroll easing, which
   overshoots and settles through a 40-50px range around wherever the user
   stops — comfortably wider than a 30px gap. Lesson: **test with real
   `mouse.wheel()` input, never `scrollTo()` jumps** — they don't exercise
   the same code path at all.

2. **Time debounce instead of spatial hysteresis** — track the desired state
   on every check, only commit (toggle the class) once it's held steady,
   unreverted, for 150ms. This genuinely fixed the momentum-jitter problem.
   But then a *different*, structural bug showed up on short pages
   (buergerstiftung's `/die-stiftung`): the header is `position: sticky`, i.e.
   part of normal document flow, so collapsing it shortens the *whole
   document* by its own height delta (~230px on buergerstiftung). On a page
   not much taller than that delta, collapsing pulls the max scroll position
   down with it, the browser clamps `scrollY` backward past the threshold,
   that un-collapses the header, which lengthens the document again, and
   continued scrolling pushes `scrollY` back past the threshold — repeating
   for as long as the user scrolls. No debounce duration fixes this: each
   cycle is driven by genuine scroll events, not jitter, and near a page's
   bottom the two states (collapsed/expanded) can *disagree about where the
   bottom even is*.

3. **A "freeze near the live bottom" guard**, then a **header-height-derived
   threshold** — two attempts, both still reading `window.scrollY` as the
   source of truth, so both were still structurally exposed to the same
   clamp; they only moved *where* it could happen. The height-derived
   threshold in particular had a nice side effect (a page shorter than the
   header's own height structurally can't reach the threshold at all — no
   special-casing needed) but was still fragile in principle, and the
   "freeze" guard actively made things worse: on `/die-stiftung` its freeze
   zone swallowed the threshold entirely, so the header stopped shrinking on
   a page that isn't actually short (it has a normal ~566px footer — the
   viewport just happened to be almost as tall as the whole page at the
   1400×900 test size, which doesn't generalize to real browser windows).

4. **The actual fix: stop reading `window.scrollY` for decisions at all.**
   It was never a reliable signal for "how far did the user mean to scroll,"
   because the browser silently clamps it whenever *our own* class toggle
   shortens the document — that clamp is a side effect of our own action,
   not user input, and nothing that reads `scrollY` directly can tell the
   two apart, no matter how the threshold is tuned. Instead, track *intended*
   scroll distance ourselves, accumulated from real input events (`wheel`
   deltas, `touchmove` drags, `keydown` steps). A programmatic clamp never
   fires one of those events, so it's structurally invisible to this
   tracking — this is the part that's actually robust, independent of page
   height, header size, or viewport size.

5. **A real bug in that same first version**, found only after "no more
   jumping!" turned into "menu is still jumping on all pages" — its
   scrollbar-drag fallback listened to plain `scroll` unconditionally, but a
   normal wheel-driven scroll *also* fires `scroll` events as the browser
   applies it. Every ordinary scroll was being counted twice: once from
   `wheel`, once from the `scroll` fallback that was only ever meant to catch
   scrollbar dragging. Fixed by gating that fallback behind a cooldown after
   the last genuine wheel/touch/key event (1.2s — long enough to cover a
   trackpad's momentum tail), so it only actually fires for what it was
   built for.

## Current design

Threshold and per-input handling both live in the script; only the shape of
the logic is summarized here — read the file for the exact numbers and the
full reasoning in its header comment.

- **`intentY`** — a running total of scroll distance, updated only from real
  `wheel`/`touchmove`/`keydown` events (normalizing `wheel`'s
  `deltaMode`, since Firefox defaults to line-based deltas, not pixels).
  Clamped to `[0, expandedMaxScroll]`, where `expandedMaxScroll` is
  `document.documentElement.scrollHeight - window.innerHeight` measured
  **once**, at load, before the header has ever collapsed — a stable
  reference that can't itself be perturbed by a later collapse.
- **`THRESHOLD`** — see below; not a fixed number, derived per-page.
- **Debounce** (150ms) on top of the intent tracking — cheap insurance
  against a user rapidly reversing direction right at the threshold; matters
  less now than it did before step 4 above, but there's no reason to remove
  it.
- **`scroll`-event fallback** for scrollbar dragging only, gated behind a
  1.2s cooldown since the last real wheel/touch/key event, and only for
  small deltas (≤60px) even then — filters out our own collapse-triggered
  clamp jumps, which are roughly the header's own collapse delta (100+ px).
  This is the one remaining approximation in the whole design; everything
  else responds exactly to real input.

## Where the threshold comes from

Originally just the header's own natural height (measured once, cleanly, at
load — no toggle-and-measure tricks, which turned out not to give reliable
values mid-transition anyway). That meant real body content spent the entire
scroll-to-threshold distance sliding underneath a still-full-size sticky
header before anything shrank — worst on mandants with visually tall headers
(buergerstiftung: ~324px).

Changed to trigger at the point scrolling would otherwise start covering the
page's actual **first content block**, not just "N px down": bk2k's own
`.frame-group-container` (`vendor/bk2k/bootstrap-package/Resources/Private/
Templates/ViewHelpers/Frame/Index.html`) wraps every content element, so

```
THRESHOLD = max(0, firstFrameGroupContainer.top - headerHeight)
```

— both measured once at load, same stable-reference principle as
`expandedMaxScroll`. In effect: "shrink by the time this content would start
disappearing under the full-size header." A hero/carousel before the first
real content frame (buergerstiftung's homepage has one) still scrolls under
the full header, same as before — only actual content-bearing frames are
protected. Falls back to the plain header-height threshold on any page with
no `.frame-group-container` at all.

Measured effect on buergerstiftung's `/die-stiftung` and `/spenden` (both use
the same template): threshold dropped from ~324px to 36px, verified
pixel-exact (header stays expanded at 31px, collapses at 46px).

## Known limitations

- **Scrollbar dragging** is the one input method with no dedicated DOM event
  to hook — see the `scroll`-fallback approximation above. Low risk in
  practice (most users scroll via wheel/trackpad/touch/keyboard), but a
  scrollbar-drag scroll that happens to move in bursts larger than 60px, or
  within 1.2s of the last wheel tick, won't register.
- The header-height and frame-group-container measurements happen at
  `DOMContentLoaded`. A page whose layout height changes significantly after
  that (very late-loading images without reserved dimensions, content
  injected by other scripts) could end up with a threshold measured against
  a layout that no longer matches — not observed in practice, just a
  theoretical gap in the "measure once, trust forever" design.

## Debugging this again

Read the file's own header comment first — it's the authoritative, detailed
version of the journey above, kept in sync with the code (this file is the
narrative summary; the code comment is the reference). If a report sounds
like "the header flickers/jumps while scrolling," check in this order before
assuming it's a new bug:

1. Is the deployed script actually the current version?
   `curl` the page, find the `bootstrap.stickyheader-*.js` asset URL, fetch
   it, confirm it matches `packages/sitepackage/Resources/Public/JavaScript/
   bootstrap.stickyheader.js` (TYPO3 hashes the compiled filename by content,
   so a stale hash means a cache that needs flushing, not a code bug).
2. Reproduce with **real `mouse.wheel()` input** in a test, not
   `scrollTo()` jumps — item 1 in the journey above is exactly the trap of
   trusting a test that doesn't exercise real scroll physics.
3. Check whether the report is really about `.navbar-transition` toggling at
   all, or about something else in the same visual area (a different mandant
   CSS issue, a genuinely new page-height edge case, etc.) — don't assume
   every "header looks wrong while scrolling" report is this script.
