# SCSS Processing Chain

Traced against the ddev instance at `/Users/martin/dev/ddev/t3v14`, site `calden`
(`fileadmin/templates/calden`), with this extension symlinked in as
`vendor/taketool/sitepackage` via a composer path repository.

## 1. Wiring (TypoScript)

Bootstrap5's own set only registers `theme`:

```
# vendor/bk2k/bootstrap-package/Configuration/Sets/Bootstrap5/setup.typoscript:2-5
page.includeCSS.theme = EXT:bootstrap_package/Resources/Public/Scss/bootstrap5/theme.scss
```

This extension overrides that and adds three more entries, in
`Configuration/TypoScript/Setup/basis.typoscript:12-23`:

```
page.includeCSS {
    theme >
    theme  = fileadmin/templates/{$mandant}/theme.scss
    global >
    global = fileadmin/templates/{$mandant}/global.scss
    layout = EXT:sitepackage/Resources/Public/Scss/Theme/{$layout}.scss
    custom >
    custom = fileadmin/templates/{$mandant}/custom.scss
}
```

`{$mandant}` resolves to the site identifier (e.g. `calden`) via that site's
`config/sites/<site>/settings.yaml`. There is no `SCSS_INC`/`compileScss`
cObject involved — the mechanism is simply `page.includeCSS` entries ending in
`.scss`, intercepted by a bk2k PageRenderer hook.

## 2. Compilation trigger

A core PageRenderer `render-preProcess` hook (bk2k's `PreProcessHook`) fires on
every **uncached** page render and calls into `CompileService`/`ScssParser`.
For each `.scss` entry it:

- computes a **stable** output filename:
  `sha256(md5(path) + serialized-compiler-settings)`. This hash does **not**
  depend on file content, which is why the output filename
  (`global-2bf611...css`) never changes even after edits.
- decides whether to actually **recompile** by comparing `filemtime()` of
  every file in the resolved `@import` chain (all files tracked in the
  `.meta` sidecar next to the compiled CSS) against the cached CSS's own
  mtime, and also diffs injected Site Settings SCSS variables (`primary`,
  `secondary`, etc.).

So editing any partial reachable via `@import` (e.g. `_fonts.scss`,
`Theme/global.scss`) bumps its mtime, and the next uncached request detects
that and recompiles into the *same* filename.

## 3. What actually triggers it in practice

- Fully page-cached requests skip the hook entirely (served straight from
  `cache_pages`).
- Any uncached render checks mtimes; an actual recompile flushes the whole
  `pages` cache group as a side effect, so subsequently-cached pages get
  rebuilt too. No manual cache flush is strictly required for a change to
  appear — one uncached hit is enough (a `cache:flush` plus a request
  guarantees it).

## 4. Output / cascade order

Insertion order in the merged TypoScript array wins:

**theme.css → global.css → plN.css (layout) → custom.css**

`custom.scss` therefore has the highest cascade precedence, and the raw
Bootstrap5 `theme.scss` the lowest. The `layout` entry (`pl0`..`pl3`, see
section 8) sits between `global` and `custom`, so `custom.scss` can still
override a layout variant.

## 5. Relevant extension configuration

- `disableCssProcessing` (bk2k ext-conf, default `0`) — kill switch for the
  whole compile hook.
- `plugin.tx_bootstrappackage.settings.overrideParserVariables` (default
  `true`) — injects Site Settings as SCSS variables; changes to those also
  force a recompile.
- `cssSourceMapping` (default `false`) — toggling it forces a recompile and
  uncompressed output.
- No Application Context (Development/Production) override exists; nothing
  forces recompilation on every request beyond the mtime/variable diff
  described in point 2.

## 6. Where to change an H1 (or other heading) font-size

There is no single canonical file — the value must be set in **two** places
because of Bootstrap's RFS (responsive font-size) scaling:

- **`fileadmin/templates/<mandant>/_variables.scss`** (e.g.
  `$h1-font-size: 3rem;`) is the source-of-truth Sass variable. It feeds all
  three compiles (`theme.scss`, `global.scss`, `custom.scss` each `@import
  "variables"` first).
- However, Bootstrap5's own compiled `theme.css` rule does **not** use that
  value flat. `_reboot.scss` emits headings via `@include font-size($h1-font-size)`,
  which routes through the RFS mixin (`$enable-rfs: true` by default, not
  overridden anywhere in this project). The actual compiled rule in
  `theme.css` is:

  ```css
  h1,.h1{font-size:calc(1.425rem + 2.1vw)}
  @media (min-width:1200px){h1,.h1{font-size:3rem}}
  ```

  i.e. fluid/scaled below a 1200px viewport, and only a flat 3rem above it.
- **`fileadmin/templates/<mandant>/custom.scss`** carries a plain (non-RFS)
  override:

  ```scss
  h1 {
    font-size:$h1-font-size;
    font-weight: 700;
  }
  ```

  Because `custom.css` loads last in the cascade (`theme.css → global.css →
  plN.css → custom.css`, see point 4) and this is a flat property assignment (no
  `rfs()`), it wins at every breakpoint and is what actually pins h1 to a
  flat size across all viewport widths. Without it, h1 would render
  RFS-scaled (noticeably smaller than 3rem on narrow viewports).
- calden's `custom.scss` also has a second, separate rule for
  `.element-subheader.h1` that mirrors the same `$h1-font-size` variable —
  keep it in sync if that class is in use.

**Practical rule of thumb:** change the number in `_variables.scss`, and make
sure the flat override rule in `custom.scss` still references the same
variable (don't hardcode a literal value there) so the two stay in sync.

### `Resources/Public/Scss/Theme/_variables.scss` is not imported

`Theme/global.scss` used to `@import "variables"` a second time (plus a
duplicate functions/variables/maps/mixins block) after the navigation partials.
Sass resolves a relative `@import` against the importing file's own directory
first, so that line picked up the sitepackage's own `Theme/_variables.scss`,
not the mandant's. That file has no `!default` assignments, so in the `global`
compile it silently overwrote every mandant's `$primary`, fonts, etc. with one
hardcoded set of values (`$primary: #28803f`, Overpass/Poppins).

Those lines are commented out now; the `global` compile sees only the
mandant's `_variables.scss` (imported by its glue file). `Theme/_variables.scss`
is no longer imported anywhere. Mandant values belong in
`fileadmin/templates/<mandant>/_variables.scss`. If the file is ever wanted
back as shared fallbacks, every assignment in it needs `!default`.

## 7. Does the custom.scss override break RFS? Where to address it

Yes — functionally it bypasses RFS entirely for that selector, it doesn't just
retune it. Bootstrap's own heading rule goes through `@include
font-size($h1-font-size)` → the `rfs()` mixin, which is what produces the
fluid `calc(1.425rem + 2.1vw)` interpolation. `custom.scss`'s plain `font-size:
$h1-font-size;` never calls that mixin, so there's no interpolation left —
just a flat value at every viewport width.

This is not limited to h1: calden's `custom.scss` applies the same pattern to
**h1 through h5** (`h2 { font-size:$h2-font-size; ... }`, `h3 { ... }`, `h4 {
... }`, `h5 { ... }`), each with its own flat `font-size` override. That
consistency across every heading level indicates this is a deliberate,
site-wide design decision (flat, non-fluid headings), not an accidental
side-effect of the h1 rule alone.

Where to make a change depends on intent:

| Goal | Where |
|---|---|
| Keep current flat-heading behavior | Nowhere — `custom.scss` is already the correct, deliberate override point. |
| Headings should be fluid/responsive again | Remove the `hN { font-size:$hN-font-size; }` rules from `custom.scss`; Bootstrap's own RFS-scaled rule (fed by the same `_variables.scss` values) takes over automatically. |
| Fluid scaling, different tuning (e.g. less shrink) | Don't touch `custom.scss`; tune RFS's own knobs (`$rfs-base-value`, `$rfs-factor`, `$rfs-breakpoint`) in `_variables.scss`. These are global RFS settings — they affect every RFS-scaled value project-wide, not just headings. |
| Disable RFS entirely for a mandant | Set `$enable-rfs: false;` in that mandant's `_variables.scss` before the bootstrap5 contrib variables layer loads. Global, biggest blast radius. |
| Disable RFS per-element via markup | Bootstrap RFS supports a class-based opt-out (`$rfs-class: "disable"` + a `.disable-responsive-font-size` class in the template) — needs Fluid template changes, more moving parts than the current SCSS-only approach. |

Given the consistent h1–h5 pattern already in place, leave `custom.scss` as
the enforcement point unless fluid headings are actually wanted — in that
case, delete the flat rules rather than fighting RFS with tuning variables.

## 8. Layout variants (pl0–pl3)

The `layout` entry shown in section 1 is a fourth `page.includeCSS` entry, placed after `global`:

```
layout = EXT:sitepackage/Resources/Public/Scss/Theme/{$layout}.scss
```

`{$layout}` is the site setting `layout` (`pl0` Mainz, `pl1` Budenheim, `pl2`
Gonsenheim, `pl3` Hechtsheim). Cascade order is
**theme → global → layout → custom**. The site setting is the only place the
layout is selected; no mandant file names it.

### What a layout file can see

- **Site Settings**: bk2k injects every constant under
  `plugin.bootstrap_package.settings.scss.*` as a SCSS variable into *every*
  compile (`CompileService::getVariablesFromConstants` → `addVariables`), so
  `$primary`, `$secondary` etc. in `plN.scss` are the **site setting** values.
- **`Theme/_layout-variables.scss`**: imported by every `plN.scss`; supplies
  all other values (fonts, sizes, card/box settings, icons) as `!default`.
- **Not visible**: `fileadmin/templates/<mandant>/_variables.scss`. Each
  includeCSS entry is its own compile and Sass `@import` cannot take a dynamic
  path, so a shared `plN.scss` cannot import the mandant's file. A mandant's
  fonts, font sizes or a `$primary` that differs from its site setting do
  **not** reach the layout.
- **`url()` paths** are resolved by bk2k relative to the entry file
  (`ScssParser::compileScss`, `registerFunction('url', …)`), here the
  sitepackage `Theme/` folder — a mandant-relative path like `img/pfeil.svg`
  does not work in `plN.scss`; use data URIs or absolute `https://` URLs.

### Files

| File | Content |
|---|---|
| `Theme/_layout-variables.scss` | Standard values, all `!default`. Imported by `plN.scss` and by the mandant glue files `theme`/`global`/`custom` right after the mandant's `variables`, so in those three compiles mandant values override them. Must not use Bootstrap variables or functions — Bootstrap is imported after it. |
| `Theme/plN.scss` | Complete, self-contained rule set of one layout: `_layout-variables`, Bootstrap tooling, the layout's mixins (`external`, `link-underline`, `link-icon`, `card-surface`, `card-hover`, `heading`), then all rules. pl0, pl1 and pl2 deliberately duplicate most rules so each layout can change independently. pl0 is the minimal base layout and the template for new variants (see below). pl3 is modelled on guetersloh.de (see "pl3 — Hechtsheim" below). |
| `<mandant>/_variables.scss` | Mandant values for `theme`, `global` and `custom`. No CSS output. |
| `<mandant>/custom.scss` | Mandant-only rules, and overrides of layout rules where the mandant needs its own fonts/sizes/colours/icons (it loads after the layout and sees `_variables.scss`). |

Rules are not scoped to `.plN`: no template sets that class, and the layout
CSS is only loaded for sites using that layout anyway.

### pl0 — base layout and template for new variants

pl0 (Mainz) is a classic, minimal city layout on plain Bootstrap markup,
deliberately different from pl1/pl2:

- **Header, two rows from lg:** white top row with the logo left and
  `.header-tools` right (search link to `sitepackage.a11y.searchPid`, only if
  set, plus the desktop accessibility button), below it a full-width menu bar
  in `$pl0-nav-bg` (default `$primary`) with white level-1 links. The bar is
  the `#mainnavigation` element itself (`flex: 0 0 100%` in a wrapping
  `.container-mainnavigation`); it reaches the viewport edges via
  `border-image: conic-gradient(<colour> 0 0) fill 0 / 0 / 0 100vmax` (outset
  only sideways; paints without layout or scroll overflow). Not a spread
  box-shadow + `clip-path` (the clip also hides the dropdowns below the bar)
  and not offset shadows (they are only as wide as the bar and leave gaps on
  wide screens) — so no template
  wrapper is needed and bk2k's offcanvas CSS keeps working. Below lg the header
  is one white row with the burger; the offcanvas header uses `$pl0-nav-bg`.
  Dropdown: white, square, `$pl0-dropdown-accent` top border; level 2 bold with
  dividers, level 3 indented with a guide line; mega menu in 3 columns.
- **Headings:** dark (`$pl0-ink`), h3 in `$h3-color`, no bar under h1. A
  content element's subheader is shown as a small uppercase label **above** the
  header (`.frame-header` is a flex column, `.element-subheader` gets
  `order: -1`; bk2k renders it after the header, no template override).
- **Cards (Bootstrap card, card group, Apicodo article cards, quicklink tiles,
  icon frame):** flat, 1px border, `$card-radius`, no shadow and no lift; a
  `$pl0-card-accent-width` bar in `$pl0-card-accent` drawn as `::before` above
  image/header, growing to `$pl0-card-accent-hover` on hover while the title
  turns primary. Apicodo events are divider-separated rows with a primary date
  block, not cards.
- **Other components:** square uppercase buttons, accordion with only dividers
  and plus/minus icons, tabs with a top accent, breadcrumb with `/`
  separators, square ke_search box with a `$pl0-nav-bg` button, carousel
  caption as a solid `$primary` box bottom left, footer with an accent top
  border and label-style headers.

All layout-only values are the `$pl0-*` variables at the top of `pl0.scss`.
Colours, radius, fonts and sizes otherwise come from the site settings.

`Resources/Private/_pl0/Partials/BootstrapPackage/Page/Navigation/` holds pl1's
three navigation partials; `Main.html` differs from pl1's only by the
`.header-tools` block (and the desktop accessibility button moved there from
the end of `.offcanvas-body`). Without these partials bk2k's stock navigation
renders, which lacks the offcanvas markup, the accessibility toggle and the
level-3 collapse buttons that `_navigation-dropdown-level3-toggle.scss` styles.

**Creating a new variant plN from pl0:**

1. Copy `Theme/pl0.scss` to `Theme/plN.scss`, rename the `$pl0-*` variables
   and adjust the header comment.
2. Copy `Resources/Private/_pl0/` to `Resources/Private/_plN/`.
3. Add `plN` to the `layout` enum in
   `Configuration/Sets/Sitepackage/settings.definitions.yaml`.
4. Select it per site via the `layout` site setting; check with
   `var/scsscheck/compile.php` (below).

**Heading weight of `.awesome` (every layout must keep this):** bk2k
`@extend`s `h1.awesome` … `h6.awesome` (carousel headers among others) to
Bootstrap's `.display-1` … `.display-6`, which hard-code
`font-weight: $display-font-weight` (300). Setting that variable to `null`
does not work: Bootstrap's `300 !default` also replaces a `null`, and bk2k's
theme offers no hook after Bootstrap's variables. Each layout therefore sets
`hN.awesome, .hN.awesome { font-weight }` to the weight its own `hN` rule
uses: pl0–pl2 loop over `$h1-font-weight` … `$h6-font-weight` right after
the `h6` rule; pl3 has no heading rules of its own (bk2k's theme uses
`$headings-font-weight` for all levels), so it uses `$headings-font-weight`.
If a layout changes its heading weights, the `.awesome` rule must follow.

### pl3 — Hechtsheim (modelled on guetersloh.de)

`pl3.scss` contains only the rule blocks below; everything else is bk2k/global.
Selectors carry no `.pl3` prefix: the file is only compiled and loaded on
sites with `layout: pl3`, so the prefix scoped nothing. Without it, pl3 rules
have the same specificity as the bk2k/global rules they override and win by
load order (theme → global → pl3). Two exceptions: a11y.css is loaded *after*
pl3 and has equally specific rules, so the toolbar block uses
`body #a11y-window` and the header padding rule
`:root:has(.a11y-window--top) body .navbar-mainnavigation…`; the "hide
toolbar while the mobile menu is open" rule anchors on `body:has(…)`.

0. **External links** (`target="_blank"`, like pl2 but without pl2's arrow
   for internal links and without the underline animation): mixins `external`
   and `link-icon` copied from pl2; `$icon-external` (14px mask, takes the text
   colour) before text links in `#page-content`/`#page-footer` — same selector
   and exceptions as pl2 section 2b (`p`/`td`/`.card-footer`/`li` links and
   `.stretched-link`; not `.btn`, `.no-link-icon`, empty links, links with
   `img`/`svg`, apicodo event rows, social links). External `.btn`s get the
   icon as `::after`, external main-menu items before `.nav-link-text`. The
   footer-meta exceptions in `global.scss` apply as well; the
   `.footer-copyright` links (TYPO3 / Bootstrap Package) are not excepted, as
   in pl2.
1. **Icon Group as tiles** (`.icongroup`, bk2k's IconGroup element —
   the one actually used for "Schnellzugriff"): every `.icongroup-item` is a
   white box, icon top left, header below, regardless of the element's
   icon-position setting. The header link gets a `::after` over the whole
   tile, so the tile is clickable. Column widths stay bk2k's.
2. **News cards** (`.apicodo-card--pl3-news`, like the news tiles on
   guetersloh.de): apicodo's `Article/Card.html` renders its own `pl3`
   section. The `.card-img-top` sits inside a 4:3 `.apicodo-card__media` box
   and fills it (`width`/`height: 100%`), which overrides the 16:9 from
   apicodo's `Card.css`. Image 4:3 (`object-fit: cover`, grey area when
   there is no image), a white body with the title, below it the date as
   `d.m.Y` in `$gray-600` (`.apicodo-card__date`), the summary, and a visible
   "Weiterlesen" link in `$secondary` as the `stretched-link`. On hover the
   whole card lifts like pl2's `card-hover` (`$card-hover-lift`,
   `$card-shadow-hover`, `$transition` from `_layout-variables`); the image
   does not scale.
3. **Apicodo events as cards** (`.apicodo-event-list` / `.apicodo-event-row`,
   like "Veranstaltungen"): CSS only on apicodo's `Event/Row.html` — the list
   becomes a grid, the row a column (image first via `order`, date badge on
   top of the image, date/time above the title). No template or TypoScript
   override for apicodo yet; therefore no visible "Event-Details" link
   (the card is clickable via `stretched-link`). `margin-bottom` needs
   `!important` against the `.mb-3` utility in the markup.
4. **Burger menu on desktop**: `_pl3/Partials/BootstrapPackage/Page/Navigation/`
   is a copy of pl0's partials. Above `$grid-float-breakpoint`, pl3 reverts
   what bk2k (`navbar/_responsive.scss`, `navbar/_dropdown.scss`) and
   `global.scss` (`_navigation-offcanvas.scss`, `_navigation-dropdown-hover.scss`,
   `-width`, `-position`) do for the inline navbar: toggler visible, offcanvas
   fixed and hidden again (`visibility: hidden !important` against global's
   `visible !important`, but not while `.show`/`.showing`/`.hiding`),
   `align-items: stretch` (Bootstrap's `.navbar-collapse { align-items: center }`
   would otherwise shrink-wrap header and body in the column panel), dropdowns
   static and click-only, backdrop visible. `offcanvas-navigation.js` has no
   breakpoint logic and works unchanged.
5. **Burger panel look** (`#mainnavigation.offcanvas`, all widths, like
   guetersloh.de): `$pl3-nav-bg` (default `$primary`) panel, bold text in
   `$pl3-nav-color`, dividers between level-1 items, active/hover in
   `$pl3-nav-accent`, which also fills the close button. `$pl3-nav-color` and
   `$pl3-nav-accent` default to the site settings "Navigation Schriftfarbe"
   (`navbar-font-color`) and "Navigation Hover-/Aktiv-Farbe"
   (`navbar-active-color`). Both settings default to empty, which means
   "layout default": `_layout-variables.scss` falls back to
   `$navbar-font-color-default` (`#333`) / `$navbar-active-color-default`
   (`$primary`), and pl3 sets these to `#fff` / `#333` before importing it,
   because the `$primary` panel needs light text. bk2k's rotated chevron is
   replaced by a +/− drawn from `::before`/`::after` (minus when the level-1
   link has `.show` or a level-2 toggle has `aria-expanded="true"`). Mega
   dropdown grids collapse to one column. The backdrop is white at 70 %.
   Selectors use classes only (`.navbar-mainnavigation .offcanvas …`,
   no ID), so a mandant's `custom.scss` (loaded after pl3) can override them
   with an equally or more specific class selector. The faint
   `.offcanvas-header` bottom border from `_navigation-offcanvas.scss` stays,
   because that rule carries `#mainnavigation`.
   Font sizes: `$pl3-nav-font-size` / `$pl3-nav-dropdown-font-size`.
   Colours are read through CSS custom properties with the SCSS value as
   fallback (`var(--pl3-nav-bg, #{$pl3-nav-bg})`, likewise `--pl3-nav-color`,
   `--pl3-nav-accent`, `--pl3-nav-divider`). pl3 does not set them; a
   mandant can set them in its `custom.scss`, e.g. podelzig:
   `:root { --pl3-nav-bg: #333; }`. A `--pl3-nav-color`/`--pl3-nav-accent`
   set there wins over the site settings, so for those two prefer the settings.
5b. **`.awesome` headings**: the `hN.awesome` font-weight loop described
   under "Heading weight of `.awesome`" above.
5c. **Carousel heights and text slides** (all carousels). Heights from `md`
   up; below `md` bk2k's values stay (400px / small 300px).
   `.carousel-fullscreen` keeps bk2k's 100vh.

   | Breakpoint | `.carousel .item` | `.carousel-small .item` |
   |---|---|---|
   | ≥ md | `$pl3-carousel-height-md` 520px | `$pl3-carousel-small-height-md` 400px |
   | ≥ lg | `$pl3-carousel-height-lg` `clamp(560px, 70vh, 760px)` | `…-lg` 460px |
   | ≥ xl | `$pl3-carousel-height-xl` `clamp(640px, 78vh, 880px)` | `…-xl` 520px |
   | ≥ xxl | `$pl3-carousel-height-xxl` `clamp(700px, 82vh, 960px)` | `…-xxl` 560px |

   The clamp keeps low laptops (1366×768) from a squashed slide and tall
   monitors from a slide that fills the whole screen.

   Heading sizes (all widths): carousel headers carry `.awesome`, which bk2k
   `@extend`s to Bootstrap's `.display-N` (up to 5rem). The editor picks
   h1–h4 in the backend (`header_layout`), so pl3 sizes them per level from
   the map `$pl3-carousel-header-sizes` (h1 `clamp(2rem, 4vw, 3.5rem)`, h2
   `clamp(1.75rem, 3.25vw, 3rem)`, h3 `clamp(1.5rem, 2.5vw, 2.5rem)`, h4
   `clamp(1.25rem, 2vw, 2rem)`), selector `.carousel hN.carousel-header`,
   which beats `hN.awesome`. The subheader (also `.awesome`) is a small bold
   uppercase label of `$pl3-carousel-subheader-size` (1.1rem). Both bk2k
   class sets are covered (`carousel-header`/`-subheader` in
   Header/TextAndImage, `carousel-item-header`/`-subheader` in Text).

   Text slides from `md` up: `header`, `text_and_image` and
   `call_to_action` items share one box (mixin `pl3-carousel-box`, applied
   to `.carousel-text-inner`, `.carousel-text` and `.carousel-box`):
   vertically centred on the left, max `$pl3-carousel-text-max-width`
   (40rem), `$pl3-carousel-box-bg` (default `$primary` at 85 %),
   `$pl3-carousel-box-color` (white), `$pl3-carousel-box-padding`,
   `$pl3-carousel-box-radius` (default `$border-radius`), slight
   `backdrop-filter: blur` and shadow. Children lose their margins and are
   spaced by `gap: .75rem` instead; the subheader is moved above the header
   via `order: -1`. This replaces bk2k's centred 80 % / 65 % header text and
   its white 50 % call-to-action box. The centring area ends
   `$pl3-carousel-text-bottom` (4rem) above the bottom edge for the
   indicators. The call-to-action button (`.carousel-item-button`, bk2k
   renders `btn-primary`) would vanish on the `$primary` box, so it gets a
   `button-variant` in `$pl3-carousel-box-color` with `$primary` text
   (hover `$gray-200`, active `$gray-300`). `text` items get the same
   left-aligned 40rem column without the box (they have the item layout's
   background) and stay vertically centred. Bodytext is `1.125rem`, cut
   after three lines. A linked slide is wrapped in `a.carousel-content-link`,
   which gets `height: 100%` so the alignment still works. `text_and_image`
   items split text and image 50/50 from `md`, 5/12 and 7/12 from `lg` up,
   the image at most 80 % of the slide height (`object-fit: contain`).
6. **A11y toolbar in the header, burger size**: see `A11yToolbar.md`
   ("Positions"). The burger lines are 45 × 4 px, ±12 px apart; the brand gets
   `align-self: center`, because bk2k's `.container-mainnavigation` sets
   `align-items: unset !important` and the 3rem toggler/toolbar make the row
   taller than the brand.
7. **Search box** (`.frame-search`, ke_search `pi1`): the searchbox is
   a white card with the tiles' shadow, max `$pl3-search-max-width`, a large
   borderless input and a `$primary` submit button with a magnifier icon
   (icon only below `md`, icon + label from `md`). Reset button and clearer
   are hidden. With `frame_layout` "overlap" the card overlaps the element
   above by `$pl3-search-overlap` from `md` up (not on mobile). The frame itself is
   `display: flow-root`, so the container's negative margin does not collapse
   through to it: only the card overlaps, the frame (and a frame background
   such as `frame-background-light`) starts at the carousel's bottom edge.
   Otherwise the background lies under the carousel and shows through the
   half-transparent slides during `carousel-fade` transitions.
8. **Footer**: rules like pl2 / Kleinich.
9. **Frame background "primary light"** (`.frame-background-primary-light`,
   see `$frame-backgrounds`): headings h1–h6 (and `.h1`–`.h6`) are `$primary`,
   except `.apicodo-event-tile__title`, which keeps its own color.

### Variables injected by bk2k

A `!default` in `_layout-variables.scss` loses against an injected site
setting. Three keys are therefore assigned **without** `!default`, from
intermediate variables:

| Bootstrap/bk2k variable | Set via |
|---|---|
| `$font-family-sans-serif` | `$font-body` |
| `$link-color` | `$text-link-color` |
| `$link-hover-color` | `$text-link-hover-color` |

### Where to put custom variable values

| Scope | Where |
|---|---|
| All layouts | `Resources/Public/Scss/Theme/_layout-variables.scss`, with `!default`. |
| One layout only | Top of that `plN.scss`, declared with `!default`. |
| Per site, affecting the layout | Site setting `plugin.bootstrap_package.settings.scss.<name>` (plain strings, no maps). |
| Per mandant, affecting theme/global/custom only | `fileadmin/templates/<mandant>/_variables.scss`. |

### Theme settings

Site settings in `Configuration/Sets/Sitepackage/settings.definitions.yaml`,
categories *Sitepackage › Typografie / Navigation / Breadcrumb / Cards /
Footer*. All are `plugin.bootstrap_package.settings.scss.<name>`, so bk2k
injects them into every compile; `_layout-variables.scss` picks them up via
`!default`.

| Setting | SCSS variable(s) |
|---|---|
| `font-body-key`, `font-heading-key` | `$font-body`, `$font-heading` via the font library — see `Fonts.md` |
| `font-weight-body` | `$font-weight-body` |
| `heading-font-weight` / `heading-font-weight-bold` | `$h3/h5/h6-font-weight` / `$h1/h2/h4-font-weight`, `$headings-font-weight` |
| `font-size-base` | `$font-size-base` |
| `heading-scale` (`compact`/`normal`/`large`) | `$h1-size`…`$h5-size` from `$heading-scales`; `$h6-size` is `$font-size-base` in every scale, so an h6 (small uppercase label) is never smaller than body text |
| `navbar-font-color` | `$navbar-font-color`; empty = layout default (`#333`, pl3 `#fff`) |
| `navbar-active-color` | `$navbar-active-color` (main-level underline, dropdown hover, active offcanvas item, burger hover); empty = layout default (`$primary`, pl3 `#333`) |
| `breadcrumb-section-bg` | `$breadcrumb-section-bg`, applied once in `Theme/global.scss` for every layout (the plN files only style the breadcrumb itself; pl3 once lacked the rule and showed bk2k's `$breadcrumb-bg` instead) |
| `card-radius` | `$card-radius` (→ `$card-border-radius`) |
| `page-footer-background`, `page-footer-color`, `page-footer-color-header` | same names |

The defaults equal the previous `_layout-variables.scss` values, so sites
without these settings compile unchanged.

`heading-scale` sets the **desktop** size. pl0 and pl1 (and Bootstrap's own
headings in pl3) shrink fluidly below `$rfs-breakpoint` via RFS
(`calc(1.345rem + 1.14vw)` for a 2.2rem h1); pl2 switches in one step at `md`
(`$heading-mobile-scale`).

**Do not also set these in a mandant's `_variables.scss`.** That file assigns
without `!default` and therefore beats the injected setting in theme, global
and custom — but the layout compile never sees it, so the page would mix two
values. Values covered by a setting belong in the site settings only.

### Element background "primary light"

The backend's element background (`tt_content.background_color_class`)
offers an extra "primary light" (`primary-light`, added in
`Configuration/page.tsconfig`). `_layout-variables.scss` defines
`$primary-light` (`mix(#fff, $primary, 85%)`) and passes it to bk2k through
`$frame-backgrounds`, whose own `map-merge` adds it to primary/secondary/…,
so the theme compile emits `.frame-background-primary-light` with the same
frame-collapsing rules as the built-in colours. Text and link colours stay
the frame defaults. A mandant can set its own `$primary-light` in its
`_variables.scss`. The `demo` template's `theme.scss` does not import
`_layout-variables` and therefore has no such class.

### Footer meta bar

`global.scss` lays out bk2k's `footer-section-meta` (all mandants, all
layouts) as one row on ≥ md: copyright left, meta menu centred, social links
right (`grid-template-columns: 1fr auto 1fr`, so the menu stays centred
whatever the copyright length). Below md the blocks stack centred in the
order meta menu → social → copyright.

Imprint/privacy links therefore belong in the **meta menu**
(`page.theme.meta.enable`, `page.theme.meta.navigationValue`), not in the
footer navigation. A footer navigation with entries (and `theme.contact`) gets
a full-width row above; an empty one (`footernavigation.enable` is on by
default, so bk2k renders the wrapper even without `navigationValue`) is hidden
via `:not(:has(li))`. `theme.language` gets a full-width row below.

This is CSS only; bk2k's `Structure/FooterMeta.html` is not overridden, so
bk2k updates do not collide. bk2k wraps meta menu, copyright and social links
in `.footer-info-section`; `display: contents` on that wrapper turns them into
direct grid items of `.frame-inner` next to `.footer-menu-section`.

The background comes from bk2k's `$darker` (`#212529`) unless a layout sets
`$page-footer-meta-background` (`_layout-variables.scss`, default `#212529`).
pl1 sets it to `#333` and pl0 to `#1d232a` at the top of their `plN.scss` and
apply it to `.footer-section-meta` (also `--frame-background`); pl2/pl3 keep
bk2k's colour. Links in the meta bar are always white (`global.scss` sets
`--frame-link-color`/`--frame-link-hover-color` on `.footer-section-meta
.frame`); the text colour stays bk2k's, computed for `$darker`. Both assume a
dark background; a light value would also need `--frame-color` and the link
colour changed.

The link rules of pl1/pl2 (section 2: "2a" animated hover underline, "2b"
arrow icon before text links) are general and contain no meta-bar
exceptions. The exceptions live in `global.scss` under `.footer-section-meta`:
no arrow before `.footer-navigation-link` and the social links, no underline
on the social links (a Social Links content element in
`footer-section-content` keeps its underline). They need `!important`
because the layout compile loads after global and its selectors carry
`#page-footer` specificity. pl0 has only the underline (no arrow; an
external-link icon only on text links in `p`/`td`/`li`); pl3 has only the
external-link icon (no arrow, no underline), so there they are partly no-ops.

### Verifying a refactoring

`var/scsscheck/compile.php` (project root, run inside ddev:
`ddev exec php var/scsscheck/compile.php <site> <entry.scss>`) compiles one
entry the way bk2k does (injected site settings, `EXT:` imports, `url()`
rewriting).

## 9. Layout variants for Fluid (page level)

The `layout` site setting also selects Fluid overrides, mirroring the SCSS
cascade. Each variant is a folder `Resources/Private/_plN/` (lowercase, because
`{$layout}` is used verbatim and the server is case-sensitive; the leading
underscore keeps the variants grouped apart from `Layouts`, `Partials` and
`Templates` in any sort order) that mirrors the
base tree and holds **only the files it overrides**:

```
Resources/Private/_plN/Layouts/Page/
Resources/Private/_plN/Partials/BootstrapPackage/Page/
```

Fluid searches root paths from the highest index down, so the order is
**extension base → layout variant → mandant `fileadmin` override**
(`basis.typoscript`, `page.10`):

| Root | Base | `plN` | Mandant |
|---|---|---|---|
| `layoutRootPaths` | `.30` | `.31` | – |
| `partialRootPaths` | `.1` | `.2` | `.3` |

Content elements and plugins follow the same pattern; every variant root is
the base index plus one (or `10` for the Contact plugin):

| Root | Base | `plN` |
|---|---|---|
| `lib.contentElement` partials / layouts | `.30` | `.31` (`…/_plN/Partials/BootstrapPackage/ContentElements/`, `…/_plN/Layouts/ContentElements/`) |
| `menu_section`, `menu_section_pages` partials / layouts | `.30` | `.31` (repeated by hand, because bk2k copies `lib.contentElement` at parse time) |
| `sitepackage_hero`, `sitepackage_linkbox` templates | `.30` | `.31` (`…/_plN/Templates/ContentElements/`) |
| `plugin.tx_sitepackage_contact.view` templates / partials / layouts | `0` | `10` (`…/_plN/Templates/`, `Partials/`, `Layouts/`) |

A new bk2k CType that copies `lib.contentElement` needs its own `.31` lines.
