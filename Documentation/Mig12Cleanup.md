# TYPO3 v11 → v12 database cleanup: `mig12`

Console command: `vendor/bin/typo3 mig12`
Source: `packages/sitepackage/Classes/Command/Mig12Command.php`

## Why

Updating the code/extensions for a TYPO3 v11→v12 upgrade doesn't fix everything —
several categories of v11-era data survive the upgrade and then silently break
or block things at the database level:

- Content elements still stored the old way (`CType="list"` + `list_type`) for
  extensions that now register a native `CType` in v12 — they render as
  "No Content Object definition found" until migrated.
- `sys_category_record_mm` can carry duplicate rows from years of use, which
  blocks TYPO3 v12's new composite primary key — `database:updateschema`
  fails outright with a `Duplicate entry ... for key 'PRIMARY'` error until
  they're removed.
- `in2code/powermail`'s `marketing_*` tracking columns get a `NOT NULL`
  default in v12's schema; existing `NULL` rows fail that same
  `database:updateschema` run with `Data truncated for column ...`.
- Every mandant's `sys_template.constants` still carries its pre-migration
  `plugin.bootstrap_package { settings.scss { ... } }` color/typography block
  from before the site's SCSS was moved to the current `_variables.scss`
  standard (see `SCSS_migration.md`) — harmless once migrated (compiled
  output no longer depends on it), but a live second source of truth that can
  silently diverge from `_variables.scss` if left in place.

None of these four fix themselves just by deploying new code. `mig12` bundles
all four into one auditable, dry-runnable command instead of four separate
one-off SQL scripts, so the same cleanup can be run consistently across every
environment a v11→v12 upgrade touches (local ddev, staging, the remote
production host).

## Steps

| Flag | What it does | In `--all`? | Risk |
|---|---|---|---|
| `--content-types` | Migrates `CType="list"` + `list_type` in (`powermail_pi1`, `news_pi1`, `media2click_list`) to the native `CType` | yes | Low — pure `tt_content` data fix, idempotent, includes hidden/deleted records |
| `--category-dedup` | Removes duplicate `sys_category_record_mm` rows blocking the composite primary key | yes | Low–medium — deletes rows, but only provable duplicates; aborts without deleting anything if it ever finds a row it can't safely disambiguate |
| `--powermail-marketing` | Replaces `NULL` with `''` in 5 `tx_powermail_domain_model_mail` marketing columns | yes | Very low — only fills blanks, never overwrites real data |
| `--legacy-scss-constants` | Removes a mandant's legacy `plugin.bootstrap_package.settings.scss` DB constants, only where fully redundant | **no**, always explicit | Medium — edits a shared `sys_template.constants` TEXT field; only proceeds when every value it would remove is provably dead |

`--all` runs the first three. `--legacy-scss-constants` is deliberately never
implied by `--all` or by bare `--dry-run` — unlike the other three, whether
it's safe to run depends on the *current file content* of a mandant's
`_variables.scss`, not just database state, so it's worth reviewing its own
dry-run output on its own before running for real.

### `--content-types`

`georgringer/news`, `in2code/powermail` (`Pi1`), and `amazing/media2click`
each register their v12 content element via
`ExtensionUtility::configurePlugin(..., PLUGIN_TYPE_CONTENT_ELEMENT)`, which
only creates the native `tt_content.<signature>` TypoScript object — the old
`tt_content.list.20.<signature>` path that `CType="list"` records still
resolve through is never populated. This step sets
`CType = list_type` and clears `list_type` on every matching row (including
hidden/soft-deleted ones, so restoring one from the recycler doesn't
resurrect a broken plugin), then flushes the `pages` cache group.

Example dry-run output:

```
Migrate CType="list" plugin records to their native CType
-----------------------------------------------------------
 [powermail_pi1] Found 357 record(s) to migrate: 295 live, 62 deleted. Already using CType="powermail_pi1": 0 record(s).
 [news_pi1] Found 184 record(s) to migrate: 170 live, 14 deleted. Already using CType="news_pi1": 0 record(s).
 [media2click_list] Found 3 record(s) to migrate: 3 live, 0 deleted. Already using CType="media2click_list": 0 record(s).
```

### `--category-dedup`

Deletes duplicate `(uid_local, uid_foreign, tablenames, fieldname)` rows via
a self-join `DELETE` (not `TRUNCATE`+rebuild — `TRUNCATE` is DDL and not
rollback-safe inside a transaction), keeping the row with the
group-minimum `(sorting_foreign, sorting)`. The join predicate is a strict
inequality, so the minimum row in each group never matches its own delete
condition and is always kept exactly once — no window functions needed.
Before deleting anything, it checks for rows that are identical across *all
six* columns (the one case this delete pattern can't disambiguate) and
aborts without touching the table if it finds any, since that needs manual
review instead.

Duplicate groups never differ in `uid_local`/`uid_foreign`/`tablenames`/
`fieldname` (that's the grouping key), so no category↔record relation is
ever discarded — only redundant sort-order copies of the same relation.

### `--powermail-marketing`

Loops the 5 `marketing_referer_domain` / `marketing_referer` /
`marketing_country` / `marketing_browser_language` / `marketing_page_funnel`
columns and runs `UPDATE ... SET <column> = '' WHERE <column> IS NULL` via
`QueryBuilder` (not `Connection::update()`, whose identifier-array `WHERE`
can't express `IS NULL`).

### `--legacy-scss-constants`

For every `sys_template` row whose `constants` field references
`plugin.bootstrap_package`, this step:

1. Extracts every key defined there — both the nested
   `plugin.bootstrap_package { settings.scss { ... } }` block and any
   standalone `plugin.bootstrap_package.settings.scss.<key> = ...` lines
   (TypoScript treats both forms identically once parsed, and both are
   exactly what `bk2k\BootstrapPackage\...\CompileService::getVariablesFromConstants()`
   injects as Sass variables before every compile). Block extraction uses
   brace-depth counting, not a fixed-whitespace regex, since indentation
   varies between mandants.
2. Resolves the mandant name from the row's top-level `mandant = ...`
   constant, and locates `fileadmin/templates/<mandant>/_variables.scss`.
   If that file doesn't exist yet, the row is skipped and reported — the
   mandant hasn't been migrated to the current SCSS standard, so there's
   nothing to compare against.
3. Compares: if *every* key found in step 1 is also defined in
   `_variables.scss`, the row is safe — `_variables.scss`'s own assignment
   always wins over the DB-injected one at compile time (later in the
   compile chain), so the DB value is provably dead regardless of what it
   actually is. If *any* key is missing from `_variables.scss`, the row is
   left untouched and reported with exactly which key is missing.
4. On a real run, re-reads the row's `constants` field fresh immediately
   before writing (defends against a TOCTOU gap between the initial scan and
   the write) and re-checks step 3 against that fresh value before removing
   anything.

This directly encodes a real incident from the sibling project this
migration pattern originated in (see `SCSS_migration.md`'s "Optional
follow-up" section): a mandant went live broken after its legacy constants
block was removed based on a partial check, because 5 of its values were
never real Sass `!default`s anywhere — only ever supplied by that DB block.
Presence-in-`_variables.scss` is the only thing checked here specifically
because it's the only fact that determines whether removal can change the
compiled output.

Example dry-run output (abbreviated):

```
Retire fully-covered legacy plugin.bootstrap_package.settings.scss constants
------------------------------------------------------------------------------
 sys_template.uid   mandant          status           detail
 111                ?                skip             no top-level "mandant" constant found
 700                buescher         safe to remove   19 key(s), all covered by _variables.scss
 709                knoll            skip             mandant not yet migrated (_variables.scss missing)
 ...
```

## Usage

```
# refuses to run — no step selected, no --dry-run
ddev exec vendor/bin/typo3 mig12

# preview content-types / category-dedup / powermail-marketing (no --all needed)
ddev exec vendor/bin/typo3 mig12 --dry-run

# run those same three for real
ddev exec vendor/bin/typo3 mig12 --all

# preview and run just one step
ddev exec vendor/bin/typo3 mig12 --category-dedup --dry-run
ddev exec vendor/bin/typo3 mig12 --category-dedup

# --legacy-scss-constants always needs its own explicit flag
ddev exec vendor/bin/typo3 mig12 --legacy-scss-constants --dry-run
ddev exec vendor/bin/typo3 mig12 --legacy-scss-constants

# everything in one run
ddev exec vendor/bin/typo3 mig12 --all --legacy-scss-constants
```

`-v`/`--verbose` shows the per-record dry-run-style table for
`--content-types` even on a real (non-dry-run) invocation.

## Deployment ordering

`--category-dedup` and `--powermail-marketing` must run **before**
`database:updateschema` on any given environment — they're exactly what
unblocks its two failing `ALTER TABLE` statements. `--content-types` and
`--legacy-scss-constants` have no such hard ordering requirement but are
naturally run in the same pass as part of the same legacy-data sweep.

## Verification performed

Run against the local `taketool-eu` ddev database while building this
command:

- `--all --dry-run` correctly reported counts matching direct SQL checks:
  357/184/3 legacy plugin records across the three signatures, 48 duplicate
  `sys_category_record_mm` groups (96 extra rows), 17,372 `NULL` rows in each
  of the 5 powermail marketing columns.
- `--all` (real run): migrated 544 content records, deleted 96 duplicate
  category rows, replaced 86,860 `NULL` cells — 87,500 total, matching the
  command's own summary line.
- Direct DB re-check after the run: 0 remaining `CType="list"` legacy
  records, 0 remaining duplicate category groups, 0 remaining `NULL`
  marketing values.
- Re-running `--all --dry-run` afterward reported "nothing to do" for every
  step (idempotent).
- `database:updateschema` went from reporting both blocking errors to
  completing cleanly (1 field added, 7 changed, 0 errors).
- `--legacy-scss-constants --dry-run` then `--legacy-scss-constants`: 46 of
  88 `sys_template` rows referencing `plugin.bootstrap_package` were fully
  covered and had the block removed; the rest were correctly left alone (2
  rows with no resolvable `mandant` constant, the remainder belonging to
  mandants not yet migrated to `_variables.scss`). Re-running the dry-run
  afterward showed 0 rows left to remove. Spot-checked one mandant's
  `constants` field before/after: only the target block and its standalone
  override line were gone, everything else (`page.logo.*`,
  `plugin.tx_cookieconsent.settings`, `plugin.tx_powermail.settings.*`)
  byte-identical. Recompiled the frontend and confirmed the compiled
  `--bs-primary` (and other custom properties) were unchanged from before
  the cleanup.
- Frontend smoke-tested on several previously-affected sites after the
  `--content-types` migration: still 200, now rendering via the corrected
  native `CType` instead of a TypoScript-alias workaround from an earlier
  session.

See `taketool-eu`'s own `docs/scss_migration_log.md` for the full per-mandant
`--legacy-scss-constants` breakdown.

## Extending this command

If you add a 5th cleanup step:

- Give it its own flag, following the `--kebab-case-name` convention, and
  decide deliberately whether it belongs in `--all` — the bar is "can this
  run blind, the same way, on any environment" (yes → include it) vs. "does
  safety depend on something outside the database, or need a human to read
  the dry-run output first" (no → keep it explicit-only, like
  `--legacy-scss-constants`).
- Keep the `find → report (dry-run table) → real-run → confirm` shape the
  existing steps use — `SymfonyStyle::section()` per step, a summary
  `writeln()`, and returning a changed-row count that feeds the command's
  final total.
- Update this doc's step table and the class-level PHPDoc together — they're
  meant to stay in sync, not duplicate independently.
- Sync `Classes/Command/Mig12Command.php`, `Configuration/Services.yaml`, and
  this file into `taketool-eu`'s `packages/sitepackage/` copy (plain file
  copy, not a submodule — `diff` them to confirm no drift before and after).
