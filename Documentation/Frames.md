# Eigene Rahmen (frame_class)

## Woher die Auswahl im Backend kommt

Das Feld „Rahmen“ (`tt_content.frame_class`) wird in drei Schritten zusammengesetzt:

1. **TYPO3 Core** (`vendor/typo3/cms-frontend/Configuration/TCA/tt_content.php`):
   `default`, `ruler-before`, `ruler-after`, `indent`, `indent-left`, `indent-right`, `none`.
2. **bootstrap_package**, Site Set „ContentElements“
   (`Configuration/Sets/ContentElements/PageTsConfig/TCEFORM.tsconfig`): entfernt
   `ruler-before`, `ruler-after`, `indent`, `indent-left`, `indent-right`.
3. **sitepackage**, `Configuration/page.tsconfig` → `TCEFORM.tt_content.frame_class.addItems`.
   Neue Rahmen kommen nur hier hinzu.

Zusätzlich kann das Feld „Page TSconfig“ einer Seite (Datenbank) weitere Einträge ergänzen.

`Configuration/TSConfig/page.tsconfig` und `Configuration/TSConfig/Page/*.tsconfig` werden
nirgends geladen (zudem stimmt der Import-Pfad `TsConfig` nicht mit dem Ordner `TSConfig`
überein) und haben keine Wirkung.

Nach Änderungen an `.tsconfig`-Dateien den Cache leeren (`ddev typo3 cache:flush`), sonst zeigt
das Backend weiter die alte Liste.

Das CSS zu einem Rahmen steht in `Resources/Public/Scss/Theme/global.scss` unter `.frame-<schlüssel>`.

## Ladereihenfolge und Spezifität

Die CSS-Dateien werden in dieser Reihenfolge geladen (`Configuration/TypoScript/Setup/basis.typoscript`):
theme → global → layout (`pl0`–`pl3`) → custom.

Die Layoutdatei kommt **nach** `global.scss`. Eine Rahmen-Regel in `global.scss` setzt sich
deshalb nur über eine höhere Spezifität gegen die Layoutdatei durch, nicht über die Reihenfolge.

## `volle_breite` – Volle Breite (mit Hintergrund)

Der Frame-Wrapper bleibt erhalten, damit die Hintergrundfarbe aus `background_color_class` greift.
Nur die Container-Begrenzung (`max-width`, seitliches Padding) entfällt. Das Padding oben und unten bleibt.

## `sliderstartseite` – Slider (Höhe 72%)

Für ein Carousel auf der Startseite.

**Breite:** Der Slider läuft immer über die volle Breite. Dafür wird bei
`.frame-container-default` die Begrenzung durch bootstrap_package aufgehoben
(`max-width: 100%`, seitliches Padding 0).

**Abstand oben/unten:** Der Slider sitzt bündig, ohne Innenabstand. Dafür wird auf dem Frame
`--frame-spacing: 0` gesetzt. Das ist die Variable, aus der bootstrap_package `padding-top` und
`padding-bottom` von `.frame` berechnet. Die Variable wird statt `padding: 0` gesetzt, weil
bootstrap_package sie auch für den negativen Ausgleichs-Margin zwischen zwei benachbarten Frames
mit gleichem Hintergrund verwendet. So bleibt auch dieser Fall stimmig. Der Selektor `.frame.frame-sliderstartseite`
(0,2,0) schlägt die Breakpoint-Regeln von bootstrap_package, die `--frame-spacing` je Breakpoint neu setzen.
Die Außenabstände „Abstand davor/danach“ (`space_before_class`/`space_after_class`) bleiben
im Backend einstellbar.

**Höhe** von `.carousel .item`. Kleine Slider (`carousel-small`) und Vollbild-Slider
(`carousel-fullscreen`) sind ausgenommen.

| Breakpoint | Höhe |
|---|---|
| < md | `clamp(320px, 55svh, 520px)` |
| md | `clamp(420px, 60svh, 640px)` |
| lg | `clamp(480px, 66svh, 760px)` |
| xl | `clamp(540px, 72svh, 900px)` |

- Der Selektor `.frame-sliderstartseite .carousel:not(.carousel-small):not(.carousel-fullscreen) .item`
  hat die Spezifität 0,5,0. pl3 verwendet für die Carousel-Höhe 0,4,0, pl0–pl2 verwenden 0,2,0.
- `clamp()` begrenzt die Höhe nach unten und oben. Auf niedrigen Laptops wird der Slider so nicht
  zu flach, auf großen Monitoren nicht zu hoch. Bei Full HD ergibt die Formel genau 72vh.
- `svh` ist die Viewport-Höhe bei eingeblendeter Browser-Leiste. Auf Mobilgeräten springt die
  Höhe damit beim Scrollen nicht. Die vorangestellte `vh`-Zeile dient älteren Browsern als Fallback.

## `ohne_abstand` – Ohne Abstand oben/unten

Entfernt den Standard-Innenabstand oben und unten (`padding-top`/`padding-bottom` von `.frame`).
Die Außenabstände „Abstand davor/danach“ (`space_before_class`/`space_after_class`) wirken weiter,
weil sie als Margin über `--frame-outer-spacing-before/after` gesetzt werden.

Umgesetzt mit `.frame.frame-ohne_abstand { --frame-spacing: 0; }` und nicht mit `padding: 0`.
bootstrap_package verschiebt einen Frame, der direkt auf einen Frame mit gleichem Hintergrund und
„Abstand: none“ folgt, um `calc(-1 * var(--frame-spacing))` nach oben (`%frame-collapsible` in
`bootstrap-package/Resources/Public/Scss/components/_frame.scss`). Mit `padding: 0` würde der Frame
deshalb in das vorherige Element rutschen. Setzt man die Variable auf 0, wird dieser Versatz ebenfalls 0.
Die Spezifität 0,2,0 schlägt die Breakpoint-Regeln von bootstrap_package, die `--frame-spacing` setzen.

## Weitere Backend-Optionen

### Icon Group: 5 und 6 Spalten

bootstrap_package bietet beim Inhaltselement `icon_group` im Feld „Spalten“ nur auto, 1, 2, 3 und 4 an
(`Configuration/FlexForms/IconGroup.xml`, Sheet `sDEF`, Feld `columns`). In `Configuration/page.tsconfig`
kommen 5 und 6 hinzu: `TCEFORM.tt_content.pi_flexform.icon_group.sDEF.columns.addItems`.
Seit TYPO3 13 ist der FlexForm-Schlüssel der CType.

Das Template setzt daraus die Klasse `icongroup-columns-{n}`. bootstrap_package liefert CSS nur bis `-4`,
deshalb stehen die Breiten für 5 und 6 in `global.scss` (für alle Layouts). Die Abstufung folgt dem Muster von bk2k:

| Breakpoint | 5 | 6 |
|---|---|---|
| < sm | 1 | 1 |
| sm | 2 | 2 |
| md | 3 | 3 |
| xl | 5 | 6 |

### pl3: `startpage-quicklinks` mobil zweispaltig

In pl3 zeigt eine Icon Group im Rahmen „Startseiten-Kacheln“ auch unter `sm` zwei Kacheln pro Reihe
(statt 1), bei 3 Spalten zusätzlich zwischen `sm` und `md`. Das gilt nur für die Icon-Positionen
`right-top`, `right-center` und `below` und nur für die Spaltenwerte 2–6, nicht für auto/1.

`left-center`, `left-top` und `above` bleiben unter `sm` einspaltig. `left-top` und `above` sehen dort
aus wie `left-center`: Icon links, vertikal mittig zum Text, Text linksbündig. Ab `sm` gilt wieder ihr
normales Layout. Damit lange Wörter in die schmale Kachel passen, trennt der Text mit
`hyphens: auto` (Silbentrennung nach Seitensprache) und bricht notfalls per `overflow-wrap: anywhere`
um; `min-width: 0` auf Kachel und Text verhindert, dass der Flex-Inhalt die Breite aufdrückt.
Unter `sm` wird außerdem das Innen-Padding der Kachel reduziert.


## Frame-Layout `box_image_text` – „Box: Bild und Text“

Das ist kein `frame_class`, sondern ein eigener Eintrag in `frame_layout`
(`TCEFORM.tt_content.frame_layout.addItems` in `Configuration/page.tsconfig`). Er wirkt auf
Text & Bild-Elemente (`.textpic`). Die Box-Farbe richtet sich nach dem gewählten Hintergrund
(`frame-background-primary` / `-secondary` / `-light`, Map `$box-colors` in `Theme/_layout-variables.scss` §9).

Je nach Layout sieht es unterschiedlich aus:

- **pl1**: Die Text-Box überlappt das Bild (Verhältnis 5:7, Schatten). Variablen `$box-*` in
  `_layout-variables.scss` §9.
- **pl2** (`Theme/pl2.scss` Abschnitt 12): flacher Split 50/50. Bild und Box sind gleich hoch, ohne
  Überlappung und ohne Schatten, mit gemeinsamen abgerundeten Außenkanten (`$card-radius`,
  `overflow: hidden` am `.textpic`). Der Text ist vertikal zentriert. Eigene Variablen
  `$split-pad`, `$split-pad-mobile`, `$split-image-height` und `$split-image-height-mobile`, damit pl1 unberührt bleibt.
  Mobil steht das Bild oben und die Box direkt darunter. „Bild links“ (`.textpic-left`) tauscht ab `lg`
  die Spalten.
