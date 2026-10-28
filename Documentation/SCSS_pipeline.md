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
| `Theme/plN.scss` | Complete, self-contained rule set of one layout: `_layout-variables`, Bootstrap tooling, the layout's mixins (`external`, `link-underline`, `link-icon`, `card-surface`, `card-hover`, `heading`), then all rules. pl1 and pl2 deliberately duplicate most rules so each layout can change independently. pl0/pl3 have no rules yet. |
| `<mandant>/_variables.scss` | Mandant values for `theme`, `global` and `custom`. No CSS output. |
| `<mandant>/custom.scss` | Mandant-only rules, and overrides of layout rules where the mandant needs its own fonts/sizes/colours/icons (it loads after the layout and sees `_variables.scss`). |

Rules are not scoped to `.plN`: no template sets that class, and the layout
CSS is only loaded for sites using that layout anyway.

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
| `heading-scale` (`compact`/`normal`/`large`) | `$h1-size`…`$h6-size` from `$heading-scales` |
| `navbar-font-color` | `$navbar-font-color` |
| `navbar-active-color` | `$navbar-active-color` (main-level underline, dropdown hover, active offcanvas item, burger hover); empty = `$primary` |
| `breadcrumb-section-bg` | `$breadcrumb-section-bg` |
| `card-radius` | `$card-radius` (→ `$card-border-radius`) |
| `page-footer-background`, `page-footer-color`, `page-footer-color-header` | same names |

The defaults equal the previous `_layout-variables.scss` values, so sites
without these settings compile unchanged.

`heading-scale` sets the **desktop** size. pl1 (and Bootstrap's own headings in
pl0/pl3) shrink fluidly below `$rfs-breakpoint` via RFS
(`calc(1.345rem + 1.14vw)` for a 2.2rem h1); pl2 switches in one step at `md`
(`$heading-mobile-scale`).

**Do not also set these in a mandant's `_variables.scss`.** That file assigns
without `!default` and therefore beats the injected setting in theme, global
and custom — but the layout compile never sees it, so the page would mix two
values. Values covered by a setting belong in the site settings only.

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
pl1 sets it to `#333` at the top of `pl1.scss` and applies it to
`.footer-section-meta` (also `--frame-background`); pl0/pl2/pl3 keep bk2k's
colour. Links in the meta bar are always white (`global.scss` sets
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
`#page-footer` specificity. In pl0/pl3, which have no such link rules, they
are no-ops.

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
