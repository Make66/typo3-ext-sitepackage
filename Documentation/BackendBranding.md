# Backend branding

`ext_localconf.php` fills the core `EXT:backend` extension configuration
(`$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['backend']`) with the OrtsPage
branding. A value is only set while that option is empty, so anything entered
in Admin Tools → Settings → Extension Configuration → backend (or in
`config/system/additional.php`) still wins.

| Option | Value | Where it shows |
|---|---|---|
| `loginLogo` | `Resources/Public/Images/loginLogo.svg` | Login form |
| `loginLogoAlt` | `OrtsPage` | Alt text (only used if the logo is not an SVG) |
| `loginHighlightColor` | `#69918b` | Login button and accent line |
| `backendLogo` | `Resources/Public/Images/ortspage.svg` | Top-left corner after login |
| `backendFavicon` | `Resources/Public/Images/favicon.ico` | Browser tab of the backend |

TYPO3 v14 has no setting for the accent colour after login. That colour comes
from each user's chosen backend theme (Fresh/Modern/Classic), and the core
marks the backend CSS variables as internal API.

## Login logo: light and dark in one file

`loginLogo.svg` is the "Orts.Page Logo.svg" wordmark (ORTS `#010101`, PAGE
`#8e8e8e`, orange hexagon, green square). In dark mode the CSS inside the
file turns both text parts white; the two marks keep their colours. It relies
on how the core `LoginLogoViewHelper` renders SVG logos: the file is sanitized
and inlined into the login page, so its `<style>` block applies like page CSS.

- The switch uses `@media (prefers-color-scheme: dark)` for the login page
  (no user yet, so it follows the operating system) and
  `:root[data-color-scheme=…]` for a logged-in user's forced scheme, e.g.
  the re-login dialog after a session timeout.
- All classes are prefixed `op-`, because the inlined CSS is global to the
  login page.
- The inlined result is cached in `cache.assets`; flush caches after
  editing the file.

## Favicon

The same `favicon.ico` is also the default frontend favicon:
`Configuration/Sets/Sitepackage/settings.yaml` overrides bootstrap_package's
site setting `page.favicon.file`. To give a tenant its own favicon, set
`page.favicon.file` in `config/sites/<tenant>/settings.yaml` (or in the site
settings editor); the site's own value wins over the set default.

`favicon.svg` is `ortspage.svg` on a square `viewBox` with plain `fill`
attributes. `favicon.ico` (48/32/16 px) was generated from it in the DDEV
container:

```bash
ddev exec "cd /var/www/html/packages/sitepackage/Resources/Public/Images && \
  magick -background none -density 600 favicon.svg -define icon:auto-resize=48,32,16 favicon.ico"
```
