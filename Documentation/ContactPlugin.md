# Contact plugin

`sitepackage_contact` (`Classes/Controller/ContactController.php`) is a list/detail
content-element plugin for contact cards — a staff directory, an organization/
club directory, anything shaped like "name, role, phone, email, maybe a photo".

## Why it exists, and why it isn't just `taketool/tsa`'s own Contact plugin

`taketool/tsa` already has a `Contact` plugin (`tsa_contact`,
`packages/tsa/Classes/Controller/ContactController.php`), and this one is a direct
port of its card markup and FlexForm shape. It had to be a port, not a reuse, for
two reasons:

- **tsa's version has no detail/show action** — it is `indexAction()` only, a
  list of cards with no per-person page. A new tenant onboarded through the
  `newTenant` skill needs a detail view for its own staff bios (a
  structural-repetition group the skill's crawl step flags — see that skill's
  "Group B" handling), so this port adds one.
- **tsa's version reads `tx_tsa_domain_model_person`**, a table populated only
  by tsa's own TSA-API mirror sync (`packages/tsa/Documentation/01_Api.md`).
  A generic new municipality has no such feed. This plugin's own table,
  `tx_sitepackage_domain_model_contact`, is a plain, hand-editable Extbase
  domain model — no mirror, no sync, no API dependency — populated by an
  editor in the backend, or by a `skilledmigration:import:content-elements`
  plan the same way any other migrated content is.

## Shape

| | |
|---|---|
| CType | `sitepackage_contact` |
| Controller | `ContactController`, actions `index` (list), `show` (detail) |
| FlexForm | `Configuration/FlexForms/ContactPlugin.xml` |
| Table | `tx_sitepackage_domain_model_contact` (plain fields: `salutation`, `first_name`, `last_name`, `job_position`, `department_text`, `room`, `phone`, `mobile`, `email`, `website`, `photo` (FAL, max 1), `description` (RTE), `categories` (system categories, see below)) |

FlexForm fields, same `settings.pluginType` list/show switch as `taketool/fewo`'s
own `Fewo` plugin uses (`packages/fewo/CLAUDE.md`):

- **List mode**: `settings.categories` (a `category` tree picker, zero or more
  categories, stored as a comma-separated uid list), `settings.persons` (a
  `group` picker on `tx_sitepackage_domain_model_contact`, like tsa's own
  `settings.persons`) — see "Choosing which contacts a list shows" below,
  `settings.displayMode` (`single` wide cards / `multiple` grid, like tsa's
  `displayMode`), `settings.singleViewPage` (the detail page a card's "Details"
  link targets, like fewo's `settings.singleViewPage`).
- **Both modes**: `settings.displayItems` (checkboxes: salutation/position/
  department/room/phone/email — ported verbatim from tsa's own
  `displayItems`, same six keys, same meaning).
- **Show mode**: `settings.backToListPage` and `settings.contact` (pin one
  specific contact, overriding whatever `contact` route/request argument a
  visitor's URL carries — the same override tsa's own
  `AbstractTsaPluginController::pickedUid()` establishes for its plugins, and
  fewo's `settings.fewo` establishes for its own show mode).

`ContactController::showAction(?Contact $contact = null)` takes a real Extbase
domain object exactly the way `FewoController::showAction(?Fewo $fewo = null)`
does (`packages/fewo/Documentation/02_RouteEnhancer.md`) — Extbase's default
object-argument mapping resolves a uid whether it arrives as a path segment
(via a route enhancer) or a plain GET parameter, so no slug/id-extraction step
is needed on top.

`ContactRepository::findByUidsInOrder()` exists because `settings.persons` is a
comma-separated uid list in the editor's own pick order, and a plain `IN()`
query does not preserve that order — the result is re-sorted in PHP to match.

## Organisation-only records

A record is either a person (`first_name` + `last_name`, optionally with the
organisation in `department_text`), or an organisation alone: names left
empty, `department_text` set. The second kind covers directory entries
without a named contact (a restaurant, a company). Neither name field is
required in the backend form, so an editor has to fill in a name or an
organisation themselves; nothing enforces it.

What changes for an organisation-only record:

| | Person | Organisation only |
|---|---|---|
| Card / detail title | salutation + first + last name, person icon | `department_text`, building icon |
| Department line | shown (if `displayItems.department`) | not repeated, it is the title |
| JSON-LD (`Partials/Contact/SchemaPerson.html`) | `Person` with `worksFor` | `Organization` (`logo` = photo) |
| Backend label | last name, first name, position, organisation | organisation |
| Slug | `first-last` | `organisation` |

The model exposes this as `Contact::isPerson()` (`{contact.person}`) and
`Contact::getName()` (`{contact.name}`: the person's name, else the
organisation), used for the image `alt` text. The slug's generator fields are
`first_name` + (`last_name`, or `department_text` when there is no last name).

Cards render `description` below the communication list. That's where the
directory migration puts the address, and where it puts data that has no
field of its own: fax, a second homepage.

## Categories

Contact records carry the standard TYPO3 system categories (`sys_category`),
edited on their own "Categories" backend tab. The TCA field is of type
`category`, so TYPO3 itself generates the `categories` DB column and stores the
relation in the shared `sys_category_record_mm` table (`tablenames =
tx_sitepackage_domain_model_contact`, `fieldname = categories`) — there is no
`ext_tables.sql` entry for it. Field and tab labels come from EXT:core, which
already ships the German translation.

The Extbase model exposes them as `Contact::getCategories()`
(`ObjectStorage<\TYPO3\CMS\Extbase\Domain\Model\Category>`), so templates
can use `{contact.categories}`. The list mode can select contacts by
category, see the next section.

## Choosing which contacts a list shows

A list-mode plugin has two sources, and exactly one of them is used:

| `settings.categories` | Contacts shown | Order |
|---|---|---|
| one or more categories | every non-hidden contact assigned to **at least one** of them (OR, not AND); `settings.persons` is ignored | organisation (`department_text`), then last name, first name (`findByAnyCategory()`) — an alphabetical directory, the way the Wasserliesch source lists were sorted |
| none | the contacts picked in `settings.persons` | the editor's pick order (`findByUidsInOrder()`) |

The category source is `ContactRepository::findByAnyCategory()`. Like the rest of
the repository it does not respect storage pages, so a contact appears wherever
one of its categories is picked, no matter which sysfolder it lives in. In
practice, categories are kept per site (one category tree per tenant), and that
keeps contacts from leaking across sites.

## Site setup

1. No Site Set dependency needed — this plugin ships inside `taketool/sitepackage`,
   already a dependency of every site in this project.
2. Give the contact records their own storage folder (any sysfolder; the
   repository disables storage-page respect the same way `FewoRepository`
   does, so the picker/show action work regardless of which page a plugin
   instance lives on).
3. If the site uses show mode, add a `Contact` Extbase route enhancer with
   `limitToPages` set to that site's detail page uid — **mandatory**, not
   optional, for the same reason documented in
   `packages/fewo/Documentation/02_RouteEnhancer.md` "`limitToPages` is
   mandatory": an unscoped Extbase enhancer collides with any other
   single-segment enhancer on the same page and the loser's controller
   silently falls back to its default action with a missing argument.

## What did not carry over from tsa's version

- `CacheTagger` / per-record page-cache tagging (`packages/tsa/Documentation/
  07_Plugins.md` "Page cache correctness") — this plugin's data has no live
  sync to go stale against, so the risk that tagging exists to prevent does
  not apply here.
- `TsaSettingsResolver` / multi-tenant storage-pid-from-site-settings handling
  — a plugin instance is scoped only by its own pickers (`settings.categories`
  or `settings.persons`); there is no site-wide "all contacts for this tenant"
  notion to resolve.
- The `anonymize`/`visible` upstream-publish flags (`AbstractTsaPluginController::
  onlyVisiblePersons()`) — those exist only because tsa mirrors third-party data
  that may be marked not-for-publication; a hand-authored contact record is
  either `hidden` (a normal TYPO3 enable field) or it is not.
