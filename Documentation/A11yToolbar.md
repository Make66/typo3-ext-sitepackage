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
| `sitepackage.a11y.searchPid` | int | `0` | Search link (hidden on mobile). |
| `sitepackage.a11y.contactPid` | int | `0` | Contact link (hidden on mobile). |
| `sitepackage.a11y.statementPid` | int | `0` | "Erklärung zur Barrierefreiheit" link in the panel. |
| `sitepackage.a11y.easyLanguagePid` | int | `0` | "Leichte Sprache" link in the panel. |
| `plugin.bootstrap_package.settings.scss.a11y-item-bg` | string | `#e9ecef` | Background of the toolbar boxes. |

A page uid of `0` hides that link. Example (calden):

```yaml
sitepackage.a11y.enable: true
sitepackage.a11y.searchPid: 78
sitepackage.a11y.contactPid: 258
sitepackage.a11y.statementPid: 79
sitepackage.a11y.easyLanguagePid: 58
plugin.bootstrap_package.settings.scss.a11y-item-bg: '#dde9e0'
```

## Files

| File | Role |
|---|---|
| `Configuration/TypoScript/Setup/a11y.typoscript` | Maps the settings to `page.10.settings.a11y.*`; adds `page.includeCSS.a11y` when enabled (re-adds `custom` after it so the mandant's `custom.scss` still wins). |
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
  (`a11y-item-size`, `a11y-item-gap`, `a11y-item-color`,
  `a11y-item-icon-color`, `a11y-burger-width`, `a11y-burger-gap`,
  `a11y-bar-height`, `a11y-navbar-right`, `a11y-icon-path`) can be set the same
  way in the site's `settings.yaml`; only `a11y-item-bg` has a settings
  definition (so it shows in the backend settings editor).
- Rule-level overrides go in the mandant's `custom.scss`.
- A mandant that needs its own `DropIn/Page/PageBefore.html` in fileadmin
  should keep `<f:render partial="A11y/Toolbar" arguments="{_all}" />` in it.
- The partial name `_toolbar.scss` is deliberate: a `_a11y.scss` next to
  `a11y.scss` makes `@import "a11y"` ambiguous for scssphp.
