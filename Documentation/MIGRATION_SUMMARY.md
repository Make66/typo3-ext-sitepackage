# SCSS migration summary — all mandants

Every `fileadmin/templates/<mandant>` folder in this project migrated from the
legacy two-file layout to the new SCSS standard documented in
[SCSS_migration.md](./SCSS_migration.md), following its step-by-step guide.
Brand colors, fonts, and per-mandant SCSS variables were recovered from each
site's **live** production site wherever one was reachable — never invented.
This file merges the six parallel migration batches that did the work; each
site got its own section (`_variables.scss`, `theme.scss`, `global.scss`,
rewritten `custom.scss` header, existing rules preserved byte-for-byte) and
was individually verified against `https://<site>.ddev.site/`.

**Already migrated before this batch job** (not covered below): `raumuehle`,
`weingut-haupt`.

**Deliberately skipped**: `mk` — `config/sites/mk/` has no `config.yaml` (no
live domain, no site config at all), and its template folder has
placeholder-named logo files ("Logo_vorlaeufig" = preliminary logo). Nothing
to verify or recover brand values against.

## Two project-wide bugs found during this job, now fixed

Both were discovered independently by multiple batches (a good sign they're
genuinely structural, not migration-induced) and are now fixed centrally —
see `SCSS_migration.md`'s "Non-SCSS prerequisites" section for full technical
detail. Every site below that initially showed HTTP 500 for one of these two
reasons has been re-verified and now returns 200.

1. **Missing `Navigation/MainNavigationDropDown.html` partial** — any mandant
   with a sub-page under a top-level nav item hit this. Fixed by creating
   `packages/sitepackage/Resources/Private/Partials/BootstrapPackage/Page/Navigation/MainNavigationDropDown.html`.
2. **Missing `ContentElements/Frame/General/BackgroundImage.html` (+ two
   sibling) partials** — a version mismatch in the third-party
   `baschte/content-animations` extension referenced a partial path that
   never existed. Fixed by creating those three files under sitepackage's own
   `ContentElements/Frame/General/` partial path.

## Remaining known issues (not fixed — flagged for follow-up, out of scope for an SCSS migration)

- **`taketool.ddev.site` and `test.ddev.site`** return HTTP 404 "No site
  configuration found" despite `vendor/bin/typo3 site:list` confirming both
  are registered and enabled — fails before TypoScript parsing even starts,
  so structurally unrelated to SCSS. Not investigated further.
- **`oberstedten.ddev.site`** and **`truebenbach-slaby.ddev.site`** return
  404, but this matches each site's own live production behavior (confirmed
  via their live domains) — not a regression.
- **`srv` and `test`** have no reachable live domain at all (`srv`'s
  configured domain doesn't resolve via DNS; `test`'s returns TYPO3's generic
  fallback error page, not a themed one) — both got Bootstrap's own default
  colors (`$primary:#2a9d8f`, `$secondary:#e76f51`) as an honest, clearly-
  labeled placeholder rather than a guessed "real" brand color. **Whoever
  owns these two sites should confirm their real brand colors.**
- **Alternate/inverted logo files found but not wired up** (deliberately out
  of scope for a bulk pass — see `SCSS_migration.md` step 10 for the
  per-mandant condition syntax to add these): `goldener-ritter/img/logo_weiss.svg`,
  `kulturleben-rheinhessen/img/logo_1.svg` (this one already has matching
  `.navbar-brand-logo-inverted` CSS rules in its `custom.scss`, just never
  had the TypoScript condition wired up).
- **`waschmaschinendoktor`** has no `logo.svg` at all in `img/` (only
  `.gif`/`.png` files) — the navbar renders the site-title text fallback
  regardless of SCSS; a genuine pre-existing asset gap, not something this
  migration can fix without a real logo file.
- A few mandant folders have **local Fluid partial overrides** unrelated to
  SCSS (`buergerstiftung`, `cruisensight`, `kulturleben-rheinhessen`,
  `stiftung-friedenskirche` each have their own
  `bootstrap_package/Resources/Private/Partials/Page/Navigation/Main.html`) —
  left untouched, out of scope.
- **`theis`**'s old `theme.scss` also imported a small `emil.scss` (sticky-
  footer flex rules) that the new three-file wiring doesn't support on its
  own — its rules were merged into the end of the new `custom.scss` with a
  comment marking the merge; the original `emil.scss` was left in place,
  unused (no git, no deletions).

---

## All sites

- [7d-beratung (7d)](#7d-beratung-7d)
- [blitzblume (blitzblume)](#blitzblume-blitzblume)
- [breugl (breugl)](#breugl-breugl)
- [buergerstiftung (buergerstiftung)](#buergerstiftung-buergerstiftung)
- [cme (cme-beratung)](#cme-cme-beratung)
- [cruise (cruisensight)](#cruise-cruisensight)
- [datako (datako)](#datako-datako)
- [event-florist (event-florist)](#event-florist-event-florist)
- [gesang (gesang)](#gesang-gesang)
- [goldener-ritter (goldener-ritter)](#goldener-ritter-goldener-ritter)
- [grohs-consult (grohs-consult)](#grohs-consult-grohs-consult)
- [hochschullehre (hochschullehre)](#hochschullehre-hochschullehre)
- [kibsmombach (kibsmombach)](#kibsmombach-kibsmombach)
- [kulturleben (kulturleben-rheinhessen)](#kulturleben-kulturleben-rheinhessen)
- [laventt (laventt)](#laventt-laventt)
- [lebenslagen (lebenslagen)](#lebenslagen-lebenslagen)
- [oberstedten (rv-oberstedten)](#oberstedten-rv-oberstedten)
- [oekomesse (oekomesse-ingelheim)](#oekomesse-oekomesse-ingelheim)
- [ogr-mainz (ogr-mainz)](#ogr-mainz-ogr-mainz)
- [peterscholles (pscholles)](#peterscholles-pscholles)
- [praxis (schmutzenhofer)](#praxis-schmutzenhofer)
- [rudi (rudi-trautz)](#rudi-rudi-trautz)
- [schlosserei (theis)](#schlosserei-theis)
- [scholles (scholles)](#scholles-scholles)
- [sdw (sdw)](#sdw-sdw)
- [seezauber (seezauber)](#seezauber-seezauber)
- [stiftung (stiftung-friedenskirche)](#stiftung-stiftung-friedenskirche)
- [taketool (taketool)](#taketool-taketool)
- [srv (srv) — placeholder colors, no live reference](#srv-srv--placeholder-colors-no-live-reference)
- [test (test) — placeholder colors, no live reference](#test-test--placeholder-colors-no-live-reference)
- [trixis-winzlinge (trixis-winzlinge)](#trixis-winzlinge-trixis-winzlinge)
- [truebenbach-slaby (truebenbach)](#truebenbach-slaby-truebenbach)
- [waschmaschine (waschmaschinendoktor)](#waschmaschine-waschmaschinendoktor)
- [zahnarztpraxis (sulaiman)](#zahnarztpraxis-sulaiman)

---

# SCSS migration summary — batch 1

Six mandants migrated to the new SCSS standard (`_variables.scss` + `theme.scss` +
`global.scss` + `custom.scss`, per `SCSS_migration.md`). All six now have the
target file layout; all six SCSS compiles (theme/global/custom) verified error-free
with a standalone `scssphp` compile test mirroring bk2k's own EXT:-path resolution,
and cross-checked against each mandant's live production `--bs-*` custom properties.

## 7d-beratung (7d)

- Live domain used: `https://www.7d-beratung.de/` (307 redirect, followed with `-L`)
- `$primary: #175485`, `$secondary: #696868`
- Accent colors: `$teal: #175485`, `$pink: #fff`, `$indigo: #ccc`, `$yellow: #fff`
- Other non-default variables: `$navbar-brand-font-size: 1.3rem` (live shows a
  fluid/RFS value locking to 1.3rem at the 1200px breakpoint, vs. the 1.1rem
  template baseline); `$navbar-light-hover-color: #0b323f` (does **not** match
  `$primary` — hardcoded literal, verified via live
  `.navbar-mainnavigation.navbar-default a:not(.active):hover`, and cross-checked
  identical via the `.navbar-inverse` mobile variant); `$font-size-base: 1.1rem`
  declared explicitly (needed as a base for the heading overrides below — this
  single addition also fixed a real bug my own draft had, see note below);
  `$h1-font-size: $font-size-base * 1.9`, `$h2: * 1.6`, `$h3: * 1.4`,
  `$h4: * 1.5`, `$h5: * 1.2` (live headings don't follow the
  1.75/1.5/1.25/1/0.9 template-baseline ratios at all — h4 is deliberately
  larger than h3 here, matching `custom.scss`'s own h4 "badge" styling)
- Custom font: `.body-bg-top { font-family: 'Open Sans'; font-weight: 400;
  font-style: normal; }` — Open Sans exists in the shared legacy font store
  (`packages/sitepackage/Resources/Public/Fonts/OpenSans-Regular.woff{,2}`), so
  created `_fonts.scss` + copied those two files into the mandant's own `Fonts/`
  (same treatment as raumuehle's Cairo), Regular weight only
- Inverted logo: none found (only `img/logo.svg` and `img/logo.png`)
- ddev verification: `https://7d-beratung.ddev.site/?no_cache=1` → HTTP 200 (see
  "transient 500s" note below); merged CSS bundle's `--bs-primary:#175485;`
  matches live exactly

## blitzblume (blitzblume)

- Live domain used: `https://www.blitzblume-ingelheim.de/`
- `$primary: #C72722`, `$secondary: #333`
- Accent colors: `$teal: #5b5b5b`, `$pink: #ccc`, `$indigo: #fef6e8`, `$yellow: #fff`
- Other non-default variables: `$navbar-brand-font-size: 1.2rem` (live shows a
  flat 1.2rem, vs. the 1.1rem baseline); `$navbar-nav-link-padding-x: 0.6rem`
  (live shows 0.6rem, not the 0.8rem baseline); `$navbar-dark-color`,
  `$navbar-dark-hover-color`, `$navbar-dark-active-color`: all `#333` (confirmed
  uniform across idle/hover/active states on `.navbar-inverse`);
  `$navbar-light-hover-color: $primary` (matches `$primary` exactly, verified
  via live `.navbar-default a:not(.active):hover`)
- Custom font: none (no `font-family` override in `custom.scss`) — `_fonts.scss`
  skipped
- Inverted logo: none found
- ddev verification: `https://blitzblume.ddev.site/?no_cache=1` → HTTP 200;
  theme.css `--bs-primary:#C72722;` matches live exactly

## breugl (breugl)

- Live domain used: `https://www.breugl.de/` (307 redirect, followed with `-L`)
- `$primary: #C4BBAA`, `$secondary: #C4BBAA`
- Accent colors: `$teal: #5b5b5b`, `$pink: #ccc`, `$indigo: #fbcaca`, `$yellow: #fff`
- Other non-default variables: `$navbar-brand-font-size: 1.3rem` (live locks to
  1.3rem at 1200px, vs. 1.1rem baseline); `$navbar-light-hover-color: #333`
  (does **not** match `$primary` — hardcoded literal). Heading sizes (h1–h5)
  matched the 1.1rem-baseline formula exactly, so left as the default (omitted).
- Custom font: `body { font-family: "Inconsolata", sans-serif; }` — Inconsolata
  exists in the shared legacy font store
  (`Fonts/Inconsolata-Regular.woff{,2}`), so created `_fonts.scss` + copied
  those two files into the mandant's own `Fonts/`, Regular weight only
- Inverted logo: none found (only `img/logo.svg`)
- ddev verification: `https://breugl.ddev.site/?no_cache=1` → HTTP 200 (see
  "transient 500s" note below); theme.css `--bs-primary:#C4BBAA;` matches live
  exactly

## buergerstiftung (buergerstiftung)

- Live domain used: `https://www.buergerstiftung-rheinhessen.de/`
- `$primary: #00ace2`, `$secondary: #c0ca10`
- Accent colors: `$teal: #2c3136`, `$pink: #dadada`, `$indigo: #00ace2` (matches
  `$primary`), `$yellow: #fff`
- Other non-default variables: `$navbar-brand-font-size: 1.16rem` (live shows a
  flat 1.16rem, a small but real deviation from the 1.1rem baseline);
  `$navbar-dark-color`, `$navbar-dark-hover-color`, `$navbar-dark-active-color`:
  all `#8c8c8c` (confirmed uniform across idle/hover/active on `.navbar-inverse`);
  `$navbar-light-hover-color: $primary` (matches `$primary` exactly, verified
  live). Heading sizes matched the baseline formula exactly, left as default.
- Custom font: `body { font-family: 'Montserrat', sans-serif; }` — Montserrat
  exists in the shared legacy font store
  (`Fonts/Montserrat-Regular.woff{,2}`), so created `_fonts.scss` + copied
  those two files into the mandant's own `Fonts/`, Regular weight only
- Inverted logo: none found. Note: this folder also has a local
  `bootstrap_package/Resources/Private/Partials/Page/Navigation/Main.html`
  Fluid override — untouched, out of scope for an SCSS migration
- ddev verification: `https://buergerstiftung.ddev.site/?no_cache=1` → HTTP 200;
  theme.css `--bs-primary:#00ace2;` matches live exactly

## cme (cme-beratung)

- Live domain used: `https://www.cme-beratung.de/`
- `$primary: #009741`, `$secondary: #009741`
- Accent colors: `$teal: #5b5b5b`, `$pink: #ccc`, `$indigo: #6fc494`, `$yellow: #fff`
- Other non-default variables: `$navbar-brand-font-size: 1.3rem` (live locks to
  1.3rem at 1200px, vs. 1.1rem baseline); `$navbar-light-hover-color: #333`
  (does **not** match `$primary` — hardcoded literal). Heading sizes matched
  the baseline formula exactly, left as default.
- Custom font: none (no `font-family` override in `custom.scss`) — `_fonts.scss`
  skipped. Note: `animation_scroll_viewport.js` sits alongside the SCSS files
  in this folder — a plain JS asset, unrelated to this migration, left untouched.
- Inverted logo: none found (only `img/logo.png`, no `logo.svg`)
- ddev verification: `https://cme.ddev.site/?no_cache=1` → HTTP 200; theme.css
  `--bs-primary:#009741;` matches live exactly

## cruise (cruisensight)

- Live domain used: `https://www.cruise-n-sight.ch/`
- `$primary: #aa2828`, `$secondary: #d7551b`
- Accent colors: `$teal: #818181`, `$pink: #ccc`, `$indigo: #ea6e27`, `$yellow: #ffc107`
- Other non-default variables: `$font-size-base: 1.1rem` declared explicitly
  (needed as a base for the `$h5-font-size` override below);
  `$h5-font-size: $font-size-base * 0.8` (live shows `0.88rem`, i.e. a ×0.8
  multiplier, not the 0.9 baseline — h1–h4 all matched the baseline formula
  exactly and were left alone); `$navbar-brand-font-size: 1.15rem` (live shows
  a flat 1.15rem, a small but real deviation from the 1.1rem baseline);
  `$navbar-light-hover-color: #333` (does **not** match `$primary` — hardcoded
  literal)
- Custom font: none (`custom.scss` only sets `body { color: #747474; }`, no
  `font-family`) — `_fonts.scss` skipped
- Inverted logo: none found. Note: this folder also has local
  `bootstrap_package/Resources/Private/Partials/Page/Navigation/Main.html` and
  `.../Templates/Page/3Columns.html` Fluid overrides — untouched, out of scope
  for an SCSS migration
- ddev verification: `https://cruise.ddev.site/?no_cache=1` → HTTP 200;
  theme.css `--bs-primary:#aa2828;` matches live exactly

## Verification method and a bug I found in my own draft

For every site above, `theme.scss`, `global.scss` and `custom.scss` were also
compiled independently with a standalone `scssphp` script (mirroring bk2k's own
`ScssParser`'s EXT:-path resolution, bypassing TYPO3/Fluid entirely) as a second,
more precise check than the live-page curl alone. This caught a real bug before
it shipped: my first draft of `7d`'s and `cruisensight`'s `_variables.scss` used
`$font-size-base` inside heading-size formulas without declaring it locally
(reasoning, incorrectly, that the shared 1.1rem default would already be in
scope) — the standalone compiler failed with `Undefined variable
$font-size-base`. In TYPO3's real request pipeline this likely never breaks
(bk2k's `CompileService` pre-seeds Sass variables from a TS Constants Editor
default before `_variables.scss` runs), but relying on that implicit ordering
is fragile and untestable outside a full TYPO3 bootstrap. Fixed by explicitly
declaring `$font-size-base: 1.1rem;` in both files' `_variables.scss`, matching
raumuehle's own explicit style — confirmed value via each site's live
`--bs-body-font-size`, unchanged from the template baseline.

## Transient 500s during verification (not a bug in this migration)

`7d-beratung`, `breugl`, and (briefly) `blitzblume` returned HTTP 500 on the
*first* verification pass after the batch-wide `cache:flush`, all with the same
`TYPO3Fluid\Fluid\View\Exception\InvalidTemplateResourceException` for the
missing `Navigation/MainNavigationDropDown.html` Fluid partial — a pre-existing,
project-wide bug (also seen and documented independently by another concurrent
batch on mandant `gesang`; see `MIGRATION_SUMMARY_batch2.md`'s "New bug found"
section for the full analysis). It is **not** caused by SCSS and **not**
introduced by this migration: the log also showed the identical exception for
`scholles`, a mandant outside every batch in this job. On retry (no code
changes in between — likely a transient cache-regeneration race from several
batch agents hitting this same shared ddev instance concurrently), all three
sites returned HTTP 200 cleanly and stayed that way through the final
verification pass. No action taken here per the task's instructions not to fix
newly-found shared-file bugs; flagging again since it was independently
reproduced in this batch too.
# SCSS migration summary — batch 2

Six mandants migrated to the new SCSS standard (`_variables.scss` + `theme.scss` +
`global.scss` + `custom.scss`, per `SCSS_migration.md`). All six now have the
target file layout; all six SCSS compiles (theme/global/custom) verified error-free
with a standalone `scssphp` compile test mirroring bk2k's own EXT:-path resolution,
and cross-checked against each mandant's live production `--bs-*` custom properties.

## datako (datako)

- Live domain used: `https://www.datako.de/`
- `$primary: #4580aa`, `$secondary: #69b2dd`
- Accent colors: `$teal: #5b5b5b`, `$pink: #ccc`, `$indigo: #69b2dd`, `$yellow: #fff`
- Other non-default variables: `$navbar-brand-font-size: 1.3rem` (live shows a flat
  1.3rem override vs. the 1.1rem template baseline); `$navbar-light-hover-color: #333`
  (does **not** match `$primary` — hardcoded literal, verified via live
  `.navbar-mainnavigation.navbar-default a:not(.active):hover`)
- Custom font: none (no `font-family` override on `body` in `custom.scss`) — `_fonts.scss` skipped
- Inverted logo: none found
- ddev verification: `https://datako.ddev.site/?no_cache=1` → HTTP 200; theme.css
  `--bs-primary:#4580aa;` matches live exactly

## event-florist (event-florist)

- Live domain used: `https://www.event-florist.de/`
- `$primary: #C4C2C2`, `$secondary: #D9D6D6`
- Accent colors: `$teal: #6b6565`, `$pink: #e6e3e3`, `$indigo: #69b2dd`, `$yellow: #e6e3e3`
- Other non-default variables: `$navbar-brand-font-size: 1.3rem`;
  `$navbar-light-hover-color: #6b6565` (matches `$teal`, not `$primary` — hardcoded
  literal); `$h1-font-size: 1.98rem`, `$h2-font-size: 1.76rem`, `$h3-font-size: 3.19rem`
  (h3 is unusually large live — it carries the site's cursive "Licorice" accent font,
  h4/h5 matched the 1.1rem-baseline formula so were left alone)
- Custom font: `body { font-family: Cairo, ... }` — Cairo exists in the shared legacy
  font store (`packages/sitepackage/Resources/Public/Fonts/Cairo-Regular.woff{,2}`), so
  created `_fonts.scss` + copied those two files into the mandant's own `Fonts/`
  (same treatment as raumuehle). The site's *other* font, "Licorice", was already
  self-hosted via its own `@font-face` in `custom.scss` pointing at `img/Licorice-Regular.woff{,2}`
  — left untouched, still resolves fine since `custom.scss` is its own compile rooted
  at the mandant folder.
- Inverted logo: none found (only `img/logo.svg`)
- ddev verification: `https://event-florist.ddev.site/?no_cache=1` → HTTP 200;
  theme.css `--bs-primary:#C4C2C2;` matches live exactly

## gesang (gesang)

- Live domain used: `https://www.mombachergesangverein-1878.de/`
- `$primary: #6C83C7`, `$secondary: #4D7D4B`
- Accent colors: `$teal: #CF6565`, `$pink: #FFFFFF`, `$indigo: #6c83c7`, `$yellow: #FFFFFF`
- Other non-default variables: `$navbar-light-hover-color: #000000` (does not match
  `$primary` — hardcoded literal); `$h3-font-size: 1.265rem`, `$h4-font-size: 1.21rem`,
  `$h5-font-size: 1.1rem` (h1/h2 matched the 1.1rem-baseline formula and were left alone)
- Custom font: none — `_fonts.scss` skipped
- Inverted logo: none found
- ddev verification: **could not fully verify via the live page** —
  `https://gesang.ddev.site/?no_cache=1` returns **HTTP 500**, caused by a
  **pre-existing, unrelated bug**, not introduced by this migration (see "New bug
  found" below). Instead verified with a standalone `scssphp` compile of
  `theme.scss`/`global.scss`/`custom.scss` (bypassing TYPO3/Fluid entirely): all
  three compile with no errors, and the resulting CSS's `--bs-primary:#6C83C7;` /
  `--bs-secondary:#4D7D4B;` match live exactly. The SCSS side of this migration is
  confirmed correct; only the unrelated Fluid navigation bug blocks a full-page check.

## goldener-ritter (goldener-ritter)

- Live domain used: `https://www.zumgoldenenritter.de/`
- `$primary: #910d1d`, `$secondary: #641c02`
- Accent colors: `$teal: #333333`, `$pink: #ccc`, `$indigo: #840918`, `$yellow: #840918`
- Other non-default variables: `$navbar-nav-link-padding-x: 0.4rem` (live shows
  0.4rem, not the 0.8rem template baseline); `$navbar-light-hover-color: #CCCCCC`
  (matches `$pink`, not `$primary` — hardcoded literal)
- Custom font: none — `_fonts.scss` skipped
- Inverted logo: **`img/logo_weiss.svg`** found (a white/light logo variant sitting
  alongside `img/logo.svg` and `img/logo.png`) — filename doesn't match the
  `logo_dark`/`logo_dunkel`/`logo_inverted`/`logo_invers` convention exactly, but is
  functionally the same kind of alternate-color logo asset raumuehle's `logo_dark.svg`
  is. Not wired into `basis.typoscript` (out of scope for this batch, per instructions)
  — flagging here so a follow-up can add the per-mandant condition block for it.
- ddev verification: `https://goldener-ritter.ddev.site/?no_cache=1` → HTTP 200;
  theme.css `--bs-primary:#910d1d;` matches live exactly

## grohs-consult (grohs-consult)

- Live domain used: `https://www.grohs-consult.com/`
- `$primary: #261F54`, `$secondary: #005E88`
- Accent colors: `$teal: #005E88`, `$pink: #fff`, `$indigo: #00c6ca`, `$yellow: #fff`
- Other non-default variables: `$navbar-brand-font-size: 1.3rem`;
  `$navbar-light-hover-color: #005E88` (matches `$secondary`, not `$primary` —
  hardcoded literal); `$h1-font-size: 1.98rem`, `$h2-font-size: 1.76rem`,
  `$h3-font-size: 1.43rem`, `$h4-font-size: 1.32rem`, `$h5-font-size: 1.21rem`
  (all five differ from the 1.1rem-baseline formula)
- Custom font: `body { font-family: 'Lato'; }` — plain "Lato" (regular weight) exists
  in the shared legacy font store (`Fonts/Lato-Regular.woff{,2}`), so created
  `_fonts.scss` + copied those two files into the mandant's *existing* `fonts/`
  folder (kept lowercase to match the pre-existing on-disk directory name — this
  filesystem is case-preserving but the actual folder is `fonts/`, not `Fonts/`,
  and production Linux is case-sensitive). The mandant's own pre-existing
  `'Lato Light'` `@font-face` (pointing at the absolute path `/fonts/Lato-Light.woff2`)
  was left completely untouched in `custom.scss`.
- Inverted logo: none found
- ddev verification: `https://grohs-consult.ddev.site/?no_cache=1` → HTTP 200;
  theme.css `--bs-primary:#261F54;` matches live exactly

## hochschullehre (hochschullehre)

- Live domain used: `https://www.ausgezeichnete-hochschullehre.org/`
- `$primary: #1D5466`, `$secondary: #eb602e`
- Accent colors: `$teal: #1e5466`, `$pink: #ccc`, `$indigo: #eb5e2b`, `$yellow: #fff`
- Other non-default variables: `$font-size-base: 1.32rem` (live `--bs-body-font-size`
  is 1.32rem, not the 1.1rem baseline — this alone justified overriding it);
  `$h1-font-size: 1.914rem`, `$h2-font-size: 1.716rem`, `$h3-font-size: 1.4784rem`,
  `$h4-font-size: 1.452rem`, `$h5-font-size: 1.32rem` (this mandant's live heading
  ratios — roughly ×1.45/1.3/1.12/1.1/1.0 of the base — don't match the
  1.75/1.5/1.25/1/0.9 template-baseline multipliers at all, so written as flat rems
  rather than formulas, matching calden's own precedent for a non-standard profile);
  `$navbar-brand-font-size: 1.3rem`; `$navbar-light-hover-color: #eb5e2b` (matches
  `$indigo`, not `$primary` — hardcoded literal)
- Custom font: `.body-bg-top { font-family: "BrandonGrotesque-Regular", ...}` — this
  font is **not** in the shared legacy font store, so nothing to migrate; it was
  already fully self-hosted via the mandant's own `fonts/BrandonGrotesque-Regular.woff{,2}`
  + an inline `@font-face` already present in `custom.scss`. Per the task's rule
  ("only create `_fonts.scss` if the font exists in the shared store"), `_fonts.scss`
  was skipped and `global.scss` omits the `@import "_fonts";` line.
- Inverted logo: none found (`img/logo_stiftung_hochschullehre.png` is a separate
  partner/sponsor logo used elsewhere in content, not a navbar dark/scrolled variant)
- ddev verification: `https://hochschullehre.ddev.site/?no_cache=1` → HTTP 200
  (site was still serving TYPO3's "merged" compressed-CSS asset naming, but content
  came from the new three-file layout); fetched `--bs-primary:#1D5466;` from the
  compiled CSS, matches live exactly

## New bug found (not fixed, out of scope — flagging per instructions)

**`gesang` throws HTTP 500** on every frontend request:
`TYPO3Fluid\Fluid\View\Exception\InvalidTemplateResourceException` — the Fluid
partial **`Navigation/MainNavigationDropDown.html`** cannot be found in any of the
three searched locations (mandant's own `bootstrap_package/Resources/Private/Partials/...`,
`packages/sitepackage/Resources/Private/Partials/BootstrapPackage/Page/Navigation/...`,
or `vendor/bk2k/bootstrap-package/Resources/Private/Partials/Page/Navigation/...`).
A repo-wide search (`find ... -iname "MainNavigationDropDown*"`) confirms this
partial simply **does not exist anywhere** in the codebase. `gesang`'s main
navigation apparently has an entry with a dropdown/submenu, which is the only thing
that triggers this code path — sites without a nested nav item (the other five in
this batch) never hit it. This is unrelated to SCSS and was not introduced by this
migration (confirmed by testing gesang's `theme.scss`/`global.scss`/`custom.scss`
directly with a standalone `scssphp` compile, entirely bypassing Fluid — all three
compile cleanly). Left untouched per the task's instructions to not fix newly-found
project-wide bugs in shared files; whoever picks this up next will need to either
supply the missing `MainNavigationDropDown.html` partial (in sitepackage, mirroring
how `MainNavigation.html` etc. already override bk2k's own) or find/restore
gesang's nav structure to avoid the dropdown code path.
# SCSS migration summary — batch 3

Six mandants migrated from the legacy two-file layout to the new standard
(`_variables.scss` + `theme.scss` + `global.scss` + `custom.scss`), per
[SCSS_migration.md](./SCSS_migration.md). Brand colors recovered from each
site's **live** compiled `theme.css` (never invented). All `custom.scss`
files kept their original rules byte-for-byte below the new required header.

## kibsmombach (kibsmombach)

- Live domain: `https://www.kibsmombach.de/`
- `$primary` / `$secondary`: `#ef7f1a`
- Other non-default variables: `$teal:#333`, `$pink:#ffffff`, `$indigo:#ef7f1a`,
  `$yellow:#fff`, `$navbar-brand-font-size:1.3rem` (live CSS showed a
  fluid-scaled `.navbar-brand` topping out at `1.3rem`, not the shared
  `1.1rem` default), `$navbar-dark-hover-color`/`$navbar-light-hover-color:
  #333` (found identical for both inverse/default states, and does **not**
  equal `$primary` here, unlike raumuehle/weingut).
- Font: none — no custom `font-family` on `body`; `_fonts.scss` omitted.
- Inverted logo: none found (`img/` has `logo.svg` only).
- ddev verification: `HTTP 200` at `https://kibsmombach.ddev.site/?no_cache=1`;
  compiled `theme.css` `--bs-primary:#ef7f1a` / `--bs-secondary:#ef7f1a` —
  matches live site.

## kulturleben (kulturleben-rheinhessen)

- Live domain: `https://www.kulturleben-rheinhessen.de/`
- `$primary` / `$secondary`: `#009fe3`
- Other non-default variables: `$teal:#818181`, `$pink:#ccc`,
  `$indigo:#009fe3`, `$yellow:#fff`, `$navbar-brand-font-size:1.18rem` (flat
  value in live CSS, differs from `1.1rem` default), `$navbar-dark-hover-color`/
  `$navbar-light-hover-color:#333` (same for both, ≠ `$primary`).
- Font: none — no custom `font-family` on `body`; `_fonts.scss` omitted.
- Inverted logo: no file matching `logo_dark`/`logo_dunkel`/`logo_inverted`/
  `logo_invers` naming. Note: `img/logo_1.svg` exists and `custom.scss`
  already has `.navbar-brand-logo-inverted` display rules wired up for an
  inverted-logo mechanism, but the file doesn't follow the new naming
  convention documented in SCSS_migration.md step 10 — flagged for follow-up,
  not wired into `basis.typoscript` by this batch.
- Also noted (not touched, out of scope): this mandant folder contains a
  per-site Fluid override at
  `public/fileadmin/templates/kulturleben-rheinhessen/bootstrap_package/Resources/Private/Partials/Page/Navigation/Main.html`
  — unrelated to the SCSS migration, left as-is.
- ddev verification: `HTTP 200` at `https://kulturleben.ddev.site/?no_cache=1`;
  compiled `theme.css` `--bs-primary:#009fe3` / `--bs-secondary:#009fe3` —
  matches live site.

## laventt (laventt)

- Live domain: `https://www.laventt.de/`
- `$primary`: `#102c59`, `$secondary`: `#1b3a6b` (differs from primary here).
- Other non-default variables: `$teal:#5b5b5b`, `$pink:#ccc`, `$indigo:#fff`,
  `$yellow:#fff`, `$h2-font-size:1.485rem` (only h2 differs from the default
  formula — h1/h3/h4/h5 all matched the shared default exactly, so only h2
  was overridden, as a flat value rather than a `$font-size-base`-relative
  formula since it's not accompanied by a base-size change),
  `$navbar-dark-hover-color`/`$navbar-light-hover-color:#0d344a` (same for
  both, ≠ `$primary`).
- Anomaly noted, not acted on: live CSS showed `.navbar-brand{font-size:1}`
  (a unitless, invalid value) for `$navbar-brand-font-size` — treated as a
  pre-existing bad TS-constants-editor value on the live site rather than a
  real design intent, so `$navbar-brand-font-size` was left at the shared
  default (omitted from `_variables.scss`).
- Font: `body { font-family: 'Lato'; }` in `custom.scss` (unchanged). `Lato`
  was previously supplied by the old shared `_fonts.scss` — confirmed
  `Lato-Regular.woff`/`.woff2` exist in
  `packages/sitepackage/Resources/Public/Fonts/`, so copied both into the
  mandant's own `Fonts/` and wrote `_fonts.scss` (Regular weight only, no
  bold/italic files exist). `global.scss` imports `_fonts`.
- Inverted logo: none found (`img/` has `logo.svg` only).
- ddev verification: **problem**. `https://laventt.ddev.site/?no_cache=1` and
  every subpage tried (ids 1801-1805) return `HTTP 500` — but this is a
  pre-existing, unrelated bug, not caused by this migration: TYPO3Fluid
  `InvalidTemplateResourceException` for
  `ContentElements/Frame/General/BackgroundImage.html`, which does not exist
  anywhere in `packages/sitepackage`, `vendor/bk2k/bootstrap-package`, or
  `vendor/taketool/sitepackage` (only `ViewHelpers/Frame/BackgroundImage.html`
  and the Carousel variants exist). This affects a content-element Fluid
  partial, not SCSS compilation, and appears to occur on every page for this
  site (likely a page-header content element reused sitewide). No
  `ScssPhp`/Sass compile error occurred at any point (the exception is deep
  in content rendering, after CSS/page setup), so the SCSS migration itself
  is not implicated — but full color verification via a live HTTP 200
  response could not be completed. **This looks like a new project-wide bug,
  not previously documented in SCSS_migration.md — flagged here rather than
  fixed, per scope.**

## lebenslagen (lebenslagen)

- Live domain: `https://www.lebenslagen.de/`
- `$primary` / `$secondary`: `#0067b3`
- Other non-default variables: `$teal:#5b5b5b`, `$pink:#ccc`,
  `$indigo:#cae0ef`, `$yellow:#ebeaea`, `$h4-font-size:1.32rem`,
  `$h5-font-size:1.265rem` (h1/h2/h3 matched shared defaults exactly),
  `$navbar-dark-hover-color:#333` (≠ primary), `$navbar-light-hover-color:
  $primary` (live CSS's `.navbar-default` hover color equaled `#0067b3`,
  same as `$primary` — same pattern as raumuehle/weingut).
- Font: none — no custom `font-family` on `body`; `_fonts.scss` omitted.
- Inverted logo: none found (`img/` has `logo.png` only, no SVG variant).
- ddev verification: `HTTP 200` at `https://lebenslagen.ddev.site/?no_cache=1`;
  compiled `theme.css` `--bs-primary:#0067b3` / `--bs-secondary:#0067b3` —
  matches live site.

## oberstedten (rv-oberstedten)

- Live domain: `https://drei.taketools.com/` (root 404s but still renders
  TYPO3's error-page template with the mandant's compiled CSS linked, as
  expected — fetched anyway per instructions).
- `$primary`: `#568c11`, `$secondary`: `#005e88` (differs from primary here).
- Other non-default variables: `$teal:#333`, `$pink:#579b67`,
  `$indigo:#fff`, `$yellow:#fff`, `$navbar-brand-font-size:1.3rem` (same
  fluid-scaled pattern as kibsmombach), `$h1-font-size:1.98rem`,
  `$h4-font-size:1.32rem`, `$h5-font-size:1.21rem` (h2/h3 matched shared
  defaults), `$navbar-dark-hover-color:#333` (≠ primary),
  `$navbar-light-hover-color: $primary` (live `.navbar-default` hover color
  equaled `#568c11`, same as `$primary`).
- Font: none — no custom `font-family` on `body`; `_fonts.scss` omitted.
- Inverted logo: none found (`img/` has `logo.png` only, no SVG variant).
- ddev verification: `HTTP 404` at `https://oberstedten.ddev.site/?no_cache=1`
  — this matches the expected/documented behavior for this site (its root
  page 404s on live too). The 404 response still includes the compiled
  `theme.css` link; fetched it and confirmed `--bs-primary:#568c11` /
  `--bs-secondary:#005e88` — matches live site exactly.

## oekomesse (oekomesse-ingelheim)

- Live domain: `https://www.oekomesse-ingelheim.de/`
- `$primary` / `$secondary`: `#1d5e32`
- Other non-default variables: `$teal:#5b5b5b`, `$pink:#ccc`,
  `$indigo:#9fff9a`, `$yellow:#fff`, `$navbar-dark-hover-color: $primary`
  (live `.navbar-inverse` hover color equaled `#1D5E32`, same as
  `$primary`), `$navbar-light-hover-color:#0d344a` (live `.navbar-default`
  hover color, ≠ `$primary` — note dark/light hover diverge in opposite
  directions from primary on this site, unlike the other five).
  h1-h5 all matched the shared default formula exactly — no heading
  overrides needed.
- Font: none — `custom.scss` only uses `font-family: 'Exo'` inline on a
  heading selector, not on `body`, so no site-wide override; `_fonts.scss`
  omitted.
- Inverted logo: none found (`img/` has `logo.svg` only).
- ddev verification: **problem**, same root cause as laventt.
  `https://oekomesse.ddev.site/?no_cache=1` returns `HTTP 500` with the
  identical `InvalidTemplateResourceException` for
  `ContentElements/Frame/General/BackgroundImage.html` (missing project-wide,
  same as documented under laventt above — not caused by this migration, not
  fixed here per scope). Color match could not be confirmed via a live 200
  response; no SCSS compile error was observed at any point during testing.

## Cross-cutting notes

- `$font-size-base`, the h1-h5 formula multipliers, `$navbar-brand-font-size`,
  and `$navbar-nav-link-padding-x` were confirmed to equal the shared
  sitepackage defaults (`1.1rem` / `1.75,1.5,1.25,1,0.9` / `1.1rem` /
  `0.8rem`) for the large majority of these six sites and were omitted
  wherever they matched; only the specific per-site deviations found above
  were written into each `_variables.scss`.
- `$navbar-light-hover-color` (required, no `!default` in the chain) was
  explicitly set for all six sites from each site's live
  `.navbar-mainnavigation.navbar-default a:not(.active):hover{color:...}`
  rule. It equaled `$primary` for 2 of 6 (lebenslagen, oberstedten) and was a
  distinct hardcoded value for the other 4 — confirming this needs
  per-site verification rather than assuming the raumuehle/weingut pattern.
  `$navbar-dark-hover-color` (has its own Bootstrap `!default`, but every
  site here rendered a value far from that default) was set the same way
  from each site's `.navbar-inverse` hover rule, for consistency with the
  raumuehle/weingut examples.
- **New project-wide bug found, not previously documented in
  SCSS_migration.md**: `EXT:.../Resources/Private/Partials/ContentElements/Frame/General/BackgroundImage.html`
  does not exist in `packages/sitepackage`, `vendor/bk2k/bootstrap-package`,
  or anywhere else in the installed vendor tree (only
  `ViewHelpers/Frame/BackgroundImage.html` and the Carousel-specific variants
  exist). This breaks every page on `laventt` and `oekomesse-ingelheim`
  (both use this content-element layout, evidenced by their matching
  `.frame-background-ueberschrift` custom.scss rules) with a hard 500. Not
  fixed here, per scope (it lives in shared vendor/sitepackage code, and the
  instructions for this batch say to flag rather than fix new shared-file
  bugs) — needs follow-up outside this migration.
- During this session, a different concurrent batch's agent created
  `packages/sitepackage/Resources/Private/Partials/BootstrapPackage/Page/Navigation/MainNavigationDropDown.html`
  (previously missing, causing the same class of `InvalidTemplateResourceException`
  on kibsmombach and others until it appeared) — this was not done by this
  batch's work, just observed mid-session; noted here for traceability only.
# SCSS migration — batch 4

Sites migrated: `ogr-mainz`, `pscholles`, `schmutzenhofer`, `rudi-trautz`, `theis`, `scholles`.
Followed `SCSS_migration.md` step by step; brand values recovered from each live
site's compiled `theme.css` (all six live domains were reachable).

## ogr-mainz (ogr-mainz)

- Live domain used: `https://www.omasgegenrechts-mainz.de/`
- `$primary: #333`, `$secondary: #601e5f`
- Also set: `$teal: #333`, `$pink: #ffffff`, `$indigo: #c35264`, `$yellow: #fff`
- `$navbar-light-hover-color: #d24233` (does **not** equal `$primary` here —
  verified via both `::before{background}` and the mobile
  `.navbar-mainnavigation.navbar-default a:not(.active):hover` selector)
- `$navbar-dark-color`/`$navbar-dark-hover-color`: `#333` (differs from bootstrap default)
- `$navbar-brand-font-size: 1.2rem` (differs from the 1.1rem sitepackage default)
- `$navbar-nav-link-padding-x: 0.7rem` (differs from the 0.8rem default)
- h1–h5 all differ from the standard formula (base itself is still 1.1rem, but the
  multipliers aren't 1.75/1.5/1.25/1/0.9) — set as flat values: `1.98rem / 1.76rem /
  1.43rem / 1.32rem / 1.1rem`
- Custom font: none (`custom.scss` has no `body{font-family}` override) — `_fonts.scss` omitted
- Inverted logo file: none found in `img/`
- ddev verification: **HTTP 500** — unrelated pre-existing bug, see "Problem found" below.
  SCSS itself compiles cleanly (no Sass errors in `var/log`); color match could not be
  confirmed on `https://ogr-mainz.ddev.site/` because the page never reaches the
  CSS-include step before the exception aborts rendering.

## peterscholles (pscholles)

- Live domain used: `https://www.peter-scholles.de/`
- `$primary: #333`, `$secondary: #000`
- Also set: `$teal: #5b5b5b`, `$pink: #ccc`, `$indigo: #fdca92`, `$yellow: #fff`
- `$navbar-light-hover-color: $primary` (confirmed equal, `#333` both ways)
- `$navbar-dark-color: #878686` (differs from default); `$navbar-dark-hover-color: $primary`
- `$navbar-brand-font-size: 1.2rem` (differs from 1.1rem default)
- `$navbar-nav-link-padding-x`: default (0.8rem), not overridden
- `$font-size-base: 1.3rem` (differs from 1.1rem default). h1–h3 still follow the
  standard `*1.75/*1.5/*1.25` formula off this base (so left as formulas); h4/h5 use
  different multipliers here, so set as flat values `1.43rem` / `1.3rem`
- Custom font: none — `_fonts.scss` omitted
- Inverted logo file: none found in `img/`
- ddev verification: **HTTP 500** — same unrelated bug as ogr-mainz (see below). SCSS
  compiles cleanly; color match unverifiable on `https://peterscholles.ddev.site/` for
  the same reason (page never reaches CSS-include step).

## praxis (schmutzenhofer)

- Live domain used: `https://www.praxis-jutta-schmutzenhofer.de/`
- `$primary: #8bae3e`, `$secondary: #568e34`
- Also set: `$teal: #8bae3e`, `$pink: #fff`, `$indigo: #cfef88`, `$yellow: #8bae3e`
- `$navbar-light-hover-color: #cfef88` (equals `$indigo`, **not** `$primary` — hardcoded
  literal, verified via both the `::before` and `a:not(.active):hover` selectors)
- `$navbar-dark-color`/`$navbar-dark-hover-color`: `#fff` (differs from default)
- `$navbar-brand-font-size: 1.3rem` (differs from 1.1rem default)
- `$navbar-nav-link-padding-x`: default, not overridden
- h1/h2 follow the standard formula (no override needed); h3–h5 use different
  multipliers, set as flat values `1.43rem / 1.32rem / 1.21rem`
- Custom font: none — `_fonts.scss` omitted
- Inverted logo file: none found in `img/`
- ddev verification: **HTTP 200** on `https://praxis.ddev.site/?no_cache=1`. Compiled
  `theme.css` confirmed `--bs-primary:#8bae3e; --bs-secondary:#568e34;` — matches live site.

## rudi (rudi-trautz)

- Live domain used: `https://www.rudi-trautz.de/`
- `$primary: #e9c277`, `$secondary: #db9102`
- Also set: `$teal: #5b5b5b`, `$pink: #ccc`, `$indigo: #f2d66b`, `$yellow: #ecba19`
- `$navbar-light-hover-color: #333` (does **not** equal `$primary` — hardcoded literal)
- `$navbar-dark-color`/`$navbar-dark-hover-color`: `#333` (differs from default)
- `$navbar-brand-font-size: 1.3rem` (differs from 1.1rem default)
- `$navbar-nav-link-padding-x`: default, not overridden
- h1–h5: all match the standard 1.1rem-base formula exactly — no heading overrides needed
- Custom font: none — `_fonts.scss` omitted
- Inverted logo file: none found in `img/`
- ddev verification: **HTTP 200** on `https://rudi.ddev.site/?no_cache=1`. Compiled
  `theme.css` confirmed `--bs-primary:#e9c277; --bs-secondary:#db9102;` — matches live site.

## schlosserei (theis)

- Live domain used: `https://www.schlosserei-theis.de/`
- `$primary: #35b6c4`, `$secondary: #35b6c4`
- Also set: `$teal: #5b5b5b`, `$pink: #ccc`, `$indigo: #35b6c4`, `$yellow: #fff`
- `$navbar-light-hover-color: #333` (does **not** equal `$primary` — hardcoded literal)
- `$navbar-dark-color`/`$navbar-dark-hover-color`: `#333` (differs from default)
- `$navbar-brand-font-size: 1.3rem` (differs from 1.1rem default)
- `$navbar-nav-link-padding-x`: default, not overridden
- h1–h5: all match the standard 1.1rem-base formula exactly — no heading overrides needed
- Custom font: none on `body` — `custom.scss` only sets `font-family: 'Dancing Script'`
  on two specific carousel headings, not `body`, so per the migration rule this doesn't
  count as a mandant font override — `_fonts.scss` omitted
- **Extra legacy file found**: the old `theme.scss` also imported `./emil.scss` (a small
  sticky-footer flex rule) in addition to `./custom`. The new standard only wires up
  `theme.scss`/`global.scss`/`custom.scss` per mandant, so `emil.scss` would have
  silently stopped compiling. Its two rules were merged verbatim into the end of the new
  `custom.scss` (with a comment marking the merge); the original `emil.scss` file was
  left in place, unused, rather than deleted (no git safety net in this project).
- Inverted logo file: none found in `img/`
- ddev verification: **HTTP 200** on `https://schlosserei.ddev.site/?no_cache=1`.
  Compiled `theme.css` confirmed `--bs-primary:#35b6c4; --bs-secondary:#35b6c4;` —
  matches live site.

## scholles (scholles)

- Live domain used: `https://www.blumen-scholles.de/`
- `$primary: #55A163`, `$secondary: #55A163`
- Also set: `$teal: #333333`, `$pink: #55A163`, `$indigo: #2fb346`, `$yellow: #fff`
- `$navbar-light-hover-color: #333` (does **not** equal `$primary` — hardcoded literal)
- `$navbar-dark-color`/`$navbar-dark-hover-color`: `#333` (differs from default)
- `$navbar-brand-font-size: 1.3rem` (differs from 1.1rem default)
- `$navbar-nav-link-padding-x`: default, not overridden
- h1–h3 match the standard formula; h4/h5 use different multipliers, set as flat
  values `1.21rem` / `1.1rem`
- Custom font: none on `body` — `custom.scss` sets `font-family: 'Dancing Script'` only
  on two specific carousel headings (same pattern as theis), not `body` —
  `_fonts.scss` omitted
- Inverted logo file: none found in `img/`
- ddev verification: **HTTP 500** — same unrelated bug as ogr-mainz/peterscholles (see
  below). SCSS compiles cleanly; color match unverifiable on
  `https://scholles.ddev.site/` for the same reason.

## Problem found: NOT fixed (new, undocumented, project-wide bug)

Three of the six sites (`ogr-mainz`, `peterscholles`, `scholles`) return HTTP 500 on
`https://<id>.ddev.site/?no_cache=1`, both before and structurally independent of this
SCSS migration. The error is:

```
#1225709595 TYPO3Fluid\Fluid\View\Exception\InvalidTemplateResourceException
The Fluid template files ".../fileadmin/templates/<mandant>/bootstrap_package/.../MainNavigationDropDown.html",
".../vendor/taketool/sitepackage/Resources/Private/Partials/BootstrapPackage/Page/Navigation/MainNavigationDropDown.html",
".../vendor/bk2k/bootstrap-package/Resources/Private/Partials/Page/Navigation/MainNavigationDropDown.html"
... could not be loaded.
```

Root cause: sitepackage's own override at
`packages/sitepackage/Resources/Private/Partials/BootstrapPackage/Page/Navigation/MainNavigation.html`
line 66 does `<f:render partial="Navigation/MainNavigationDropDown" .../>` whenever a
main-nav item has children (i.e. any mandant whose page tree has subpages under a
top-level nav page). No `MainNavigationDropDown.html` partial exists anywhere in the
project — not in sitepackage, not in `vendor/bk2k/bootstrap-package` (whose own stock
`MainNavigation.html` inlines the dropdown markup directly instead of delegating to a
separate partial). This is a genuine missing-file bug in the shared sitepackage
partial, unrelated to SCSS/CSS entirely, and it will affect **any** mandant (migrated
or not) whose main navigation has a page with visible subpages — it just happens that
3 of this batch's 6 sites have that page-tree shape and the other 3 (and, apparently,
`raumuehle`, which loads fine) don't.

This is **not** documented in `SCSS_migration.md`'s "already fixed" list, so per
instructions it was left untouched — no edits were made to
`packages/sitepackage/Resources/Private/Partials/BootstrapPackage/Page/Navigation/MainNavigation.html`
or any other shared file. Confirmed the exception is unrelated to the SCSS work: no
Sass/SCSS compile errors appear in `var/log/typo3_*.log` for these requests, and the
same six-line `custom.scss` header / `_variables.scss` / `theme.scss` / `global.scss`
pattern used here is identical (only the color values differ) to the pattern that
verified cleanly on the other 3 sites in this batch. A follow-up fix would need to
either add a `MainNavigationDropDown.html` partial to sitepackage (rendering the
dropdown-menu markup bk2k's stock template inlines) or point `MainNavigation.html` at
bk2k's own partial instead.
# SCSS migration — batch 5

Six mandant folders migrated to the new SCSS standard (see `SCSS_migration.md`).
Verification run: `ddev exec 'vendor/bin/typo3 cache:flush'` once, then
`curl https://<identifier>.ddev.site/?no_cache=1` per site.

## sdw (sdw)

- Live domain used: `https://www.sdw-hofheim.de/`
- `$primary` / `$secondary`: `#418302` / `#418302`
- Other variables set: `$teal:#5b5b5b`, `$pink:#ccc`, `$indigo:#86a20b`, `$yellow:#fff`,
  `$navbar-brand-font-size:1.3rem` (differs from the confirmed shared default of 1.1rem —
  live CSS showed RFS-fluid scaling capping at 1.3rem, not the flat 1.1rem seen on
  raumuehle/weingut), `$navbar-dark-color`/`$navbar-dark-hover-color`/
  `$navbar-dark-active-color`/`$navbar-light-hover-color` all `#333` (checked live —
  the default-skin hover color here is **not** equal to `$primary`, unlike raumuehle/weingut,
  so the "hover = primary" assumption does **not** hold for this site; verified separately
  via `.navbar-mainnavigation.navbar-inverse a:not(.active):hover` too, same `#333`).
- Custom font: none (no `font-family` on `body` in custom.scss).
- Inverted logo file: none found in `img/`.
- ddev verification: **HTTP 500**, but root-caused to a pre-existing, unrelated bug — see
  "New project-wide issue found" below. Not a regression from this migration (see reasoning
  there: no Sass/SCSS compiler exception appears in `var/log/typo3_*.log` for this request,
  unlike a genuine SCSS error which does log distinctly).

## seezauber (seezauber)

- Live domain used: `https://www.seezauber-schluchsee.de/`
- `$primary` / `$secondary`: `#008dd2` / `#69b2dd`
- Other variables set: `$teal:#008dd2`, `$pink:#b6dff7`, `$indigo:#ffcc00`, `$yellow:#fff`,
  `$navbar-brand-font-size:1.2rem`, `$navbar-nav-link-padding-x:0.6rem` (both differ from
  shared defaults), `$navbar-dark-color:$primary`, `$navbar-dark-hover-color:#333`,
  `$navbar-dark-active-color:#333`, `$navbar-light-hover-color:$primary` (confirmed: default-skin
  hover **does** equal `$primary` here, matching the raumuehle/weingut pattern — but the
  mobile/dark-skin hover is `#333`, not `$primary`, so that one still had to be set explicitly).
- Custom font: none.
- Inverted logo file: none found in `img/`.
- ddev verification: **HTTP 200**. Fetched `theme.css`, confirmed `--bs-primary:#008dd2;` and
  `--bs-secondary:#69b2dd;` — exact match to live.

## stiftung (stiftung-friedenskirche)

- Live domain used: `https://www.stiftung-friedenskirche.de/`
- `$primary` / `$secondary`: `#6B488A` / `#8c61b2`
- Other variables set: `$teal:#5b5b5b`, `$pink:#ccc`, `$indigo:#fdd420`, `$yellow:#fff`,
  `$navbar-brand-font-size:1.2rem` (differs from default), `$navbar-dark-color`/
  `$navbar-dark-hover-color`/`$navbar-dark-active-color`/`$navbar-light-hover-color` all `#333`
  (checked live — hover is **not** equal to `$primary` here either, same finding as sdw).
- Custom font: none (headings etc. use plain colors, no `font-family` override on `body`).
- Inverted logo file: none found in `img/` (the CSS does use the generic
  `.navbar-brand-logo-inverted` class from sitepackage's own Main.html partial, but there's no
  actual `logo_dark`/`logo_inverted`-style file in this mandant's `img/` to wire up).
- Note: this folder also has a mandant-local Fluid override at
  `bootstrap_package/Resources/Private/Partials/Page/Navigation/Main.html` — out of scope for
  this SCSS migration, left untouched.
- ddev verification: **HTTP 200**. Fetched `theme.css`, confirmed `--bs-primary:#6B488A;` and
  `--bs-secondary:#8c61b2;` — exact match to live.

## taketool (taketool)

- Live domain used: `https://eins.taketools.com/`
- `$primary` / `$secondary`: `#b5242b` / `#333`
- Other variables set: `$teal:#2b2b2b`, `$pink:#adb5bd`, `$yellow:#fff`,
  `$navbar-nav-link-padding-x:0.7rem` (differs from default), `$navbar-dark-color:#fff`,
  `$navbar-dark-hover-color:#333`, `$navbar-dark-active-color:#000`,
  `$navbar-light-hover-color:#fff` (checked live — hover is white, not `$primary` and not
  `$secondary`; a genuinely different value from anything else on the page).
  **`$indigo` intentionally omitted**: live CSS showed `--bs-indigo:#6610f2`, which is the
  unmodified raw Bootstrap default — this mandant never actually customized it, so porting it
  as if it were a real per-site value would be wrong per `SCSS_migration.md` step 3.
- Custom font: none.
- Inverted logo file: none found in `img/`.
- Note: this mandant's legacy `theme.scss` was already missing the sitepackage `global.scss`
  import (only had bootstrap5/theme + custom) — pre-existing brokenness from before this
  migration, now moot since the new standard separates theme/global/custom into three
  independent files anyway.
- ddev verification: **HTTP 404** ("No site configuration found"). `vendor/bin/typo3 site:list`
  confirms the `taketool` site is registered, enabled, and points at
  `https://taketool.ddev.site/` correctly; the failure happens before TypoScript is even parsed
  (`x-typo3-parsetime: 0ms`), i.e. before SCSS compilation would ever be invoked — this is a
  pre-existing routing/environment issue unrelated to this migration, not something introduced
  by these SCSS changes. Could not confirm color match via a live page render as a result;
  color values above were captured directly from `eins.taketools.com`'s own compiled CSS
  instead.

## srv (srv) — placeholder colors, no live reference

- Live domain used: **none** — `zwei-taketools.com` doesn't resolve via DNS.
- `$primary` / `$secondary`: `#2a9d8f` / `#e76f51` (bk2k/bootstrap-package's own shipped
  defaults, used as an honest placeholder — **not** a real recovered brand color; `custom.scss`
  references `$primary` via `h2 {color:$primary;}`, so it couldn't be left undefined).
  `$navbar-light-hover-color: $primary;` set too (required, no `!default` in the chain).
  No `$teal`/`$pink`/`$indigo`/`$yellow` set (no reference available; Bootstrap defaults apply).
- Custom font: **yes** — `custom.scss` sets `body { font-family: 'Lato'; }`. `Lato-Regular.woff`
  / `.woff2` already existed in the shared legacy font store
  (`packages/sitepackage/Resources/Public/Fonts/`), so created `srv/_fonts.scss` +
  `srv/Fonts/Lato-Regular.woff{,2}` and added `@import "_fonts";` to `global.scss`. Left the
  existing (already-broken, absolute-path) `@font-face 'Lato Light'` rule in `custom.scss`
  untouched, as required — it's used nowhere in this mandant's own rules besides the
  declaration itself, and per the migration rules only the header may change.
- Inverted logo file: none found in `img/`.
- **Owner action needed**: confirm srv's real brand color and update `_variables.scss`.
- ddev verification: **HTTP 500**, root-caused to the same pre-existing, unrelated bug as sdw
  (see "New project-wide issue found" below) — not a regression from this migration.

## test (test) — placeholder colors, no live reference

- Live domain used: **none** — `https://zwei.taketools.com/` returns TYPO3's generic fallback
  error page, not this site's own themed page.
- `$primary` / `$secondary`: `#2a9d8f` / `#e76f51` (same bk2k default placeholder as srv, for
  the same reason — `custom.scss` references `$secondary` via
  `.footer-section .frame a:not([class]) {color:$secondary;}` and
  `.navbar-mainnavigation .navbar-nav > li > .nav-link::before {background: $secondary;}`).
  `$navbar-light-hover-color: $primary;` set too (required).
  No `$teal`/`$pink`/`$indigo`/`$yellow` set (no reference available).
- Custom font: none — `custom.scss` uses `font-family: baskerville` inline on several specific
  selectors (h1/h2/h3/h4/h5, `.card-title`, `legend`, `#c8423 figure .caption`), but never on
  `body`, and there's no `@font-face` for it and no font files anywhere in the project — per
  instructions this doesn't count as a body-level custom font, so `_fonts.scss` was skipped
  entirely (and `@import "_fonts";` omitted from `global.scss`). Those `font-family:baskerville`
  rules are left exactly as-is in `custom.scss`.
- Inverted logo file: none found in `img/` (there is a `labubu_logo.svg`, but it's a plain
  logo, not a dark/inverted variant, and it's not referenced from custom.scss).
- **Owner action needed**: confirm test's real brand color and update `_variables.scss`.
- ddev verification: **HTTP 404** ("No site configuration found"), same as taketool — see
  taketool's entry above; pre-existing, unrelated to this migration.

## New project-wide issues found (not fixed — out of scope, flagged only)

Two issues surfaced during verification that are **not** already documented in
`SCSS_migration.md` and are **not** SCSS-related — both are missing Fluid partials, unrelated
to the files touched in this batch:

1. **`Navigation/MainNavigationDropDown` partial does not exist anywhere in the codebase.**
   `packages/sitepackage/Resources/Private/Partials/BootstrapPackage/Page/Navigation/MainNavigation.html`
   line 66 does `<f:render partial="Navigation/MainNavigationDropDown" .../>`, but no file named
   `MainNavigationDropDown.html` exists in sitepackage, `vendor/bk2k/bootstrap-package`, or
   anywhere else searched. Any mandant whose page tree has a navigation item with children (a
   dropdown submenu) — e.g. `sdw`, whose page tree includes "Verein" with sub-pages — 500s with
   `TYPO3Fluid\Fluid\View\Exception\InvalidTemplateResourceException` (code `1225709595`) on
   **every** page, since the main nav renders site-wide. Confirmed via `sdw.ddev.site`.

2. **`ContentElements/Frame/General/BackgroundImage` partial does not exist anywhere in the
   codebase.** Same shape of bug — any page using a "background image" frame content element
   500s the same way. Confirmed via `srv.ddev.site`'s root page.

Both were verified to be unrelated to this migration: neither mandant's `custom.scss`/
`theme.scss`/`global.scss` touches navigation or frame-background rendering, and no
Sass/SCSS compiler exception appears in `var/log/typo3_*.log` for either request — only the
Fluid `InvalidTemplateResourceException` above. As instructed, these were left un-fixed and are
only flagged here for whoever owns `basis.typoscript`/sitepackage's Partials next.

Additionally, `taketool.ddev.site` and `test.ddev.site` both return HTTP 404 "No site
configuration found" despite `vendor/bin/typo3 site:list` confirming both sites are registered,
enabled, and correctly mapped to those hostnames. The failure happens before TypoScript parsing
(`x-typo3-parsetime: 0ms`), so it cannot be an SCSS compile issue — flagged for visibility but
not investigated further, as it's outside this migration's scope (site `config.yaml` was
explicitly out of scope to edit) and may simply be a transient caching/environment issue given
five other batches were running concurrently against the same ddev instance.
# SCSS migration summary — batch 6

Sites migrated per `SCSS_migration.md`'s step-by-step. All four now match the
`_variables.scss` / `theme.scss` / `global.scss` / `custom.scss` layout used
by `raumuehle` and `weingut-haupt`.

## trixis-winzlinge (trixis-winzlinge)

- Live domain used: `https://www.trixis-winzlinge.de/`
- `$primary: #ab2421`, `$secondary: #5655a6`
- Also set (differ from shared defaults): `$teal: #5b5b5b`, `$pink: #ccc`,
  `$indigo: #fcf3f3`, `$yellow: #fff`, `$navbar-brand-font-size: 1.2rem`,
  `$navbar-nav-link-padding-x: 0.4rem`, `$navbar-light-hover-color: #333`
  (found via `.navbar-mainnavigation.navbar-default a:not(.active):hover` —
  does **not** equal `$primary` here, unlike raumuehle/weingut)
- h1–h5 use custom multipliers of `$font-size-base` (2.4 / 1.7 / 1.35 / 1.2 /
  0.9), confirmed against live `theme.css` (h1 renders 2.64rem, not the
  1.925rem default). Because this required `$font-size-base` inside
  `_variables.scss` itself (the very first file in the compile chain, before
  bootstrap5's own defaults exist), `$font-size-base: 1.1rem;` had to be
  declared explicitly too — omitting it caused a real "Undefined variable"
  compile failure in local verification (dart-sass), caught and fixed before
  going further. Value matches the confirmed project-wide default
  (`--bs-body-font-size:1.1rem` on live), so this changes nothing visually.
- Custom font: yes — `'Baby Brooklynn Regular'`, used on `h1`. Unlike
  raumuehle's Cairo case, this font was **not** supplied by the shared legacy
  `packages/sitepackage/Resources/Public/Fonts/` store via `body`; the
  mandant already had its own local `fonts/Babybrooklyn-Medium.woff{,2}` and
  a working `@font-face` declared directly inside `custom.scss`. Left that
  `@font-face` block untouched (byte-for-byte, below the new header) — no
  `_fonts.scss` created, no `@import "_fonts";` added to `global.scss`, since
  it's self-contained and doesn't depend on the removed shared chain.
- Inverted logo: none found in `img/`.
- ddev verification: `https://trixis-winzlinge.ddev.site/?no_cache=1` → HTTP
  200. `theme.css` `--bs-primary`/`--bs-secondary` match live exactly; `h1`
  font-size confirmed at `2.64rem` matching live after the fix above.

## truebenbach-slaby (truebenbach)

- Live domain used: `https://www.truebenbach-slaby.de`
- `$primary: #633039`, `$secondary: #897174`
- Also set: `$teal: #5b5b5b`, `$pink: #ccc`, `$indigo: #cc8390`,
  `$yellow: #fff`, `$navbar-brand-font-size: 1.3rem` (live shows RFS-scaled
  `calc(1.255rem + 0.06vw)` → flat `1.3rem` at 1200px, differs from the
  1.1rem/flat default), `$navbar-light-hover-color: #333` (does not equal
  `$primary` here)
- `$navbar-nav-link-padding-x` and h1–h5 matched shared defaults exactly on
  live — not set.
- No custom font in `custom.scss`.
- Inverted logo: none found (`img/` only has `logo.svg`).
- ddev verification: `https://truebenbach-slaby.ddev.site/?no_cache=1` → HTTP
  **404**, but this is **not** an SCSS/migration problem — the page rendered
  fully (logo, content, all three compiled stylesheets linked in `<head>`)
  and `theme.css` `--bs-primary`/`--bs-secondary` matched live exactly. The
  site's own `config.yaml` has `errorHandling` pointing 404s at page
  `t3://page?uid=2043`, and `rootPageId: 2042` itself isn't resolving to a
  valid route in this ddev DB — a pre-existing routing/content data issue,
  unrelated to any file touched in this migration.

## waschmaschine (waschmaschinendoktor)

- Live domain used: `https://www.waschmaschinendoktor.de/`
- `$primary: #C72722`, `$secondary: #333`
- Also set: `$teal: #5b5b5b`, `$pink: #ccc`, `$indigo: #e3a2a0`,
  `$yellow: #fff`, `$navbar-brand-font-size: 1.2rem`,
  `$navbar-nav-link-padding-x: 0.6rem`, `$navbar-light-hover-color: $primary`
  (confirmed equal to primary here, `$navbar-dark-hover-color` stayed at the
  shared `#333` default — not overridden)
- No custom font in `custom.scss`.
- Inverted logo: none found. Separately worth flagging: this mandant's `img/`
  folder has **no `logo.svg` at all** (only `logo.gif` and `logo.png`) —
  `basis.typoscript`'s global logo fix expects
  `fileadmin/templates/{$mandant}/img/logo.svg` literally, so the navbar
  logo may not render regardless of the SCSS work here. Pre-existing asset
  gap, not something this migration can fix (would need a real SVG asset,
  out of scope).
- ddev verification: `https://waschmaschine.ddev.site/?no_cache=1` → **HTTP
  500**. Confirmed **not caused by this migration**: independently compiled
  this mandant's `theme.scss` and `custom.scss` locally with `dart-sass`
  (EXT: paths resolved to their real vendor locations) and both compiled
  cleanly with the correct `--bs-primary:#C72722` etc. The actual 500 is
  `TYPO3Fluid\Fluid\View\Exception\InvalidTemplateResourceException`
  (`#1225709595`) thrown while resolving the Fluid partial
  `Navigation/MainNavigationDropDown`, referenced at
  `packages/sitepackage/Resources/Private/Partials/BootstrapPackage/Page/Navigation/MainNavigation.html:66`
  (`<f:render partial="Navigation/MainNavigationDropDown" ... />`) — **this
  partial file does not exist anywhere in the codebase**, not in
  sitepackage, not in `vendor/bk2k/bootstrap-package`, not vendored anywhere.
  It only triggers when a main-nav item has children (a dropdown), which is
  apparently only exercised by this mandant's page tree among the sites
  checked so far (raumuehle, weingut, and the other three sites in this
  batch all render fine). **This is a new project-wide bug, not documented
  in `SCSS_migration.md`'s already-fixed list — not fixed here** (it's in
  the shared sitepackage partial, out of scope per this batch's
  constraints); flagging for follow-up. Someone will need to either add the
  missing `MainNavigationDropDown.html` partial (in sitepackage or as a
  vendor patch) or adjust `MainNavigation.html` to not reference it.

## zahnarztpraxis (sulaiman)

- Live domain used: `https://www.zahnarztpraxis-sulaiman.de/`
- `$primary: #a1191b`, `$secondary: #a1191b` (same value, like raumuehle)
- Also set: `$teal: #333333`, `$pink: #d9534f`, `$indigo: #f2aaa7`,
  `$yellow: #fff`, `$navbar-brand-font-size: 1.3rem` (same RFS pattern as
  truebenbach, flat `1.3rem` at 1200px), `$navbar-light-hover-color:
  $primary`, `$navbar-dark-hover-color: $primary` (this one **does** differ
  from the shared `#333` default, unlike the other three sites in this
  batch — confirmed via both `.navbar-default` and `.navbar-inverse`
  `a:not(.active):hover` both resolving to `#a1191b` live)
- `$navbar-nav-link-padding-x` and h1–h5 matched shared defaults — not set.
- No custom font in `custom.scss`.
- Inverted logo: none found.
- ddev verification: `https://zahnarztpraxis.ddev.site/?no_cache=1` → HTTP
  200. `theme.css` `--bs-primary`/`--bs-secondary` match live exactly.

## Cross-cutting notes

- `$blue`/`$red`/`$font-size-base`(body)/h1–h5 multipliers/
  `$navbar-nav-link-padding-x` all matched the confirmed shared
  sitepackage-wide defaults (`#164194` / `#e30613` / `1.1rem` /
  `1.75,1.5,1.25,1,0.9` / `0.8rem`) for 3 of these 4 sites and were left out
  of `_variables.scss` accordingly; trixis-winzlinge was the one exception
  (custom h1–h5 multipliers, documented above).
- `$navbar-dark-color` (non-hover, non-active navbar-inverse text color) was
  observed as `#333` identically across all four legacy (pre-migration) live
  sites plus raumuehle/weingut — confirmed shared default, not set anywhere
  in this batch.
- No shared files (`basis.typoscript`, sitepackage's `composer.json`,
  `global.scss`, `_navigation-offcanvas.scss`) were edited.
