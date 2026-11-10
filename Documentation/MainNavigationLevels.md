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

## pl2 (Gonsenheim): klickbare Eltern-Punkte

In `Resources/Private/_pl2/Partials/BootstrapPackage/Page/Navigation/` ist jeder
Eltern-Punkt ein echter Link; das Auf-/Zuklappen hängt an einem separaten
Pfeil-Button daneben:

- **Level 1** (`MainNavigation.html`): `<a class="nav-link nav-link-main">` +
  `<button class="nav-link nav-link-main-toggle" data-bs-toggle="dropdown">`
  direkt vor `.dropdown-menu` (Bootstrap findet das Menü über das nächste
  Geschwister-Element). Ab `lg` öffnet das Dropdown per CSS-Hover bzw.
  `:has(:focus-visible)` (`Theme/pl2.scss`); `.dropdown-menu.show` wird
  zusätzlich angezeigt, damit Touch-Geräte über den Pfeil öffnen können.
  Unter `lg` (Offcanvas) öffnet nur der Pfeil.
- **Level 2 mit Kindern** (`MainNavigationDropDown.html`): wie pl0/pl1/pl3 —
  `<a class="nav-link-dropdown">` + `<button class="nav-link-toggle"
  data-bs-toggle="collapse">`; Layout aus `Theme/_navigation-dropdown-level3-toggle.scss`.
- `Resources/Public/JavaScript/offcanvas-navigation.js` öffnet beim Anzeigen
  des Offcanvas den aktiven Zweig; der Selektor erfasst sowohl den klassischen
  Level-1-Toggle (`.nav-link-main[data-bs-toggle]`) als auch den pl2-Toggle
  (`.nav-link-main-toggle`).
- Der Pfeil (`.nav-link-indicator::before`) ist in pl2 dicker als im
  bk2k-Standard: 2px / `.3em` auf dem Desktop, 2.5px / `.65em` im Offcanvas.
- Ab `lg` dreht sich der Level-1-Pfeil, solange das Dropdown per Hover bzw.
  Fokus offen ist (`.nav-item:hover > .nav-link-main-toggle`).
- Ebene 3 ist in pl2 immer zugeklappt gerendert, auch im aktiven Zweig
  (kein `show`/`aria-expanded="true"` aus `item.active`): Sie öffnet nur per
  Klick auf den Pfeil. Ein Klick auf den Ebene-2-Link navigiert nur.
- Zwischen `lg` und `xxl` (992–1399.98px) ist der pl2-Header zweizeilig: Die
  Logo-Zeile steht oben, darunter `.navbar-collapse` mit `flex: 0 0 100%`, und
  `.navbar-nav` ist mit `space-between` über die Containerbreite verteilt
  (`@include media-breakpoint-between(lg, xxl)` in `Theme/pl2.scss`). Ab
  `xxl` ist der Header einzeilig. Die Texte der Hauptpunkte brechen ab `lg`
  nie um (`white-space: nowrap`).
  In diesem Bereich steht das Logo im Fluss (bk2ks `.navbar-brand` mit
  Link-Höhe und `top:50%`/`translate` am `img` aufgehoben), und das
  Navbar-Padding ist fest (`.5rem` oben, `0` unten) statt
  `(nav-height − link-height) / 2`. Die Selektorstärke `.navbar.navbar-mainnavigation`
  ist nötig, damit das Padding auch gegen `A11y/_toolbar.scss` gewinnt.
  Das Logo ist dort auf `max-height: 48px` begrenzt (gegen die
  tenant-eigene Logo-Größe in `custom.scss`; im geschrumpften Zustand
  `.navbar-transition` gilt weiter der Tenant-Wert), die Menüzeile hat
  `.25rem` Innenabstand oben und unten.
- Ab `lg` ist die Header-Höhe in pl2 `--mainnavigation-nav-height: 90px`
  (bk2k: 100/110px). Der geschrumpfte Zustand `.navbar-transition` ist
  ausgenommen und behält die 70px von bk2k.
- Ab `xxl` (einzeiliger Header) ist das Logo auf `max-height: 60px` begrenzt
  (`.navbar-transition` behält den Tenant-Wert aus `custom.scss`). Die
  `max-height`-Transition des Logos steht ebenfalls in `pl2.scss`. Das Navbar-Padding ist dort oben und unten wieder
  symmetrisch `(nav-height − link-height) / 2`. Das überschreibt die
  Regel mit `/ 3` oben aus `A11y/_toolbar.scss` (nur bei aktiver Barrierefreiheits-Leiste), die Logo und Menü sonst
  nach oben aus der Mitte schiebt.
- Mobil (unter `lg`) ist die ganze Zeile die Klickfläche zum Aufklappen, auf
  Ebene 1 und auf Ebene 2 mit Kindern. Das `::after` des Pfeil-Buttons
  (`.nav-link-main-toggle` / `.nav-link-toggle`) liegt als Overlay über dem
  ganzen `<li>`. Der Button selbst ist dafür `position: static`, weil bk2k
  jedem `.nav-link` `position: relative` gibt. Das aufgeklappte Untermenü
  (`.dropdown-menu` bzw. `.collapse`) liegt mit `z-index: 2` darüber, damit
  seine Links klickbar bleiben. Die Elternseite ist mobil damit nicht direkt
  verlinkt. Rückbau: die Overlay-Regel im Mobil-Block von `Theme/pl2.scss`
  entfernen, dann ist der Text mobil wieder ein Link.
- Mobil klappt Ebene 1 (Bootstrap *Dropdown*, eigentlich ohne Animation)
  ohne JS genauso weich auf und zu wie Ebene 3 (Bootstrap *Collapse*, .35s
  ease). Unter `lg` ist `.navbar-nav > .nav-item > .dropdown-menu` immer
  `display: grid` mit `grid-template-rows: 0fr` und `visibility: hidden`;
  `.show` (von Bootstrap Dropdown gesetzt) wechselt auf `1fr` und `visible`,
  und `grid-template-rows` wird animiert. Das Kind `.dropdown-nav` braucht
  `min-height: 0; overflow: hidden`. `visibility` schaltet beim Schließen
  erst nach der Animation um, damit die Links zugeklappt nicht per Tab
  erreichbar sind. Bei `prefers-reduced-motion` gibt es keine Transition.
  Unterstützt ab Chrome 107, Firefox 66 und Safari 16; ältere Browser klappen
  ohne Animation auf.
