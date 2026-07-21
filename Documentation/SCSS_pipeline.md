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

This extension overrides that and adds two more entries, in
`Configuration/TypoScript/Setup/basis.typoscript:11-18`:

```
page.includeCSS {
    theme >
    theme  = fileadmin/templates/{$mandant}/theme.scss
    global >
    global = fileadmin/templates/{$mandant}/global.scss
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

**theme.css → global.css → custom.css**

`custom.scss` therefore has the highest cascade precedence, and the raw
Bootstrap5 `theme.scss` the lowest.

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
  custom.css`, see point 4) and this is a flat property assignment (no
  `rfs()`), it wins at every breakpoint and is what actually pins h1 to a
  flat size across all viewport widths. Without it, h1 would render
  RFS-scaled (noticeably smaller than 3rem on narrow viewports).
- calden's `custom.scss` also has a second, separate rule for
  `.element-subheader.h1` that mirrors the same `$h1-font-size` variable —
  keep it in sync if that class is in use.

**Practical rule of thumb:** change the number in `_variables.scss`, and make
sure the flat override rule in `custom.scss` still references the same
variable (don't hardcode a literal value there) so the two stay in sync.

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
