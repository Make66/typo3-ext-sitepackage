# schema.org markup (JSON-LD)

Machine-readable structured data so crawlers and AI agents can read facts
(who, where, when) without guessing from the layout. Format is JSON-LD in a
`<script type="application/ld+json">`; no extra extension is required.

## What is emitted

| Where | Type | Source |
|---|---|---|
| Every page (opt-in) | `Organization` (or subtype) and `WebSite` | site settings `sitepackage.schema.*` |
| Every page below the root | `BreadcrumbList` | current rootline, starting at the site root page (doktype >= 199 skipped) |
| Contact list cards | `Person` | `Contact` record |
| Calendarize event detail | `Event` (name, description, start/end, status, location, organizer, image) | `Partials/Calendarize/Event/SchemaEvent.html`, included from `Detail.html` |
| Contact detail view | `ProfilePage` with `mainEntity` `Person` | `Contact` record |

Not covered yet: news `NewsArticle`, fewo, event list/month views.

### Event details

All-day events get date-only `startDate`/`endDate`, events with "open end"
get no `endDate`, state `canceled` maps to `EventCancelled` (else
`EventScheduled`). `description` is the abstract, else the cropped description
without tags. `location` and `organizer` are plain names only. Only the first
non-video image is used. If EXT:schema is installed, calendarize's own
`StructuredData/Detail` partial is also rendered by `Detail.html`; remove one
of the two then, or the page carries two `Event` nodes.

## Site settings (category "Sitepackage > Strukturierte Daten")

| Setting | Default | Effect |
|---|---|---|
| `sitepackage.schema.enable` | `false` | Emits Organization and WebSite. Needs `name`. |
| `sitepackage.schema.breadcrumb` | `true` | Emits BreadcrumbList (independent of `enable`). |
| `sitepackage.schema.type` | `Organization` | `GovernmentOrganization`, `LocalBusiness`, `EducationalOrganization`, `NGO` also possible. |
| `sitepackage.schema.name`, `logo`, `street`, `postalCode`, `city`, `country`, `telephone`, `email` | empty (`country` = `DE`) | Organization data. `logo` is an absolute URL or a path relative to the site base. |
| `sitepackage.schema.sameAs` | empty | One profile URL per line. |

## Pieces

- `Classes/DataProcessing/SchemaOrgProcessor.php` builds the site-wide nodes;
  `Configuration/TypoScript/Setup/basis.typoscript` renders them with
  `Resources/Private/Templates/Schema/Site.html` into `page.headerData.9100`.
- `Classes/ViewHelpers/SchemaOrg/JsonLdViewHelper.php`
  (`<sp:schemaOrg.jsonLd data="{...}" />`, namespace
  `http://typo3.org/ns/Taketool/Sitepackage/ViewHelpers`) adds `@context`,
  drops empty values and encodes safely (`JSON_HEX_TAG`, so a value can never
  close the script tag). A node left with only `@type` is not rendered.
- `Resources/Private/Partials/Contact/SchemaPerson.html` is used by `Card.html`
  and `Show.html`. It only emits fields the plugin's `settings.displayItems`
  shows, so the markup never exposes more than the visible card.

## Relation to brotkrueml/schema (EXT:schema)

`brotkrueml/schema` is in `suggest`, not `require`. The two engines are
exclusive: as soon as EXT:schema is loaded, everything described here is
switched off. `JsonLdViewHelper` renders nothing and `SchemaOrgProcessor`
builds nothing, so nothing is emitted twice. One check per component, no
setting.

What that means once EXT:schema is installed:

- **Events:** calendarize's own `StructuredData/Detail` partial (already called
  by `Detail.html`) takes over.
- **Organization, WebSite, BreadcrumbList and the `sitepackage.schema.*`
  settings:** not output any more. Configure them in EXT:schema. The settings
  stay defined but have no effect.
- **Contact `Person`/`ProfilePage`:** gone, because EXT:schema has no
  automatic markup for the Contact plugin. Add it through EXT:schema's API in
  `Partials/Contact/SchemaPerson.html` if you need it.

Switch when a tenant needs more types than Organization, Person and Event
(news, lodging, FAQ) or wants one `@graph` for the whole page.

## Rules

- Markup must match the visible content; do not add fields the page does not show.
- Contact data is personal data: the displayItems gating is intentional.
- Validate with validator.schema.org and Google's Rich Results Test. Tested
  in the ddev project (TYPO3 14.3): Organization, WebSite, BreadcrumbList and
  the Contact list cards and the Calendarize event detail render correctly. The Contact detail view
  (`ProfilePage`) has not been rendered yet.
