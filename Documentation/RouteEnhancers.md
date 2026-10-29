# Route enhancers (speaking URLs)

The sitepackage ships route enhancers for the plugins it supports, in
`Configuration/Routes/`. A site (mandant) imports the ones it needs. Each
enhancer is active only on pages that are marked for it, not on the whole site.

**Requires TYPO3 14.2 or newer.** The page limitation uses expressions in
`limitToPages` (core Feature #109263). On TYPO3 13 the string entries never
match, so the enhancers are silently inactive and the plugins keep their
`?tx_…&cHash=` URLs.

## Why not global

Without `limitToPages`, TYPO3 builds every enhancer, including its aspects, for
every page whenever it generates or resolves a URL. `PersistedAliasMapper`,
`NewsTitle` and similar aspects run database queries. On a site with large menus
this adds up even though only a few pages actually contain the plugin. With the
limitation, the enhancer is skipped on all other pages. The check is a cheap
expression on the page record that is already loaded.

## Shipped files

| File | Enhancer key(s) | Plugin / namespace | Example URLs |
|---|---|---|---|
| `Calendarize.yaml` | `Calendarize`, `CalendarizeBooking` | all calendarize plugins (`tx_calendarize_calendar`), booking | `/events/month/2026/11`, `/events/event/<slug>` (de: `monat`, `termin`) |
| `News.yaml` | `News` | `tx_news_pi1` (all news plugins) | `/news/article/<slug>`, `/news/page-2`, `/news/category/<slug>`, `/news/tag/<slug>` (de: `artikel`, `seite`, `kategorie`, `schlagwort`) |
| `Contact.yaml` | `SitepackageContact` | `tx_sitepackage_contact` | `/team/contact/<first-last>` (de: `kontakt`) |
| `All.yaml` | – | imports the three files above | |

The path labels are `LocaleModifier`s: `de_*` locales get the German label,
all other locales get the English one.

## Enabling for a mandant

Add the import to `config/sites/<mandant>/config.yaml`:

```yaml
imports:
  - resource: 'EXT:sitepackage/Configuration/Routes/Calendarize.yaml'
  - resource: 'EXT:sitepackage/Configuration/Routes/News.yaml'
```

or all of them:

```yaml
imports:
  - resource: 'EXT:sitepackage/Configuration/Routes/All.yaml'
```

This has to go in `config.yaml`. `settings.yaml` holds site settings only, and
`routeEnhancers` or `imports` there are ignored. Saving the site in the Sites
module keeps the `imports:` block.

Flush caches after changing the imports (`typo3 cache:flush`).

## Limiting to pages

Every enhancer has

```yaml
limitToPages:
  - 'sitepackagePluginPage("<key>")'
```

`sitepackagePluginPage()` is an ExpressionLanguage function the sitepackage adds
to the `routing` context (`Configuration/ExpressionLanguage.php`,
`Classes/ExpressionLanguage/`). It returns true if **either** of these is set
for the page:

1. **Page property "Contains plugin"** (`pages.module`, tab *Behaviour*). This is
   the normal way: whoever places the plugin also sets the property.

   | Key | "Contains plugin" value | Shipped by |
   |---|---|---|
   | `calendarize` | Calendarize (`calendarize`) | calendarize |
   | `news` | News plugins (`newsplugins`) | news. Not "News folder" (`news`), which marks the storage folder |
   | `contact` | Sitepackage: Ansprechpartner (`contact`) | sitepackage (`Configuration/TCA/Overrides/pages.php`) |

2. **Site setting** with a list of page uids, in `config/sites/<mandant>/settings.yaml`
   (Site settings → Sitepackage → Sprechende URLs):

   ```yaml
   sitepackage.routing.calendarizePages: ['12', '34']
   sitepackage.routing.newsPages: ['56']
   sitepackage.routing.contactPages: []
   ```

   Use this when the page property is already used for something else. Each page
   holds only one "Contains plugin" value.

Details:
- For translated pages, the uid of the default-language page is compared
  (`l10n_parent`). `pages.module` is `l10n_mode: exclude`, so translations
  inherit it.
- The settings are stringlists, and the uids are compared as integers.
- If an expression throws, TYPO3 swallows the exception and treats the
  enhancer as not matching. A typo in the function name therefore shows up only
  as "the links still have a cHash".
- The second argument of `sitepackagePluginPage("news", "newsplugins")` is the
  `pages.module` value to check, if it differs from the key. The key always
  names the setting (`sitepackage.routing.<key>Pages`).

## Overriding per site

Values in the site's own `config.yaml` take precedence over the imported files.
For example, to add a fixed page uid as well:

```yaml
imports:
  - resource: 'EXT:sitepackage/Configuration/Routes/Calendarize.yaml'
routeEnhancers:
  Calendarize:
    limitToPages:
      - 'sitepackagePluginPage("calendarize")'
      - 42
```

Imported and local lists are merged **by index**, not appended. A local list
`[42]` would replace entry 0, which is the expression. So always restate the
whole list.

## Per plugin

### Calendarize

`Calendarize.yaml` imports calendarize's own
`EXT:calendarize/Configuration/Yaml/RouteEnhancers.yaml` and only adds
`limitToPages`. Routes, aspects and labels therefore follow calendarize updates.
calendarize sets the namespace `tx_calendarize_calendar` for all its plugins, so
the single `plugin: Calendar` enhancer covers List, Detail, Month, Year and the
others. The year range is 2015–2040 (StaticRangeMapper), and URLs outside it
return a 404.

The month view category filter (see [Calendarize.md](Calendarize.md)) has no
route. Its `customSearch[categories][]` stays a query argument with a cHash, for
example `/events/month/2026/10?tx_calendarize_calendar[customSearch][categories][0]=26&cHash=…`.

### News

Routes follow the "basic setup" from the news documentation. Pagination comes
before detail, because the order matters on "list with detail" pages. A `PageType`
enhancer for RSS feeds is not included: it is site-wide, so each site adds it
to its own `config.yaml` if needed.

### Contact

The contact record has a `slug` field (URL segment, generated from first and last
name, unique per storage folder). New and edited records get it automatically.
To fill it for existing records:

```bash
ddev typo3 sitepackage:contact-slugs --dry-run
ddev typo3 sitepackage:contact-slugs
```

A contact without a slug can't be mapped. Its link falls back to the query
string URL and still works.

## Troubleshooting

| Symptom | Cause |
|---|---|
| Links still have `?tx_…&cHash=` | Page not marked (property or setting), file not imported, caches not flushed, or TYPO3 < 14.2 |
| Speaking URL returns 404 | Value outside a StaticRangeMapper range, unknown slug, or wrong language label (`month` on a German site, where it is `monat`) |
| Works on one page, not on its translation | The setting must list the default-language uid |
