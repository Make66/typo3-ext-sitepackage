# Barrierefreiheits-Leiste (a11y toolbar)

Opt-in toolbar above the page header: search and contact links, a settings
panel (font size, letter spacing, typeface, contrast, large cursor, highlighted
elements, hide images, reset) and links to "Erklärung zur Barrierefreiheit"
and "Leichte Sprache". Settings are stored per visitor in `localStorage`
(`a11y-settings`) and applied as CSS custom properties / `data-a11y-*`
attributes on `<html>`.

Extracted from calden's former fileadmin override
(`fileadmin/templates/calden/bootstrap_package/.../DropIn/Page/PageBefore.html`,
`a11y.js`, `_a11y.scss`), which had hard-coded page uids.

Independent of the legacy `tx_sitepackage.accessibility.*` constants /
`lib.accessibilityMenu` / `Navigation/Accessibility.html` menu used by the pl1
layout — that one is untouched.

## Site settings (category "Sitepackage › Barrierefreiheit")

| Setting | Type | Default | Effect |
|---|---|---|---|
| `sitepackage.a11y.enable` | bool | `false` | Renders the toolbar, loads `a11y.js` and the `a11y` CSS entry. |
| `sitepackage.a11y.searchPid` | int | `0` | Search link (hidden on mobile); also hides that page's main-menu item on desktop, see "Search page in the main menu". |
| `sitepackage.a11y.contactPid` | int | `0` | Contact link (hidden on mobile). |
| `sitepackage.a11y.statementPid` | int | `0` | "Erklärung zur Barrierefreiheit" link in the panel. |
| `sitepackage.a11y.easyLanguagePid` | int | `0` | "Leichte Sprache" link in the panel. |
| `sitepackage.a11y.position` | enum `top`/`right` | `top` | Toolbar placement, see "Positions" below. |
| `plugin.bootstrap_package.settings.scss.a11y-item-bg` | string | `#e9ecef` | Background of the toolbar boxes. Icon and label colors follow it automatically, see below. |

The icon color is derived from `a11y-item-bg` at compile time (`$a11y-item-bg-dark`: white has
the better contrast ratio against it than `$primary`):

- **dark background** → icons white (`right`: label text white); on hover the background stays
  unchanged, only the icon grows.
- **light background** → icons `$primary` (`right`: label text `$primary`); on hover the box darkens
  slightly (5 %) and the icon grows.

`$a11y-item-icon-color`, `$a11y-item-hover-bg` and `$a11y-side-item-color` can still be overridden.

The panel's corner radius (`$a11y-panel-radius`) follows the cards setting
`plugin.bootstrap_package.settings.scss.card-radius` ("Cards Eckenradius"); without it, Bootstrap's
`$border-radius` applies.

A page uid of `0` hides that link. Example (calden):

```yaml
sitepackage.a11y.enable: true
sitepackage.a11y.searchPid: 78
sitepackage.a11y.contactPid: 258
sitepackage.a11y.statementPid: 79
sitepackage.a11y.easyLanguagePid: 58
plugin.bootstrap_package.settings.scss.a11y-item-bg: '#dde9e0'
```

## Search page in the main menu

Mandants keep a "Suche" page (with icon) in the main navigation so mobile
visitors, who don't get the toolbar's search link, can reach it. On desktop
the toolbar already has a search link, so the menu item is hidden from `lg`
(992px) up.

`a11y.typoscript` emits this as `page.cssInline.20` (TYPO3 writes it to a
`typo3temp/assets/css/*.css` file) when `enable` is set and `searchPid > 0`:

- `#nav-item-<searchPid>`: `justify-content: left`; `display: none` from 992px.
- `.navbar-transition #nav-item-<searchPid> .nav-link-icon svg`: `fill: #333`.

`#nav-item-<uid>` is the id bk2k's main navigation puts on each `<li>`. Before
this, every mandant's `custom.scss` had the rule with the uid hard-coded
(`#nav-item-137` kleinich, `#nav-item-78` calden); setting `searchPid` in the
site's `settings.yaml` is now enough. Mandant tweaks (e.g. a different icon
colour) still go in `custom.scss`, but note that the inline CSS file is linked
*after* `custom.css`, so an override needs a more specific selector (e.g.
`body #nav-item-…`), not just the same one.

Why `cssInline` and not SCSS: bk2k's `CompileService::getVariablesFromConstants()`
passes `plugin.bootstrap_package.settings.scss.*` constants to the compiler
unresolved, so `search-pid = {$sitepackage.a11y.searchPid}` arrives as the
literal string and scssphp fails on the selector (HTTP 500). Constants are
resolved in TypoScript setup, so the rule lives there; `992px` is Bootstrap's
`lg` breakpoint written out because no SCSS mixin is available.

## Positions

Both variants use the same `Toolbar.html` and `a11y.js`; the template puts
`a11y-window--<position>` on `#a11y-window` and picks the icon set in its
`Icon` section, everything else is SCSS.

- `top`: boxes hang from the top edge above the header (desktop) and shrink
  the header's top padding via `:root:has(.a11y-window--top)`. Not in the
  scrolled state (`.navbar-transition`): the toolbar is out of view there, so
  the bootstrap_package's equal top/bottom padding applies again.
- `right` (kleinich): on desktop the boxes stack vertically, fixed and
  vertically centred at the right viewport edge. The `.a11y-label` text
  (visually hidden otherwise) slides out together with its box on hover/focus (and while the panel is open), icon left, text right; the panel
  opens fixed to the left of the bar. Below `$grid-float-breakpoint` it
  behaves exactly like `top` (toggle next to the burger).
  Icons are white Lucide-style outlines (ISC license) in a `$primary` circle
  (`.a11y-circle`); `top` keeps the original SVGs. In high-contrast mode
  the circle becomes an outline. Sizes and colours: `$a11y-side-item-size`,
  `$a11y-side-circle-size`, `$a11y-side-icon-size`, `$a11y-side-circle-bg`,
  `$a11y-side-circle-color`, `$a11y-side-item-color` (label text) (overridable like the other `$a11y-*` variables).

The `.a11y-tools` column is full-height with `pointer-events: none` instead of
`transform`-centred, because a transformed ancestor would turn the panel's
`position: fixed` into positioning relative to the bar.

## Files

| File | Role |
|---|---|
| `Configuration/TypoScript/Setup/a11y.typoscript` | Maps the settings to `page.10.settings.a11y.*`; adds `page.includeCSS.a11y` when enabled (re-adds `custom` after it so the mandant's `custom.scss` still wins); `page.cssInline.20` for the search menu item. |
| `Resources/Private/Partials/BootstrapPackage/Page/DropIn/Page/PageBefore.html` | bk2k DropIn; only renders `A11y/Toolbar`. |
| `Resources/Private/Partials/BootstrapPackage/Page/A11y/Toolbar.html` | Markup; renders nothing unless `settings.a11y.enable`. |
| `Resources/Public/JavaScript/a11y.js` | Loaded in `<head>` (`priority="1"`) so stored settings apply before first paint. |
| `Resources/Public/Scss/A11y/a11y.scss` | Standalone SCSS entry (Bootstrap functions/variables/maps/mixins + bk2k variables, then `_toolbar`). |
| `Resources/Public/Scss/A11y/_toolbar.scss` | All styles; every `$a11y-*` variable is `!default`. |
| `Resources/Public/Icons/A11y/*.svg` | Mask icons for the panel headings/links. |

## Customising per mandant

- The CSS entry is its own compile, so it does **not** see the mandant's
  `_variables.scss` (same situation as the layout entry, see
  `SCSS_pipeline.md` section 8). Colors come from
  `plugin.bootstrap_package.settings.scss.*` (`$primary` etc.), which bk2k
  injects into every compile. Any other `$a11y-*` variable
  (`a11y-item-size`, `a11y-item-gap`, `a11y-item-color`, `a11y-label-duration`, `a11y-label-easing`,
  `a11y-item-icon-color`, `a11y-burger-width`, `a11y-burger-gap`,
  `a11y-bar-height`, `a11y-navbar-right`, `a11y-icon-path`) can be set the same
  way in the site's `settings.yaml`; only `a11y-item-bg` has a settings
  definition (so it shows in the backend settings editor).
- Rule-level overrides go in the mandant's `custom.scss`.
- A mandant that needs its own `DropIn/Page/PageBefore.html` in fileadmin
  should keep `<f:render partial="A11y/Toolbar" arguments="{_all}" />` in it.
- The partial name `_toolbar.scss` is deliberate: a `_a11y.scss` next to
  `a11y.scss` makes `@import "a11y"` ambiguous for scssphp.
