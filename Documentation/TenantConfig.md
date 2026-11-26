# Tenant configuration (Website-Einstellungen)

Non-admin backend users (e.g. auditors) can override the site settings of
**their own** website. Other tenants stay untouched. TYPO3's own site settings
editor (Site Management → Settings) is admin-only and has no per-site access
check, so it is not used for this.

## How it works

Each website can have one record of the table `tx_sitepackage_tenantconfig`
("Website-Konfiguration") on its **root page**. It holds a JSON map of the
settings that were filled in.

Precedence, lowest to highest:

1. the default in the site set (`Configuration/Sets/*/settings.definitions.yaml`)
2. `config/sites/<site>/settings.yaml`
3. the tenant configuration record

An empty field inherits; a filled field overrides. Clearing a field falls back
to `settings.yaml` or the set default.

The record is layered in by `Classes/Site/TenantSiteSettingsFactory.php`. It
replaces core's `TYPO3\CMS\Core\Site\SiteSettingsFactory` (an alias in
`Configuration/Services.yaml`) and adds the record's values as the last
settings provider. Every consumer sees the result: TypoScript constants,
bootstrap_package SCSS variables, route enhancer conditions
(`sitepackage.routing.*`), `$site->getSettings()` in PHP. Values are type-checked
by core's setting types; an invalid stored value is ignored and the lower layer wins.

## Which settings are offered

The fields are built at runtime, per website, from the site sets the site
includes (`dependencies` in `config/sites/<site>/config.yaml`, resolved
transitively). Only sets matching the extension configuration
**`tenantConfig.sets`** are used (default `taketool/*, apicodo/*`). New
settings or new packages appear automatically once their set is included.

Never offered:

- definitions with `readonly: true`
- keys matching **`tenantConfig.excludeSettings`** (default
  `*apiKey*, *ApiKey*, chatbot.serverApiUrl, chatbot.serviceUrl`). Use this
  for secrets and infrastructure URLs.

The tab order is set by **`tenantConfig.tabOrder`** (default `taketool/sitepackage, taketool/apicodo, taketool/infodienste, taketool/tsa, apicodo/chatbot`); sets not listed follow in their dependency order.

All options are in Admin Tools → Settings → Extension Configuration →
sitepackage. The same filter is applied when saving and when the values are
applied at runtime, so a key that is excluded later stops having an effect.

Empty fields show the **effective value without the record** as a grey hint
(set default overlaid with `settings.yaml`), so auditors see what is live.

## Editing

- **Module** Inhalt → **Website-Einstellungen** (page tree, same icon as System → Settings). Select
  any page of a website:
  - if the record exists, it opens in the normal record editor;
  - otherwise the module explains that there is none yet, lists the site sets
    it would cover, and offers **Konfiguration anlegen**. Nothing is created
    automatically.
  - Closing the editor returns to the Page module of the selected page.
- **List module**: the record is an ordinary record on the root page, labelled
  "Konfiguration: &lt;site identifier&gt;", and uses the same form.

Rules enforced on save (`Classes/Hooks/TenantConfigDataHandlerHook.php`):

- A record created on any subpage is stored on the site's root page (resolved via
  the rootline). The form says so beforehand.
- One record per website. A second one is refused; the form instead shows a
  link to the existing record.
- Moving and copying are refused; undelete is refused if the root page already
  has another record.
- Only offered keys are accepted, so a crafted request cannot set an API key.
  Invalid values (outside the enum, not an integer, not a colour, …) are
  reported and the previous value is kept.
- Partial updates (import, API) merge: keys that are not sent keep their value.

After saving, deleting or restoring, the hook flushes the core cache (site
and settings cache, as core's settings editor does) and the `pages` cache group,
so constants and SCSS are rebuilt on the next request.

## Access for an auditor group

Everything uses standard group rights:

- **Modules**: Inhalt → Website-Einstellungen (`content_tenantconfig`), plus
  Layout/Datensätze if wanted.
- **Tables (listing and modify)**: `Website-Konfiguration`
  (`tx_sitepackage_tenantconfig`). The settings field is not an exclude field.
- **DB mount**: the tenant's root page. Page permissions must allow the group to
  see the root page and to edit content on it (e.g. make the group the page
  owner group, or add the tenant's existing group, like "Calden", as subgroup).
- **Workspace**: live access (`workspace_perms`), otherwise the user sees no
  modules at all.

Tenant separation comes from the mounts and page permissions: pages outside
the mount are refused by core before the module runs.

## Known limits

- The core admin settings editor still shows and writes only `settings.yaml`.
  Values there are overruled by the record without notice.
- The overlay is applied when the site configuration cache is rebuilt. Editing
  the record directly in the database needs `typo3 cache:flush`.
- Labels and descriptions come from the set definitions. Translate them there,
  e.g. with `LLL:` references.
