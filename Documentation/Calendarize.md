# Calendarize (event calendar)

`lochmueller/calendarize` is an optional dependency of the sitepackage: it is listed
under `suggest` in `composer.json`, not `require`. If it is installed, the
sitepackage adds templates, partials and a category filter for the month view.
Without it, nothing calendarize-related is registered and the TypoScript below
does nothing.

## What the sitepackage changes

| File | Change compared to calendarize |
|---|---|
| `Resources/Private/Partials/Calendarize/Date/Static.html` | Date + time in a `.time` div with an icon. The end date is never shown (an event ending after midnight would otherwise show a second date), only the end time. |
| `Resources/Private/Partials/Calendarize/Event/ListItem.html` | Larger images (500px), `h4`/`h5` headings, date with icon, location (linked if `locationLink` is set). |
| `Resources/Private/Partials/Calendarize/Event/Detail.html` | Wrapped in `.calendarize_single`, calendar icon in the date heading, images/videos below the text (images max. 768px wide). |
| `Resources/Private/Templates/Calendarize/Calendar/Month.html` | Renders the category filter above the month grid. |
| `Resources/Private/Partials/Calendarize/Month.html` | Prev/next month links keep the selected category. Event titles are listed in the day cells even without a "Detail page" (then not linked). A line per event category in the category color. |
| `Resources/Private/Partials/Calendarize/MonthCategoryFilter.html` | The category filter links in the category colors (new). |

The paths are set in `Configuration/TypoScript/Constants/calendarize.typoscript`
(`plugin.tx_calendarize.view.templateRootPath` / `partialRootPath`). calendarize's
own paths stay at index 50, so only the files listed above are overridden.

## Month view: browsing between months

This is calendarize's own feature. The `<` / `>` links only appear if the month
plugin has a **"Month page"** set (plugin options, `settings.monthPid`). This is
normally the page the plugin is on. Instead of setting it on each plugin, it can
be set for a whole site in TypoScript:

```typoscript
plugin.tx_calendarize.settings.monthPid = 123
```

How far visitors can browse is limited by `plugin.tx_calendarize.settings.dateLimitBrowserPrev`
(default `-2 years`) and `dateLimitBrowserNext` (default `+3 years`). Months
outside that range return a 404.

Optional settings:
- **"Day page"** (`dayPid`) turns the day numbers into links.
- **"Detail page"** (`detailPid`) links the event titles to the detail view.

## Month view: event titles without a detail page

In calendarize itself, the day cells only list the event titles if a "Detail page"
is set. Without it, the cells are only marked with the CSS class `hasEvents`. The
sitepackage's `Month.html` partial always lists the titles: linked to the detail
page if one is set, as plain text otherwise. Each title is preceded by its start
and end time (none for all-day events, only the start time for open-end events).

Below the month title, a header row names the weekdays: the full name from
breakpoint `lg` up, below that the short form (full name as `abbr` title), switched
with Bootstrap's `d-lg-none` / `d-none d-lg-inline`. It uses `f:format.date` with an ICU pattern, so the names follow the
locale of the current site language (e.g. "Mon"/"Monday" or "Mo."/"Montag").

The prev/next month links are buttons (`btn btn-sm btn-outline-primary`) with a
FontAwesome chevron and the month name; the month title sits centred between them
(`.month-nav`). Month names also use ICU patterns and follow the site locale.

Below the title, a "Today" link (`btn-link`, label `calendarize.month.today` in
`locallang_db.xlf`, "Heute" in German) jumps back to the current month. It only
appears when another month is shown, needs a "Month page" like prev/next, and
keeps the category filter. Today's day cell gets the class `today` (inset border
in `$primary`).

Because "today" is rendered into the cached page, `MonthTodayCacheLifetimeEventListener`
(`GenericActionAssignmentEvent`, month action only) limits the page cache lifetime
of pages with a month plugin to the next midnight.

The month table has the class `calendarize-month`. Its styles live in
`Resources/Public/Scss/Theme/_calendarize.scss` (imported by `global.scss`): equal
column widths (`table-layout: fixed`), no list bullets, smaller right-aligned text, days of the previous/next month in a
light grey (`.not-current-month`).

## Month view: category filter

calendarize's `monthAction()` has no search/filter argument. The sitepackage adds
one with two event listeners and does not need an XCLASS.

- **`MonthCategoryFilterEventListener`** (`GenericActionAssignmentEvent`, month
  action only) assigns two variables to the view:
  - `extended.categories`: the categories used by events in the plugin's storage
    pages ("Record Storage Page" of the content element, or
    `plugin.tx_calendarize.persistence.storagePid`). If the content element has
    categories set (Categories tab), only those are offered.
  - `extended.categoryFilter`: the selected category uids.
- **`MonthCategoryConstraintEventListener`** (`IndexRepositoryDefaultConstraintEvent`)
  reads `tx_calendarize_calendar[customSearch][categories][]` from the request.
  For month plugins (CType `calendarize_month`) only, it limits the events to the
  selected categories. It runs after calendarize's `CategoryConstraintEventListener`
  and keeps only events that pass both. So a category fixed on the content
  element still applies, and the frontend filter can only narrow the result further.

The filter renders as links: "Alle" + one per category, as Bootstrap
`btn-outline-primary` buttons, with the active one as `btn-primary active` and
`aria-current`. Links are built with the Extbase UriBuilder, so they carry a
cHash and the filtered pages are cached normally. The query parameter has the
same format as calendarize's search plugin (`customSearch[categories][]`).

The listeners are registered in `Configuration/Services.php`, and only if
calendarize is installed. `Configuration/Services.yaml` excludes
`Classes/EventListener/Calendarize/` from autoloading for the same reason.

### Turning it on or off

Use the site setting **Sitepackage → Kalender (calendarize) → Kategorie-Filter in
der Monatsansicht** (`sitepackage.calendarize.monthCategoryFilter`, default: on).
It maps to `plugin.tx_calendarize.settings.monthCategoryFilter`
(`Configuration/TypoScript/Setup/calendarize.typoscript`).

The filter only appears if at least one category is available. If no event has
categories, the month view looks the same as calendarize's default.

### Limits

- Only one category can be selected at a time. The constraint listener accepts
  several (`categories[]`, combined with OR, like calendarize's search), but the
  filter template only creates links with one category.
- Only `tx_calendarize_domain_model_event` records are filtered. Other index types,
  such as events from other extensions, are not affected.

## Month view: category colors

Each category has a color. It appears as a thin line under every event in the
month grid (one segment per category of the event, the category title as tooltip)
and on the category filter buttons (outline in the color, filled when hovered or
selected). The "All" button keeps the primary color.

- **Editors** set the color in the category record (field **Farbe / Color**).
  The field, its setup and editor steps are described in [Categories.md](Categories.md).
- **Without a color**, a category gets one from a fixed palette of strong colors
  (`MonthCategoryColorEventListener::PALETTE`). The palette is assigned in order of
  the category titles, over all categories used by events in the plugin's storage
  folders, so a category keeps its color from month to month.

`MonthCategoryColorEventListener` (`GenericActionAssignmentEvent`, month action
only) passes the colors to the templates as `extended.categoryColors`
(category uid → color). The templates set the color as the CSS custom property
`--category-color`; the styles are in `Resources/Public/Scss/Theme/_calendarize.scss`
(`.events-category`, `.btn-category`). Colors work independently of the
category filter setting.

## Speaking URLs

`Configuration/Routes/Calendarize.yaml` adds calendarize's route enhancer for a
site, limited to pages with "Contains plugin" = Calendarize or listed in
`sitepackage.routing.calendarizePages`. See [RouteEnhancers.md](RouteEnhancers.md).
