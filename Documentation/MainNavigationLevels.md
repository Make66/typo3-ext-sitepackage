# Main navigation / mega menu depth (`page.theme.mainnavigation.levels`)

Covers the `mainnavigation` menu (navbar + dropdown/mega menu) rendered by
`Resources/Private/Partials/BootstrapPackage/Page/Navigation/MainNavigation.html`
and its per-site setting, added 2026-09-19.

## How many layers it renders

Three page-tree levels, by default:

1. **Navbar items** — top-level pages, rendered by `MainNavigation.html`.
2. **Dropdown columns** — each top-level item's children, rendered by
   `vendor/bk2k/bootstrap-package/Resources/Private/Partials/Page/Navigation/
   MainNavigationDropDown.html` (not overridden in sitepackage) starting at
   `level: 2`.
3. **Nested items inside a column** — grandchildren, rendered by the same
   partial recursing into itself (`level: level + 1`).

`MainNavigation.html` decides `dropdown-menu-simple` vs `dropdown-menu-mega`
by checking whether *any* level-2 child has its own children — i.e. whether a
level-3 exists at all for that branch. `MainNavigationDropDown.html` itself has
no hardcoded depth limit: it recurses for as many levels as the menu data
actually contains via `<f:render section="dropdown" ... />` calling itself.
The real limit is purely how many levels the underlying menu query fetches.

That query is a `TYPO3\CMS\Frontend\DataProcessing\MenuProcessor` registered
as `page.10.dataProcessing.10` (`as = mainnavigation`) in
`vendor/bk2k/bootstrap-package/Configuration/Sets/Full/TypoScript/General/
page.typoscript`, which hardcodes `levels = 3`. Unlike the footer menu
(`footernavigation`, whose `levels` is a real site setting,
`page.theme.footernavigation.levels`), bk2k ships no setting for this one.

## What sitepackage adds

- `Configuration/Sets/Sitepackage/settings.definitions.yaml` defines
  `page.theme.mainnavigation.levels` (type `int`, default `3`), the same
  pattern as bk2k's own `footernavigation.levels`.
- `Configuration/TypoScript/Setup/basis.typoscript` overrides the processor:
  `page.10.dataProcessing.10.levels = {$page.theme.mainnavigation.levels}`.

This only changes how many levels are *fetched*; it doesn't touch the mega
menu's CSS (`vendor/bk2k/bootstrap-package/Resources/Public/Scss/components/
navbar/_dropdown.scss`), which lays out `.dropdown-nav` as a CSS grid
generically at any nesting depth — going deeper than 3 renders without a hard
CSS cap, but hasn't been visually reviewed past the default depth.

## How to override it per site

Per-site settings on this install live in `config/sites/<site>/settings.yaml`
as **flat dotted keys**, e.g.:

```yaml
page.theme.mainnavigation.levels: 2
```

This is the format to use — confirmed against `TYPO3\CMS\Core\Site\
SiteSettingsFactory::getSettings()`: if `config/sites/<site>/settings.yaml`
exists, it's used *instead of* (not merged with) whatever `settings:` key is
present in that site's `config.yaml`, so a nested `settings:` block dropped
into `config.yaml` is silently ignored whenever a `settings.yaml` file exists
next to it — as it does for every site on this install that has ever had a
setting configured via the backend Site Management UI. Always check for and
edit `settings.yaml` first; only fall back to `config.yaml`'s `settings:` key
for a site that has no `settings.yaml` at all.

After changing it, flush caches (`vendor/bin/typo3 cache:flush --group all`)
and confirm with `vendor/bin/typo3 site:show <site>` — it prints the resolved
value under `mainnavigation: { levels: N }`.
