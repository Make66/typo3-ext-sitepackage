# Migrating a mandant to the new SCSS standard

Background: see [SCSS_pipeline.md](./SCSS_pipeline.md) for how compilation
actually works (TypoScript wiring, compile trigger, cascade order, RFS).
This document is the practical how-to for moving one `fileadmin/templates/<mandant>/`
folder from the legacy two-file layout to the current standard, traced against
`fileadmin/templates/calden` (see the ddev instance at `/Users/martin/dev/ddev/t3v14`).

## Old vs. new layout

Legacy (pre-migration) mandant folder:

```
fileadmin/templates/<mandant>/
  theme.scss   # imports bootstrap5 theme + sitepackage global + ./custom, via
               # relative typo3conf/ext/... paths, all three in one compile
  custom.scss  # site-specific rules, relies on being tacked onto theme.scss's
               # chain for $primary/$secondary and bootstrap mixins
```

New standard (what `basis.typoscript` actually wires up — three **independent**
compiles, each starting fresh):

```
fileadmin/templates/<mandant>/
  _variables.scss  # brand variables — imported first by all three files below
  _fonts.scss       # @font-face rules for fonts this mandant actually uses
  Fonts/            # the woff/woff2 files _fonts.scss points at
  theme.scss        # @import "variables"; @import "EXT:bootstrap_package/.../theme";
  global.scss       # @import "variables"; @import "_fonts";
                     # @import "EXT:sitepackage/Resources/Public/Scss/Theme/global";
  custom.scss        # @import "variables"; then the bootstrap functions/
                     # variables/maps/mixins partials (needed because this file
                     # is its own compile now, not appended after theme.scss);
                     # then all of the mandant's own rules, unchanged
  img/logo.svg       # navbar-brand logo — wired globally in basis.typoscript
                     # (works for every mandant, no per-mandant TypoScript needed)
  img/logo_dark.svg  # OPTIONAL inverted/scrolled-state logo — new standard
                     # filename (see step 10); needs one explicit condition
                     # block added to basis.typoscript per mandant that has one
```

`page.includeCSS` in `Configuration/TypoScript/Setup/basis.typoscript` already
expects `theme.scss`, `global.scss` *and* `custom.scss` at
`fileadmin/templates/{$mandant}/` — if `global.scss` is missing, that include
just silently fails to resolve. Any mandant still on the legacy layout is
already slightly broken by this file's presence; migrating fixes it.

## Step-by-step

1. **Inventory the mandant folder.** `find fileadmin/templates/<mandant> -type f`.
   Read the existing `theme.scss` and `custom.scss` in full.

2. **Find the mandant's real brand values.** This is the step that's easy to
   get wrong. Legacy mandants generally have **no** `_variables.scss` — their
   `$primary`/`$secondary`/etc. come from a TypoScript Constants Editor record
   in the database (`plugin.bootstrap_package.settings.scss.*`), injected at
   compile time by `bk2k\BootstrapPackage\Classes\Service\CompileService::getVariablesFromConstants()`
   (`overrideParserVariables`, on by default). That record is invisible in the
   filesystem. Two ways to recover it:
   - Cheapest: `grep -rn "plugin.bootstrap_package.settings.scss" var/cache/code/typoscript/constant-*.php`
     if a cache file for that site happens to exist locally, then pull out
     `s:N:"plugin.bootstrap_package.settings.scss.<key>";s:N:"<value>"` pairs.
   - Reliable: fetch the mandant's **live** compiled `theme.css` and read the
     `--bs-*` custom properties directly, e.g.:
     ```
     curl -s -A "Mozilla/5.0" https://www.<mandant-domain>/ \
       | grep -oE 'href="[^"]*theme[^"]*\.css[^"]*"'
     curl -s -A "Mozilla/5.0" "https://www.<mandant-domain>/<that path>" \
       | grep -oE -- '--bs-[a-z-]+:[^;]+;'
     ```
     This is ground truth for what's actually rendering today and doesn't
     depend on a cache file existing.

3. **Do not assume a value is a safe-to-omit "shared default" just because
   several sites show the same number.** An earlier version of this guide
   claimed `$blue: #164194`, `$red: #e30613`, `$gray-100: #f1f1f1`,
   `$font-size-base: 1.1rem`, and the h1–h5 `1.75/1.5/1.25/1/0.9` formula were
   project-wide **code** defaults (`!default` values shipped by
   bootstrap_package/sitepackage) safe to leave out of `_variables.scss`. That
   was wrong, and the mistake shipped into several already-migrated mandants
   before being caught (see "Optional follow-up" below) — **`waschmaschinendoktor`
   was live-broken for a period as a direct result**: `$blue`/`$red`/`$gray-100`
   fell back to raw Bootstrap (`#0d6efd`/`#dc3545`/`#f8f8f8`) and
   `$font-size-base` fell back to unscaled `1rem` (cascading into every h1–h6
   size and several Bootstrap component font-sizes) the moment its legacy
   TS-constants block — the *only* place those values actually lived — was
   removed. They looked "shared" only because most sites' individual
   TS-constants records happened to repeat the same copy-pasted starter values
   for those specific keys, not because any SCSS file anywhere defines them
   with `!default`.

   The only way to know if a value is a *real* code-level default: grep for it
   with `!default` in `vendor/bk2k/bootstrap-package/Resources/Public/**/_variables*.scss`
   (contrib and bk2k's own). If it's not there, it is **not safe to omit**,
   no matter how many sites you spot-check and find it identical on — port it
   into `_variables.scss` explicitly. In practice this means porting
   `$blue`/`$red`/`$gray-100`/`$font-size-base`/the h1–h5 formula/
   `$navbar-nav-link-padding-x` (or whatever flat heading sizes the site
   actually uses) for **every** mandant, not just the ones that look different
   from their neighbors — `$navbar-nav-link-padding-x` was the last one of
   these to be caught (vendor default is `1rem`; every mandant checked so far
   actually uses `0.8rem`, which is *not* the default, it's just as
   consistently duplicated across TS-constants blocks as the others). `$primary`,
   `$secondary`, and whichever of `$teal`/`$pink`/`$indigo`/`$yellow` the
   site's design reuses as accent colors are still the values that vary most
   dramatically site to site, but that doesn't mean the rest are safe
   defaults — verify against the vendor source, not against a handful of
   other sites.

   One variable is **not optional** regardless: `$navbar-light-hover-color`
   has no `!default` anywhere in the chain (`_navbar.scss`, sitepackage's
   `Theme/global.scss`) — omitting it is a hard compile error, not a visual
   regression.

4. **Write `_variables.scss`.** Follow calden's structure/comment style, but
   only with the mandant's actual differing values from step 2/3 — don't
   copy calden's own numbers (its `#2b9a4b` primary, `2.8rem` h1, footer
   colors etc. are calden-specific, not template defaults). If the mandant's
   `custom.scss` doesn't itself override heading sizes with flat values (see
   `SCSS_pipeline.md` §6-7 on RFS), keep heading sizes as
   `$font-size-base`-relative formulas rather than switching to calden's flat
   `rem` style — that's a deliberate calden-only design choice, not part of
   the standard.

5. **Handle fonts.** If the mandant used a font previously supplied by the
   legacy shared `EXT:sitepackage/Resources/Public/Scss/Theme/_fonts.scss`
   (check `packages/sitepackage/Resources/Public/Fonts/` for the source
   files), that font is **no longer bundled** by the new `global.scss` — copy
   the woff/woff2 files into the mandant's own `Fonts/` and write a matching
   `_fonts.scss` with `url('Fonts/<name>.woff2')`-style relative paths (see
   calden's `_fonts.scss`). Only declare the weights/styles that actually
   have files — don't invent a bold/italic face that doesn't exist.

6. **Rewrite `theme.scss`:**
   ```scss
   @import "variables";
   @import "EXT:bootstrap_package/Resources/Public/Scss/bootstrap5/theme";
   ```

7. **Create `global.scss`** (new file, didn't exist before):
   ```scss
   @import "variables";
   @import "_fonts";
   @import "EXT:sitepackage/Resources/Public/Scss/Theme/global";
   ```
   Omit `@import "_fonts";` only if the mandant truly has no custom fonts.

8. **Rewrite `custom.scss`'s header only** — prepend, don't touch the rules
   below it:
   ```scss
   @import "variables";
   @import "EXT:bootstrap_package/Resources/Public/Contrib/bootstrap5/scss/_functions";
   @import "EXT:bootstrap_package/Resources/Public/Contrib/bootstrap5/scss/_variables";
   @import "EXT:bootstrap_package/Resources/Public/Contrib/bootstrap5/scss/_variables-dark";
   @import "EXT:bootstrap_package/Resources/Public/Contrib/bootstrap5/scss/_maps";
   @import "EXT:bootstrap_package/Resources/Public/Contrib/bootstrap5/scss/_mixins";
   ```
   The `_variables-dark` line matters on this project's installed
   `bk2k/bootstrap-package` version (`dev-BP_13_0`, i.e. 13.0.x): its own
   `_maps.scss` unconditionally reads dark-mode emphasis variables (e.g.
   `$primary-text-emphasis-dark`) inside an `@if $enable-dark-mode { … }`
   block (`$enable-dark-mode` defaults to `true`), and unlike bootstrap-package
   16.x (used by t3v14/calden), this version's own `_variables.scss` does
   **not** import `_variables-dark` for you — so without this explicit line
   the compile fails with `Undefined variable $primary-text-emphasis-dark`
   (and similarly for every other theme color) as soon as `_maps.scss` loads.
   The full canonical "Configuration" import stack, straight from this
   version's own `Contrib/bootstrap5/scss/bootstrap.scss` aggregator, is
   `functions → variables → variables-dark → maps → mixins → utilities` — if
   you hit a similar undefined-variable error on some other partial, check
   that file against this list before adding anything ad hoc.
   This same gap existed in `packages/sitepackage/Resources/Public/Scss/Theme/global.scss`
   (the shared file every mandant's `global.scss` pulls in) and was patched
   there too — don't reintroduce it by copying an older mandant's header.
   This is required because `custom.scss` is now its own independent compile
   (see `SCSS_pipeline.md` §1/§4) rather than tacked onto the end of
   `theme.scss`'s chain — without it, any `@include media-breakpoint-up(...)`
   or bare `$primary` reference in the mandant's existing rules will fail to
   compile. Leave every existing rule below the header byte-for-byte as it
   was; this migration changes wiring, not design.

9. **Verify.** Confirm the file list now matches calden's shape
   (`_variables.scss`, `_fonts.scss` + `Fonts/`, `theme.scss`, `global.scss`,
   `custom.scss`), and diff the new `custom.scss` against the original to
   confirm only the header changed.

10. **Check the logo actually renders.** If the navbar shows the site title as
    text instead of an image, `page.logo.file` (a TS Constants Editor value,
    invisible in files) is probably still the literal, never-expanded string
    `fileadmin/templates/{$mandant}/img/logo.svg` — see the first bullet under
    "Non-SCSS prerequisites" below; this is already patched globally in
    `basis.typoscript`, so if it's still broken for a *new* mandant, look for
    a differently-broken constant rather than assuming the global fix regressed.

    The **inverted/scrolled-state logo** (`navbar-brand-logo-inverted`,
    swapped in via `.navbar-transition` CSS once the user scrolls) is a
    separate, per-mandant opt-in. Sitepackage's new standard filename is
    `fileadmin/templates/<mandant>/img/logo_dark.svg` — if the mandant has
    one, add a condition block to `basis.typoscript` (next to raumuehle's):
    ```typoscript
    ["{$mandant}" == "<mandant>"]
        page.10.dataProcessing.1553883874.files.inverted = fileadmin/templates/{$mandant}/img/logo_dark.svg
    [END]
    ```
    Two syntaxes that look correct here are not, both discovered live while
    wiring this up for raumuehle:
    - `[{$mandant} == "<mandant>"]` (no quotes around the constant) —
      TypoScript substitutes it as a bare, unquoted token
      (`[raumuehle == "raumuehle"]`), invalid ExpressionLanguage syntax, and
      the condition just silently evaluates false — no error, no log entry,
      the override simply never appears in the compiled
      `var/cache/code/typoscript/setup-*.php`. Always quote the constant
      yourself: `"{$mandant}"`.
    - `[site.identifier == "<mandant>"]` — looks like the "proper" TYPO3
      condition variable for this, but on this TYPO3 version it throws a hard
      `Error: Cannot access protected property
      TYPO3\CMS\Core\Site\Entity\Site::$identifier` (a real 500, not a silent
      no-op) — the ExpressionLanguage variable resolver here does direct
      property access rather than calling the public `getIdentifier()`
      getter. Don't use it; the quoted-constant form above is what works.
    Don't invent a universal `logo_dark.svg`-for-everyone default the way the
    normal logo was fixed — filenames for the inverted logo weren't
    consistent before this (calden uses `logo_inverted.svg`), so a global
    default would silently 404 for every mandant not using this exact name.

## Non-SCSS prerequisites (already fixed project-wide, but good to know)

Migrating raumuehle and weingut surfaced several bugs that have nothing to do
with SCSS but block things most mandants use — grid/column content elements
("2cols", "4cols", etc.) and the navbar-brand logo. All are now fixed
centrally, so a fresh migration shouldn't need to touch them again, but if
any of these errors resurface (e.g. after a from-scratch `composer install`,
or on a mandant that hasn't hit this code path before), this is where to
look — don't re-diagnose from scratch:

- **`gridelementsteam/gridelements` silently never installed.**
  `packages/sitepackage/composer.json` had `"replace": {"gridelementsteam/gridelements": "*", ...}`
  — Composer's `replace` means "I already am this package," so the real
  extension (wired up as a path repo at `packages/gridelements`, required
  directly in root `composer.json`) was never actually pulled into `vendor/`,
  with no error or warning anywhere. Fixed by dropping that one line from
  sitepackage's `replace` block, then `ddev composer update
  gridelementsteam/gridelements taketool/sitepackage`. If some *other*
  extension ever fails to install with no explanation, check `replace` blocks
  across `packages/*/composer.json` before assuming a version-constraint
  problem.

- **Frontend: `Default->2cols` / `Default->3cols` / `Default->4cols` etc.
  throw `InvalidTemplateResourceException`.** `gridelementsteam/gridelements`
  is a generic grid *engine* — it ships no layout HTML itself. The actual
  `Default/2cols.html` etc. partials come from the separately-installed
  `laxap/bootstrap-grids` package, but that package's own static TypoScript
  (`Configuration/TypoScript/Frontend/setup.typoscript`, which registers
  `EXT:bootstrap_grids/Resources/Private/Templates/` into
  `tt_content.gridelements_pi1.templateRootPaths`) was never included anywhere
  — no DB "Include static" selection, no `@import`. Fixed with one line near
  the top of `packages/sitepackage/Configuration/TypoScript/Setup/basis.typoscript`:
  ```
  @import 'EXT:bootstrap_grids/Configuration/TypoScript/Frontend/setup.typoscript'
  ```

- **Backend: Page module (Web > Layout) throws `Default->BackendContainer`
  "No paths configured".** Separate bug, same shape. The grid-container
  preview rendered inside the TYPO3 backend Page module reads its Fluid view
  config from `module.tx_gridelements.backendContainer.view.*` TypoScript
  (`Classes/PageLayoutView/GridelementsPreviewRenderer.php`), which
  `gridelementsteam/gridelements` itself ships in
  `Configuration/TypoScript/backend.typoscript` — also never included. Fixed
  with a second import right after the one above:
  ```
  @import 'EXT:gridelements/Configuration/TypoScript/backend.typoscript'
  ```
  (Use `backend.typoscript`, not `backend11.typoscript` — the latter is the
  TYPO3 v11 template set; this project is v12.)

- **Desktop nav menu wraps onto multiple lines instead of laying out in one
  row** (only becomes visible once a mandant's `global.scss` actually compiles
  — i.e. right after migrating it). The bug is in
  `packages/sitepackage/Resources/Public/Scss/Theme/_navigation-offcanvas.scss`,
  which patches Bootstrap's `.offcanvas` component to behave like a classic
  inline navbar above `$grid-float-breakpoint` (sitepackage's own `Main.html`
  wraps the menu in `<nav class="offcanvas offcanvas-end navbar-collapse">`
  instead of bk2k's default `<nav class="collapse navbar-collapse">` — compare
  `packages/sitepackage/Resources/Private/Partials/BootstrapPackage/Page/Navigation/Main.html`
  against the shipped default at
  `vendor/bk2k/bootstrap-package/Resources/Private/Partials/Page/Navigation/Main.html`).
  The file's own comment claimed to mirror `components/navbar/_responsive.scss`
  — a file that **does not exist** in this project's installed
  `bk2k/bootstrap-package` (`dev-BP_13_0`); it's only real in newer releases
  (16.x, used by t3v14/calden). In 13.0.x the desktop flex-row switch lives in
  `vendor/bk2k/bootstrap-package/Resources/Public/Scss/components/_navbar.scss`
  and only applies `flex-direction: row` to `.navbar-nav` via a selector
  requiring a `.collapse`-classed ancestor
  (`.container > .collapse > .navbar-nav`) — our offcanvas nav has
  `.navbar-collapse` but not `.collapse`, and adds an extra `.offcanvas-body`
  wrapper the selector doesn't account for either way, so it never matches.
  `.offcanvas-body` and `.navbar-nav` both silently fall back to Bootstrap's
  default `flex-direction: column`. Fixed by adding the missing rules directly
  to the existing desktop media query in `_navigation-offcanvas.scss`:
  ```scss
  .offcanvas-body {
    display: flex;
    flex-direction: row;
    align-items: center;
    // ...existing padding/overflow-y/flex-grow...
    .navbar-nav {
      flex-direction: row;
    }
  }
  ```
  If bk2k/bootstrap-package ever gets upgraded past 13.0.x on this project, an
  upgrade to a version that actually ships `components/navbar/_responsive.scss`
  may make this override partially redundant — check bk2k's own selectors
  again before assuming the override is still needed as-is.

  Getting the row to lay out at all still left two follow-up gaps, fixed in
  the same rule block: the menu items had **no horizontal gap** (bk2k's own
  `.nav-link { padding-left/right: $navbar-nav-link-padding-x; }` lives inside
  that same non-matching selector, so it never applied either), and the whole
  menu was **left-aligned inside its own box instead of pinned to the right of
  the header** — Bootstrap core's `.navbar-collapse` sets `flex-grow: 1`, and
  bk2k's media query only resets `flex-basis` back to `auto`, never
  `flex-grow` back to `0`, so `<nav>` still stretches to fill the header row;
  without an explicit `justify-content`, row content just hugs that stretched
  box's own left edge rather than the header's right edge. Fixed with:
  ```scss
  .offcanvas-body {
    // ...display/flex-direction/align-items from above...
    justify-content: flex-end;
    .navbar-nav {
      flex-direction: row;
      gap: $navbar-nav-link-padding-x; // reuses the mandant's own variable
    }
  }
  ```
  That still left the menu visually centered rather than right-aligned — a
  *third* layer of Bootstrap default, on `<nav>` itself this time, not
  `.offcanvas-body`. Bootstrap's base `.offcanvas` rule (as opposed to
  `.offcanvas-body`) is **unconditional**: unlike `.offcanvas-lg`/`-xl` etc.,
  plain `.offcanvas` never auto-expands at any breakpoint by Bootstrap's own
  design, so its `display: flex; flex-direction: column;` applies to `<nav>`
  at every width, and nothing above resets it. With `<nav>` stuck in column
  mode, Bootstrap core's `.navbar-collapse { align-items: center; }` — same
  `<nav>` element, since it carries both classes — centers on the *cross*
  axis, which in column mode is horizontal, so `.offcanvas-body` (the only
  visible child once `.offcanvas-header` is hidden) gets horizontally
  centered inside `<nav>` regardless of its own `justify-content`. Fixed by
  adding to the outer `.navbar-mainnavigation #mainnavigation.offcanvas` rule
  (not the nested `.offcanvas-body` one):
  ```scss
  .navbar-mainnavigation #mainnavigation.offcanvas {
    // ...position/visibility/etc. from above...
    flex-direction: row;
    justify-content: flex-end;
  }
  ```
  `flex-direction: row` turns that inherited `align-items: center` back into
  ordinary vertical centering, and `justify-content: flex-end` then pins the
  shrink-wrapped `.offcanvas-body` to the right of the now-stretched `<nav>`
  box. Moral for next time: when overriding Bootstrap's offcanvas for this
  pattern, check `.offcanvas` (the panel element) and `.offcanvas-body` (its
  inner content wrapper) as two *separate* rule sets needing their own
  `flex-direction` reset — fixing one and assuming it covers the other is
  exactly what caused this to take three passes.

- **Navbar-brand logo renders as text (site title) instead of an image.**
  `page.logo.file` is a TS Constants Editor value (invisible in files) that,
  for at least one site, was literally set to the never-expanded string
  `fileadmin/templates/{$mandant}/img/logo.svg` — someone tried to use
  `{$mandant}` substitution *inside a constant's own value*, but TypoScript
  only resolves `{$...}` references while parsing **setup**, never while
  parsing another constant's default. `BK2K\BootstrapPackage\DataProcessing\StaticFilesProcessor`
  then silently fails to load a file literally named `{$mandant}`, so
  `logo.normal` ends up empty and Fluid's `<f:if condition="{logo.normal}">`
  falls through to `<span>{siteTitle}</span>`. Fixed by overriding the
  DataProcessor's input directly in `basis.typoscript` (setup, where
  `{$mandant}` *does* resolve — same mechanism `includeCSS` already relies
  on), bypassing the broken constant for every mandant at once, assuming the
  `fileadmin/templates/<mandant>/img/logo.svg` convention every mandant
  folder checked so far already follows:
  ```typoscript
  page.10.dataProcessing.1553883874.files.normal = fileadmin/templates/{$mandant}/img/logo.svg
  ```
  The **inverted** logo (`files.inverted`) is deliberately *not* defaulted the
  same way — see step 10 above for why, and for the per-mandant condition
  syntax (and the two ways to get that condition wrong that were discovered
  live while wiring up raumuehle's).

  **Update — this is not a one-off, and the blanket default above is itself
  only a partial fix.** A full DB audit (`sys_template.constants` for all 37
  sites, cross-referenced against each mandant's actual `img/` folder) found
  the broken `{$mandant}`-in-a-constant mistake in `page.logo.file` on ~30 of
  37 sites, not just raumuehle — evidently a mistake made consistently by
  whoever set these sites up, not an isolated typo. The blanket
  `.../img/logo.svg` default above happens to match what ~22 of those broken
  constants actually intended (they use `.svg`), which is *why* it looked like
  a complete fix at the time. It silently breaks the other ~8, whose real logo
  is `.png` (or, for `waschmaschinendoktor`, a **correct, literal, non-broken**
  constant that the blanket default was clobbering regardless — it doesn't
  check whether a site's own constant is fine before overriding it). Fixed
  with one `["{$mandant}" == "<mandant>"]` condition block per affected site,
  right after the blanket default, each pointing at that mandant's real file:
  ```typoscript
  ["{$mandant}" == "cruisensight"]
      page.10.dataProcessing.1553883874.files.normal = fileadmin/templates/{$mandant}/img/logo.png
  [END]
  ```
  (repeated for `theis`, `sdw`, `cme-beratung`, `lebenslagen`, `ogr-mainz`,
  `rv-oberstedten`, `waschmaschinendoktor`). Deliberately *not* fixed this way:
  narrowing the blanket default to an empty-value-only fallback instead would
  have been architecturally cleaner, but would have **newly broken** the ~22
  sites currently relying on the blanket default to paper over their own
  broken `{$mandant}` constant — those weren't touched, so don't remove or
  narrow the blanket default without first fixing (or replicating this same
  per-mandant override for) every site still depending on it. If another
  mandant turns up with a missing or wrong navbar logo, check its own
  `page.logo.file` constant (DB, not a file — `SELECT constants FROM
  sys_template WHERE uid = ...`) and its `img/` folder before assuming
  `.svg` is simply missing; add one more condition block here, not a new
  investigation from scratch.

- **`InvalidTemplateResourceException` for `Navigation/MainNavigationDropDown`
  on any page with a sub-page under a top-level nav item** (i.e. any mandant
  whose page tree isn't completely flat — found while bulk-migrating a batch
  of mandants, three of which happened to have this in their nav). Nothing to
  do with SCSS or compile order this time — `packages/sitepackage/Resources/Private/Partials/BootstrapPackage/Page/Navigation/MainNavigation.html`
  renders `<f:render partial="Navigation/MainNavigationDropDown" .../>`
  whenever a nav item has children, but that partial file simply never
  existed anywhere in the project — not in sitepackage, not in
  `vendor/bk2k/bootstrap-package` (whose own default `MainNavigation.html`
  inlines its dropdown `<ul class="dropdown-menu">` directly rather than
  delegating to a separate partial), not in any mandant override. A page
  without nested nav items never hits the `<f:if condition="{item.children}">`
  branch that renders it, which is why this went unnoticed until a mandant
  with a real sub-navigation got migrated. Fixed by creating
  `packages/sitepackage/Resources/Private/Partials/BootstrapPackage/Page/Navigation/MainNavigationDropDown.html`,
  mirroring bk2k's own dropdown item markup/classes (`dropdown-item`,
  `dropdown-icon`, `dropdown-text`) so it renders correctly with the Bootstrap
  CSS already in place — no new SCSS needed. It recurses into its own partial
  for a child's `children` (TYPO3's `MenuProcessor` is currently configured
  for `levels = 2` in `vendor/bk2k/bootstrap-package/Configuration/TypoScript/setup.typoscript`,
  so that recursion branch is inert today, but future-proofs it if `levels`
  is ever raised — MainNavigation.html's own `dropdownStyle` "mega" vs
  "simple" detection already anticipates that case). If you see this
  exception again, it means this file went missing again, not that a new
  mandant needs mandant-specific handling.

- **`InvalidTemplateResourceException` for `ContentElements/Frame/General/BackgroundImage`
  on any page whose content elements render through the animated frame
  layout** (found independently by three different batches during the same
  bulk migration this file's fixes came from). Not sitepackage's or bk2k's
  bug this time — the third-party extension `baschte/content-animations`
  (`vendor/baschte/content-animations`, `composer.json` requires
  `"baschte/content-animations": "^2.4"`) ships its own replacement
  `Layouts/ContentElements/Default.html` (one variant per TYPO3 version,
  `v10`/`v11`/`v12`) that adds animation support on top of bk2k's own
  content-element frame layout. Its `v12` variant renders
  `<f:render partial="Frame/General/BackgroundImage" .../>` — but that exact
  path never existed anywhere: bk2k's own real background-image partial is at
  `Partials/ViewHelpers/Frame/BackgroundImage.html` (a different render
  context, the `<bk2k:frame>` ViewHelper's own dedicated `partialRootPaths`,
  not `lib.contentElement`'s `ContentElements` ones this layout actually
  renders under), and the *only* thing under a literal `.../General/`
  subfolder is the unrelated `Carousel/General/BackgroundImage.html`. A
  content-animations version mismatch/typo, not something to fix upstream
  from here. Fixed by creating the three files the broken reference actually
  needs, under sitepackage's own `ContentElements` partial root (checked
  before bk2k's own, so this is additive, not an override of anything real):
  `Resources/Private/Partials/BootstrapPackage/ContentElements/Frame/General/{BackgroundImage,BackgroundImageStyle,BackgroundImageStyleNonce}.html`
  — straight copies of bk2k's own proven `ViewHelpers/Frame/Background*`
  content, just with the two inner `<f:render partial="...">` calls
  repointed from `Frame/BackgroundImageStyle(Nonce)` to
  `Frame/General/BackgroundImageStyle(Nonce)` to match where the sibling
  files actually ended up. If `baschte/content-animations` ever ships a fixed
  `v12/Default.html` (or gets upgraded past whatever version has this typo),
  these three files become redundant but harmless — check its changelog
  before assuming they're still needed.

All seven fixes above only needed doing once, in shared files
(`basis.typoscript`, sitepackage's `composer.json`, sitepackage's
`global.scss`, `_navigation-offcanvas.scss`, and the two new
`MainNavigationDropDown.html` / `Frame/General/Background*.html` partials) —
a mandant migration itself doesn't need to repeat any of this except the
one-line per-mandant condition for an
inverted logo (and, per the update above, an occasional per-mandant logo
*format* condition too). It's documented here so that if a similar "works for
one mandant, breaks on another" or "silently missing package/rule" report
comes up again, a version mismatch against bk2k/bootstrap-package, a missing
`@import`/`replace`, or a constant that can't actually do what its value
claims is the first thing to check, not the last.

### Update 2026-08-14: bk2k/bootstrap-package upgraded 13.0.x → 15.x

`composer.json` now requires `bk2k/bootstrap-package: ^15.0` (was
`dev-BP_13_0` when the fixes above were written). This is exactly the
scenario the offcanvas bullet above warned about — v15 does ship a
navbar-responsive layer 13.0.x lacked, so part of that override is now
redundant, but v15 also changed enough markup/CSS elsewhere to introduce new
bugs of its own. Found and fixed while debugging reports on
`buergerstiftung.ddev.site` of black-on-black (then white-on-white) dropdown
menus and a blank gap above the header:

- **`MainNavigationDropDown.html` is stale.** The partial documented above
  (mirroring bk2k 13.0.x's `dropdown-item`/`dropdown-icon`/`dropdown-text`
  markup) predates v15's own native dropdown partial, which uses different
  classes (`.nav-link-dropdown` inside a `.dropdown-nav` grid, not
  `.dropdown-item`). Renamed sitepackage's copy aside
  (`Navigation/MainNavigationDropDown.html.v13-override-disabled`) so v15's
  own partial resolves instead. Harmless where nothing else depends on the
  old classes; **not** effective on any mandant with its own fileadmin-level
  `Navigation/Main.html` override that renders `.dropdown-item` inline
  itself (see next point) — those never call this partial at all.

- **Dark-skin (`.navbar-inverse`) dropdown text unreadable — black-on-black,
  then white-on-white.** bk2k's `Scss/components/navbar/_style.scss` sets
  `--bs-dropdown-color`/`--bs-dropdown-bg` for the panel under
  `.navbar-inverse .dropdown-menu`, but never sets `--bs-dropdown-link-color`
  (a separate variable `.dropdown-item` uses for its own text,
  `Contrib/bootstrap5/scss/_dropdown.scss:36,181`) or `--bs-nav-link-color`
  (what v15's `.nav-link-dropdown` uses instead). Both default to
  `var(--bs-body-color)` — near-black — landing on the panel's now-dark
  background. First fix pass set item text to white on hover/focus but
  missed that `--bs-dropdown-link-hover-bg` (default `var(--bs-tertiary-bg)`,
  a light neutral) was *also* never overridden — white text on a near-white
  hover background. Fixed with a new partial,
  `Resources/Public/Scss/Theme/_navigation-dropdown-inverse-color.scss`
  (imported from `global.scss`), setting color **and** hover/focus
  background directly on both `.dropdown-item` and `.nav-link-dropdown`
  under `.navbar-inverse .dropdown-menu` — covers whichever of the (now
  three) navigation-partial variants a given mandant renders, without
  depending on bk2k's own variable chain for either.

- **Dropdown panel too narrow, cutting off longer item text.** bk2k's
  `.dropdown-menu { width: 100% }` (`Scss/components/navbar/_dropdown.scss`)
  is only reset to `width: auto` at desktop under a
  `.nav-style-simple`/`.nav-style-mega` class — added by bk2k's own
  `MainNavigation.html` alongside the dropdown-menu it wraps. Several
  mandants (`buergerstiftung` confirmed; likely `cruisensight`,
  `kulturleben-rheinhessen`, `stiftung-friedenskirche`, `event-florist` too —
  see their fileadmin-override notes in `MIGRATION_SUMMARY.md`) render
  navigation from their *own*, older, fully-inline
  `fileadmin/templates/<mandant>/.../Navigation/Main.html`, which predates
  that class convention and never adds it — so the panel stayed pinned to
  100% of its trigger `<li>`'s width (sized for the short top-level label),
  clipping longer submenu text. Fixed with a new partial,
  `Resources/Public/Scss/Theme/_navigation-dropdown-width.scss`, resetting
  width for any non-mega `.dropdown-menu` regardless of that class.

- **Blank gap above the header** (confirmed on `buergerstiftung`; likely
  present anywhere else the pattern below appears). bk2k's `_fixed.scss`
  changed `.navbar-fixed-top` from `position: fixed` (needs a page wrapper to
  manually reserve space beneath it) to `position: sticky` (self-reserving,
  in normal flow) somewhere between 13.0.x and 15.x. **35 of the ~37 mandant**
  `fileadmin/templates/<mandant>/custom.scss` **files** still carry a
  hand-tuned `.body-bg-top { padding-top: ...px; }` block (values from 70px
  to 280px, varying per breakpoint and per mandant) written for the old
  fixed-position behavior — now pure dead space stacked above the
  self-reserving sticky header. Fixed **only on `buergerstiftung`** so far
  (removed the block, replaced with a comment explaining why); the other
  ~34 mandants still have the stale padding and have not been touched —
  before bulk-fixing, confirm per-mandant whether `theme.navigation.type`
  actually uses the fixed/sticky variant (some may use plain `navbar-top`,
  where this padding could mean something else, e.g. clearing an
  intentionally oversized overlapping logo rather than compensating for
  fixed positioning) rather than assuming every occurrence of the pattern is
  the same bug.

None of this touched `vendor/bk2k/bootstrap-package` itself (wiped on every
`composer update`) — all four fixes live under `packages/sitepackage/Resources/`,
either a renamed-aside stale partial or new SCSS partials imported from
`global.scss`.

**Also noticed, not fixed as part of this**: `packages/sitepackage/ext_emconf.php`
still declares `'bootstrap_package' => '13.0.0-13.99.99'` as its TYPO3
extension dependency constraint, disagreeing with `composer.json`'s
`bk2k/bootstrap-package: ^15.0`. Worth reconciling before it causes a
confusing dependency-resolution error for someone who hasn't read this file.

## Optional follow-up: retiring a mandant's legacy TS-constants `scss` block

Every mandant migrated so far still has its *old* color/typography config
sitting untouched in `sys_template.constants` — a `plugin.bootstrap_package {
settings.scss { ... } }` block (DB only, invisible in files; find it via
`SELECT constants FROM sys_template WHERE uid = ...`, not by searching the
repo). `bk2k\BootstrapPackage\Classes\Service\CompileService::getVariablesFromConstants()`
injects every value in that block as a Sass variable *before* each of
`theme.scss`/`global.scss`/`custom.scss` compiles; `_variables.scss`'s own
plain (non-`!default`) assignments then run immediately after and win. So in
practice, for any value `_variables.scss` already sets, the matching TS
constant is dead — redundant, not broken, just a second source of truth that
can silently diverge from the first if someone edits the wrong one. This
doesn't fix itself just by migrating a mandant's SCSS files; the gap has to be
checked and closed deliberately, one mandant at a time.

**Worked example — `waschmaschinendoktor`, including a mistake worth learning
from.** Its TS-constants `scss` block had 20 keys. The first diff against its
(already-migrated) `_variables.scss` found four genuinely missing keys —
`frame-inner-spacing`, `navbar-dark-color`, `navbar-dark-hover-color`,
`navbar-dark-active-color` (this mandant's navbar is *always* in `inverse`
mode — `page.theme.navigation.style = inverse` — so those `navbar-dark-*`
values are its primary navbar text colors, not a rarely-seen mobile-only edge
case) — and treated the rest (`font-size-base`, the h1–h5 formula, `blue`,
`red`, `gray-100`) as safe-to-skip shared defaults, per what this doc claimed
at the time. That verification only checked `--bs-primary`/`--bs-secondary`,
the `.navbar-inverse` hover color, and `--frame-spacing-xs` — all of which
*did* match — so the block was removed from the DB looking fully verified.

It wasn't. Those five "shared default" values were never real Sass `!default`s
anywhere in the codebase (see step 3 above) — they were only ever supplied by
that TS-constants block, and the site went live with `--bs-blue:#0d6efd`,
`--bs-red:#dc3545`, `--bs-gray-100:#f8f8f8`, and `--bs-body-font-size:1rem`
(cascading into every heading size) instead of its real values, for however
long it took to notice. Fixed by porting all five into `_variables.scss` and
re-verifying. The lesson, now reflected in step 3: **diff the full `--bs-*`
custom-property set** (`curl ... | grep -oE -- '--bs-[a-z-]+:[^;]+;'`) between
before-block-removed and after, not just the handful of variables you already
suspect might be mandant-specific — a partial verification that happens to
pass is not the same as a complete one.

**Mechanics** (still correct, unchanged by the above): port every real gap
into `_variables.scss`, confirm the compiled CSS is *fully* identical to the
still-present TS-constants baseline — not just spot-checked — then remove only
the `plugin.bootstrap_package { settings.scss { ... } }` block from that
site's `constants` field via a direct, scoped `UPDATE sys_template ... WHERE
uid = <that site only>`, leaving `page.logo.*`, `page.theme.*`,
`plugin.tx_cookieconsent.settings`, etc. in the same field completely
untouched (unrelated to SCSS, out of scope). Read the field fresh immediately
before writing (PHP `PDO`, not the `mysql` CLI's batch/`-N` mode — that
escapes real newlines/tabs in the field as literal backslash sequences, which
will corrupt a naive re-parse of a multi-row export; fetching one field's true
value directly avoids the whole problem), diff old vs. new in full before
applying, and use a parameterized query — this field routinely contains `{`,
`}`, `$`, quotes, and German umlauts, none of which should be hand-escaped
into a shell command.

**Update — done for every migrated mandant.** All ~35 sites have now had this
same gap-audit-then-blank treatment (parallelized across several batches after
`waschmaschinendoktor` established the procedure). The false-"shared-default"
mistake described above recurred in every batch until the verification method
was corrected to diff the *complete* `--bs-*`/`--frame-*` set rather than a
handful of expected variables — by the end, confirmed real (non-`!default`)
gaps across the whole install consistently included `$blue`, `$red`,
`$gray-100`, `$font-size-base` + the h1–h5 formula (or a site's own flat
overrides), `$navbar-nav-link-padding-x`, and `$body-bg` when it wasn't
literally the vendor's own `#fff`/`#ffffff`. Every mandant's
`sys_template.constants` no longer has a `plugin.bootstrap_package {
settings.scss { ... } }` block — `_variables.scss` is now the sole source of
truth for brand/typography values across the entire project. `mk` (no site
config at all) was skipped, matching its exclusion from the original SCSS
migration.

If a *new* mandant is migrated in the future, its TS-constants block (if the
site was set up the old way, with one) still needs this same audit — don't
assume it's been retired just because every *currently existing* mandant has.

## Reusable prompt

Paste this (with the mandant name filled in) to migrate another folder:

> Migrate the SCSS files in `fileadmin/templates/<mandant>` to the new
> taketool TYPO3 standard demonstrated in `fileadmin/templates/calden`
> (ddev instance `/Users/martin/dev/ddev/t3v14`). Read
> `packages/sitepackage/Documentation/SCSS_migration.md` first and follow it
> step by step — in particular, recover `<mandant>`'s real brand colors from
> its live site's compiled `theme.css` (not just local cache files, which may
> not exist), don't invent values, and preserve every existing rule in
> `custom.scss` unchanged aside from the new required header.
