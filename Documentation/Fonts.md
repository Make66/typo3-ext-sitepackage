# Fonts (font library + site settings)

Web fonts live once, centrally, in the sitepackage and are selected per site by
site setting. A mandant folder no longer ships its own font files or
`_fonts.scss` (migrated: calden, kleinich, wasserliesch, podelzig). Migrated
sites set `page.theme.googleFont.enable: false`, otherwise bk2k additionally
loads its default Google font.

## Files

| File | Content |
|---|---|
| `Resources/Public/Fonts/` | All font files, **flat** (no subfolder per font), woff2 only. |
| `Resources/Public/Scss/Fonts/_font-library.scss` | `$font-library` map (key → `stack` + `files`), the key defaults, and the helper functions `font-library-stack()`, `font-library-family()`, `font-library-weight()`. No CSS output. |
| `Resources/Public/Scss/Fonts/fonts.scss` | Own `page.includeCSS.fonts` entry: emits `@font-face` only for the body and heading font chosen by the site. |
| `Configuration/TypoScript/Setup/fonts.typoscript` | Registers `fonts.scss`, only when the site picks a font other than `system`. |

`_font-library.scss` is imported by `Theme/_layout-variables.scss`, so every
compile (theme, global, layout, custom, fonts) resolves the same keys to the
same stacks and weights.

### Why fonts.scss is its own includeCSS entry

bk2k resolves `url()` relative to the **entry file** of a compile
(`ScssParser::compileScss`, `registerFunction('url', …)`), and only rewrites the
path if the file exists at that moment. Emitting `@font-face` from a mandant's
`global.scss` would resolve against `fileadmin/templates/<mandant>/`. With its
own entry in `Scss/Fonts/`, `url("../../Fonts/<file>")` always points at
`Resources/Public/Fonts/`.

## File naming

| Kind | Name |
|---|---|
| Variable font | `<Family>-VF.woff2` (e.g. `Overpass-VF.woff2`) |
| Variable font, italic | `<Family>-Italic-VF.woff2` |
| Static cut | `<Family>-<Cut>.woff2` (e.g. `Lato-Bold.woff2`) |
| Licence | `<Family>-OFL.txt` (SIL OFL requires the licence next to the files) |

Prefer a variable font: one file covers every weight in its range, so every
weight in the settings enum works. Not every family has one (e.g. Poppins, Arvo
are static only); then add one file per cut. Only woff2 — `.woff` is not needed by any supported browser.
Google Fonts ships TTF; convert with `woff2_compress <file>.ttf` or fonttools.
Avoid `[wght]` in file names (brackets in URLs).

## Adding a font

1. Put the woff2 file(s) into `Resources/Public/Fonts/`.
2. Add an entry to `$font-library`:
   ```scss
   inter: (
     stack: ("Inter", Arial, Helvetica, sans-serif),
     files: ("Inter-VF.woff2": 100 900)       // VF: weight range
   ),
   // static: one entry per file, value = its weight
   // files: ("Lato-Regular.woff2": 400, "Lato-Bold.woff2": 700)
   // optional italic, same format: italic: ("Inter-Italic-VF.woff2": 100 900)
   ```
   The first item of `stack` is the `font-family` name used in `@font-face`.
   One family name for all cuts — never `"Lato Bold"` as its own family,
   otherwise `font-weight` has no effect.
3. Add the key to the `enum` of both `font-body-key` and `font-heading-key` in
   `Configuration/Sets/Sitepackage/settings.definitions.yaml`.

## Available fonts

All variable, normal + italic: overpass, merriweathersans (300–800),
merriweather (serif, 300–900), sourcesans3 (200–900), roboto, robotocondensed,
urbanist, opensans (300–800), montserrat, notosans — 100–900 unless noted.
Urbanist replaced Poppins (static cuts, no 600).

## Weights

`font-weight-body`, `heading-font-weight` (h3, h5, h6) and
`heading-font-weight-bold` (h1, h2, h4) go through `font-library-weight()`: a
weight inside a file's range (VF) or equal to a static cut is used as is;
otherwise the nearest available weight is used and the compile logs
`WARNING: Schrift '…' hat keinen Schnitt …` (visible in `var/scsscheck/compile.php`
output). `system` has no files, so its weights pass through unchanged.

Links (`$font-link`) and footer icon-group headers (`$font-footer`) use the
body font and inherit the body weight; there is no separate weight setting.

## Caching

bk2k only recompiles when an imported SCSS file or a variable changes. Adding
or replacing a font **file** does not trigger a recompile, and a `url()` to a
file that did not exist at compile time stays unresolved. After adding a font
file, remove the compiled CSS:

```
rm public/typo3temp/assets/bootstrappackage/css/fonts-*
```

## Migrating a mandant

1. Make sure its fonts exist in `Resources/Public/Fonts/` and `$font-library`.
2. Set the Typografie site settings (`config/sites/<site>/settings.yaml`).
3. Remove fonts, weights, `$font-size-base` and `$h1-size`…`$h6-size` from
   `fileadmin/templates/<mandant>/_variables.scss` (a mandant value overrides
   the site setting in theme/global/custom but not in the layout — see
   `SCSS_pipeline.md` §8 "Theme settings").
4. Remove `@import "_fonts";` from its `global.scss`; `_fonts.scss` and
   `Fonts/` can then be deleted.
