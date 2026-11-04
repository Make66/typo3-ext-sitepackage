# Sitepackage

`taketool/sitepackage` is this TYPO3 v14 project's own site-package extension:
project-wide TypoScript/Page TSconfig, the SCSS compilation pipeline for
per-tenant `fileadmin/templates/<slug>/` folders, a couple of small custom
content elements (Hero, Linkbox), the Contact plugin, and a handful of one-off
upgrade/cleanup console commands. Every site in this project depends on it.

We comment in docs, not in code. Explanation belongs in `Documentation/`, not
inline comments — see the pinned project-wide memory on this.

## Source of truth and testing

The source of truth lives in `~/github/typo3-ext-sitepackage/`. Make all edits
there. Testing happens in `~/dev/ddev/t3v14/`. The playground version is
symlinked (`vendor/taketool/sitepackage`) and does not require a sync.

## Git

Always `git add` every new file you create (no commit unless asked).

## Content elements and plugins

| CType | Kind | Notes |
|---|---|---|
| `sitepackage_hero` | plain `tt_content` fields | Header/subheader/bodytext/CTA over a selectable background, registered in `Configuration/TCA/Overrides/tt_content.php`. |
| `sitepackage_linkbox` | plain `tt_content` fields | A box title + an editable list of links (IRRE `tx_sitepackage_linkbox_item`). |
| `sitepackage_contact` | Extbase plugin | Contact-card list/detail (staff directory, organization directory). Ported from `taketool/tsa`'s own Contact plugin onto a standalone, hand-editable domain model — see `Documentation/ContactPlugin.md`. |

## Documentation

@Documentation/ContactPlugin.md

Pre-existing topic files not pulled in here (open directly when relevant):
`SCSS_pipeline.md`/`SCSS_migration.md`/`MIGRATION_SUMMARY.md` (the per-tenant
SCSS compilation chain and its migration history; §8 "Theme settings" lists
the typography/navigation/breadcrumb/cards/footer site settings), `Fonts.md`
(central font library in `Resources/Public/Fonts/`, selected by site setting), `MainNavigationLevels.md`
(mega-menu depth), `StickyHeader.md` (the shrinking-header JS),
`Calendarize.md` (optional `lochmueller/calendarize`: template overrides and
the month view category filter and colors, site setting `sitepackage.calendarize.*`),
`Categories.md` (the `sys_category.tx_sitepackage_color` field: setup, editor
steps, where it is used),
`RouteEnhancers.md` (speaking URLs: `Configuration/Routes/*.yaml` imported per
site, limited to marked pages via `sitepackagePluginPage()`, site settings
`sitepackage.routing.*`),
`SchemaOrg.md` (JSON-LD structured data: organization/website/breadcrumb from site settings
`sitepackage.schema.*`, `Person` in the Contact templates), `A11yToolbar.md` (opt-in Barrierefreiheits-Leiste, site settings
`sitepackage.a11y.*`), `IconSet.md` (own bootstrap_package icon set from
`Resources/Public/Icons/Set/`), and two one-off
upgrade commands, `Mig12Cleanup.md` and `NewsToFeUsersImport.md`.
