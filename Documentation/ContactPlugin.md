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
| Table | `tx_sitepackage_domain_model_contact` (plain fields: `salutation`, `first_name`, `last_name`, `job_position`, `department_text`, `room`, `phone`, `mobile`, `fax`, `email`, `website`, `address` (street and house number), `zip`, `city`, `photo` (FAL, max 1), `description` (RTE), `categories` (system categories, see below)) |

FlexForm fields, same `settings.pluginType` list/show switch as `taketool/fewo`'s
own `Fewo` plugin uses (`packages/fewo/CLAUDE.md`):

- **List mode**: `settings.listSource` (`persons` / `categories`, reloads the
  form and shows only the matching picker), `settings.categories` (a `category`
  tree picker, zero or more categories, stored as a comma-separated uid list),
  `settings.persons` (a
  `group` picker on `tx_sitepackage_domain_model_contact`, like tsa's own
  `settings.persons`) — see "Choosing which contacts a list shows" below,
  `settings.displayMode` (`single` wide cards / `multiple` grid, like tsa's
  `displayMode`), `settings.singleViewPage` (the detail page a card's "Details"
  link targets, like fewo's `settings.singleViewPage`).
- **Both modes**: `settings.displayItems` (checkboxes: salutation/position/
  department/room/phone/email, ported from tsa's own `displayItems` with the
  same meaning, plus `address`, which tsa doesn't have). Nothing checked
  means everything is shown.
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

Cards and the detail view render the address (`address`, then `zip` +
`city`) above the communication list, shown with the `address` display item.
JSON-LD carries it as a `PostalAddress`. The communication list shows the
fax after the mobile number, with the `phone` display item (JSON-LD
`faxNumber`). `description` renders below the communication list. The
directory migration puts data there that has no field of its own, such as a
second homepage. Older imports also put the address and fax there;
`skilledmigration:migrate:contact-fields` moves them into their fields
(`packages/skilledmigration/Documentation/04_ContactFieldMigration.md`).

## Photo crop variants

The `photo` field defines two crop variants (`overrideChildTca` on the
file reference's `crop` column in
`Configuration/TCA/tx_sitepackage_domain_model_contact.php`), so editors
choose the image section and aspect ratio separately for each place the
photo appears:

| Variant | Used in | Default ratio | Other ratios |
|---|---|---|---|
| `card` ("Karte (Liste)") | `Partials/Contact/Card.html` (`card-img-top`) | 4:3 | 1:1, 3:4, free |
| `detail` ("Detailseite") | `Templates/Contact/Show.html`, and the JSON-LD `image`/`logo` in `Partials/Contact/SchemaPerson.html` | 4:3 | 1:1, 3:4, free |

The templates select the variant with `cropVariant="card"` /
`cropVariant="detail"`. There is no `default` variant.

The crop area is stored in the file reference's `crop` field when the
contact record is saved in the backend. Photos that were added before the
variants existed (or by an import that writes no `crop` value) have no area
for `card`/`detail` and render uncropped, at their original ratio, until an
editor opens the contact and saves it once.

The upgrade wizard `sitepackage_contactPhotoCropVariants`
(`Classes/Upgrades/ContactPhotoCropVariantsWizard.php`) fills in the missing
variants: each missing `card`/`detail` entry gets the whole image with the
free ratio, so the frontend looks the same as before. Variants that already
exist are left alone, other variants (e.g. `default`) are kept, and hidden
file references are included. A centred 4:3 crop was not used because most
existing contact photos are organisation logos (square or wide banners), and
4:3 would cut their edges off. Editors switch a photo to 4:3 in the crop
editor when they want it.

```
vendor/bin/typo3 upgrade:run sitepackage_contactPhotoCropVariants
```

The wizard is repeatable: it is never marked as done and shows up in the
upgrade list whenever a contact photo lacks one of the variants, for example
after a migration import. "Free" keeps whatever area the
editor draws, so cards in one grid can differ in height if editors mix
ratios. Use one ratio per list when the grid has to line up.

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

A list-mode plugin has two sources, and `settings.listSource` picks exactly one
of them. The FlexForm shows only the picker that belongs to the selected source.
A value left in the hidden picker stays in the FlexForm XML, but
`ContactController::listSource()` ignores it, so the two sources never mix.

| `settings.listSource` | Contacts shown | Order |
|---|---|---|
| `categories` | every non-hidden contact assigned to **at least one** of the `settings.categories` (OR, not AND) | organisation (`department_text`), then last name, first name (`findByAnyCategory()`) — an alphabetical directory, the way the Wasserliesch source lists were sorted |
| `persons` (default) | the contacts picked in `settings.persons` | the editor's pick order (`findByUidsInOrder()`) |

Records created before `settings.listSource` existed have no value for it. For
those, the controller falls back to the old rule: `categories` if any category
is set, `persons` otherwise. Migrations that create contact plugins should
always write `settings.listSource` explicitly.

The category source is `ContactRepository::findByAnyCategory()`. Like the rest of
the repository it does not respect storage pages, so a contact appears wherever
one of its categories is picked, no matter which sysfolder it lives in. In
practice, categories are kept per site (one category tree per tenant), and that
keeps contacts from leaking across sites.

## Backend preview

In the page module, a `sitepackage_contact` element shows its settings as
readable values instead of the generic plugin label
(`Classes/EventListener/ContactPluginPreviewRenderer.php`, a
`PageContentPreviewRenderingEvent` listener, template
`Resources/Private/Templates/Backend/ContactPluginPreview.html`):

| Setting | Shown as |
|---|---|
| `settings.pluginType` | List / Detail (FlexForm option label) |
| `settings.listSource` | the effective source; without an explicit value, the same fallback as `ContactController::listSource()` (categories if any are picked) |
| `settings.persons` | contact names in pick order, `First Last (Organisation)` or the organisation alone; only with source "persons" |
| `settings.categories` | category titles; only with source "categories" |
| `settings.displayMode` | Stacked / Grid (FlexForm option label) |
| `settings.singleViewPage` | page title and uid |

Detail mode shows only the mode. Labels are the FlexForm's own labels, so
the preview is German for German backend users. Deleted contacts, categories
or pages are left out; hidden ones are still listed.

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

## Card layout

`Resources/Public/Scss/Theme/global.scss` styles the two list modes
differently. All layout rules are scoped to one of the two wrappers from
`Templates/Contact/Index.html`; the bare `.sitepackage-contact-card` class
carries no layout of its own.

**Stacked list** (`settings.displayMode = single`, each card in a `.mb-3`
wrapper, selector `.sitepackage-contact .mb-3 > .sitepackage-contact-card`):
the cards are not boxed. Each card has no border, radius, background or
shadow and 1.25rem vertical padding; the card body has no padding of its
own. Rows are separated by a hairline (`--bs-border-width` in
`--bs-border-color-translucent`) on the top of every card that follows
another card (`.mb-3 + .mb-3 > .sitepackage-contact-card`), so there is
no line above the first or below the last card. The wrappers' `mb-3`
margin is set to 0 inside the contact list
(`.mb-3:has(> .sitepackage-contact-card)`; `!important`, because
Bootstrap's spacing utilities are `!important` themselves), so the
spacing comes only from the card padding and the line sits exactly
between two rows (1.25rem above and below). The layouts' decorative card
pseudo-element is switched off (`content: none` on `::before`), so the
hairline is the only separator. The selector is more specific than the layouts' plain `.card`,
so it wins although the layout CSS loads after `global.scss`.

Below `sm`, logo and text stack (flex column, 1.5rem gap), and the logo
sits in a 150px-high `object-fit: contain` box, centered. From `sm` up,
every card becomes a two-column grid: a 150px column on the left for the
logo or, without one, the placeholder (see below, 150 × 112.5 px here),
3rem gap, the text on the right.

The card body holds the title (`<h3 class="card-title">`) and two groups
after it (`Partials/Contact/Card.html`): `.sitepackage-contact-card-main`
(position, department, room, address) and
`.sitepackage-contact-card-details` (communication list, description,
"Details" link). In the stacked list, from the `md` breakpoint up, the
card body is a two-column grid: the title spans both columns
(`grid-column: 1 / -1`), and below it the two groups sit side by side
(3rem gap, top-aligned), so they start at the same height. The columns
are `fit-content(28ch)` and `fit-content(40ch)` with
`justify-content: start`: each is as wide as its content, but at most
about 28 / 40 characters, and the second follows the first at the gap
instead of taking half of the width. Longer lines wrap at that cap, so
one long department name or street does not push the second column to
the right and leave empty space under the short lines, and a long
description wraps at a readable line length. The caps are a first guess;
adjust them to the real data if names wrap too early. The title has `width: 0;
min-width: 100%`, so it fills the whole row but does not count when the
columns are sized; without that, a long name would widen the columns.
The card then has three columns: logo, main data, contact details, with
the title over the two text columns. Below `md` they stack. The
logo is aligned to the top left (`align-items: start`, `height: auto`,
`object-position: left top`), level with the title, as in the grid. In
the grid the two groups are plain blocks and stack, so it looks as
before.

Columns aligned across rows (subgrid): the rules above size each card's
columns on their own, so the contact details would start at a different
position in every row. To line them up like a table, the stacked list's
rows are wrapped in `.sitepackage-contact-stack`
(`Templates/Contact/Index.html`), and from `md` up that wrapper is the
one grid that defines the columns for the whole list:
`150px fit-content(28ch) fit-content(40ch) minmax(0, 1fr)`, 3rem gap.
Each level below takes the columns over with
`grid-template-columns: subgrid`: the `.mb-3` row and the card span all
four columns, the logo or placeholder sits in column 1, and the card body
spans columns 2 to 4, with `.sitepackage-contact-card-main` and
`.sitepackage-contact-card-details` in its first two and the title across
all three. The text columns are therefore sized once, by the widest
content of any row (still capped at about 28 / 40 characters). The fourth
column only takes the remaining width, so cards and their hairlines run
across the full width; without it the cards would end after the details
column. A table was not used: each contact is a record with a heading,
logo and two groups, the description and "Details" link do not fit into
cells, and below `md` the rows have to stack.

These rules are inside `@supports (grid-template-columns: subgrid)`.
Browsers without subgrid (all current browsers support it, since 2023)
keep the per-card layout described above: everything works, only the
columns are not aligned across rows. The hairline and spacing rules use
the `.mb-3` wrappers, which are unchanged inside the new wrapper.

**Grid** (`settings.displayMode = multiple`, cards in
`.row > .col`): a normal Bootstrap card with the layout's card styling.
From the `sm` breakpoint up, every card becomes a two-column grid: a
120px column on the left (plus the card's horizontal spacing) and the card
body on the right. The photo is 120px wide, keeps its own ratio and is
aligned to the top, with the card's spacing as margin above and left of
it, so its top edge lines up with the top of the text (the title). It
has Bootstrap's `rounded` class (`--bs-border-radius`, overriding the
card's top-only `card-img-top` radius). The spacing must be margin, not
padding: with padding the rounded corners would sit on the padded box
and not touch the image. The rounding is only visible where the image
fills its box, i.e. in the grid; in the stacked list and below `sm` the
`object-fit: contain` box is larger than the image, and logos with a
white background show no visible corner on a white card anyway. Below `sm`, the photo sits on top at 70% width with a 16:9
box.

A card without a photo renders `.sitepackage-contact-card-placeholder`
instead (`Partials/Contact/Card.html`): a 4:3 box (the `card` crop
variant's default ratio) in `--bs-tertiary-bg` with a person or building
icon at 50% opacity and the same `rounded` corners, at the same position
as a photo. Its look is defined once on the class itself, hidden by
default (`display: none`); each layout only switches it on and sets its
width: 120px (120 × 90) in the grid, 150px (150 × 112.5) in the stacked
list, matching the logo column. Both layouts show it from `sm` up; below
`sm` it stays hidden, so a card without a photo shows only the text.

The detail view does not use the card partial and is not affected.

The icon rows (position, department, room, address, communication list)
are flex rows (`d-flex align-items-start`) with the icon as the first
item. Font Awesome gives `.fa-solid` `line-height: 1`, so the icon box is
only 1em tall while the text line is about 1.5em, and with
`align-items-start` the icon sits too high. `global.scss` gives these
icons (`.sitepackage-contact .d-flex > .fa-solid`) `line-height: inherit`,
so the icon box is exactly one text line tall and the glyph is centered
on the first line, also next to a two-line address. A fixed width of
1.25em (like FA's `fa-fw`) lines up the text of all rows. Both sizes are
in `em`, so the icons scale with the text. This applies to the stacked list, the grid and the detail
view. The card title (`<h3>`, person or building icon) is a flex row of
the same kind, so a long name that wraps continues under the text, not
under the icon. The title is larger than the rows, so a `1.25em` icon box
would be wider there and push the title text further right than the row
texts. The title icon's box is therefore set to
`calc(1.25 * var(--bs-body-font-size))`, the width of the row icons
(the rows use the body font size; `fontsize.css` sets it to a `clamp()`,
not `1rem`, so a fixed `rem` value would not match). The glyph keeps the
title's size and is centered on the same axis as the row icons, and all
texts start at the same position. The detail view's `<h1>` has no icon.
The card title has `text-wrap: balance`, so a name that wraps is split
into lines of similar length instead of a full line and a short rest.
Browsers without support wrap normally.

The communication list (`.sitepackage-contact .sitepackage-contact-list`,
all list modes and the detail view) has `overflow-wrap: anywhere`. Email
addresses and URLs contain no spaces and would otherwise stick out of a
narrow column or card; with `anywhere` the browser breaks them at any
character, but only when the whole string does not fit. Unlike
`break-word`, `anywhere` also lowers the text's minimum width, which the
flex rows (`d-flex` with the icon) need, or the row would not shrink.

The page layouts (`pl0.scss` to `pl3.scss`) put a symbol in front of text
links: an external-link symbol for `target="_blank"` (all layouts) and an
arrow for other links (pl1, pl2). Links in `.sitepackage-contact-list`
(phone, mobile, email, website) already have their own FA icon, so each
of these rules excludes them with `:not(.sitepackage-contact-list a)`,
next to the existing exceptions such as `.frame-type-social_links`. The
"Details" link and links in the description are outside the list and keep
their symbol.

The same list links have no underline at rest. On hover or keyboard focus
the layout's own underline appears: the animated line from `link-underline`
in pl0 to pl2, the Bootstrap underline in pl3. `global.scss` does this with
`.sitepackage-contact-list a[href]:not(:hover):not(:focus-visible)`, which
sets `text-decoration: none` and shrinks both `link-underline` gradients to
zero width (`background-size: 0 2px, 0 1px`), so the hover transition still
runs from zero. The rule needs `!important`: the layout rule contains the
`#page-content` ID and its CSS loads after `global.scss`. An extra `:not()`
exception in the layout rules would not work, because those rules also set
the link font (pl1, pl2) and the focus ring (pl0), and both would be lost.
