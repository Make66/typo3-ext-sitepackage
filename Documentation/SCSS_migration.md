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

3. **Tell real customization apart from shared/global defaults.** Not every
   constant that differs from vanilla Bootstrap is mandant-specific — some
   (observed: `$blue: #164194`, `$red: #e30613`) are identical across
   *every* taketool mandant checked, i.e. project-wide defaults set once by
   the sitepackage/bootstrap_package extension's own shipped constants, not a
   per-site choice. Confirm by spot-checking 2-3 other **live** mandant sites
   the same way as step 2. Only port values into the new `_variables.scss`
   that actually vary per site (typically: `$primary`, `$secondary`, and
   whichever of `$teal`/`$pink`/`$indigo`/`$yellow` the site's design reuses
   as accent colors — these four vary wildly site to site, unlike blue/red).
   One variable is **not optional**: `$navbar-light-hover-color` has no
   `!default` anywhere in the chain (`_navbar.scss`, sitepackage's
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

## Non-SCSS prerequisites (already fixed project-wide, but good to know)

Migrating raumuehle and weingut surfaced three bugs that have nothing to do
with SCSS but block any mandant that uses `gridelements_pi1` content elements
(grid/column layouts like "2cols", "4cols", etc.) — i.e. most of them. All
three are now fixed centrally, so a fresh migration shouldn't need to touch
them again, but if any of these errors resurface (e.g. after a from-scratch
`composer install`, or on a mandant that hasn't hit this code path before),
this is where to look — don't re-diagnose from scratch:

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

Both `@import` fixes and the `_variables-dark` fix from step 8 above only
needed doing once, in shared files (`basis.typoscript`, sitepackage's
`composer.json`, sitepackage's `global.scss`) — a mandant migration itself
doesn't need to repeat any of this. It's documented here so that if a similar
"works for one mandant, breaks on another" or "silently missing package"
report comes up again, the `replace`/missing-`@import` pattern is the first
thing to check, not the last.

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
