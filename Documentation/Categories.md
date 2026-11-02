# System categories (`sys_category`)

The sitepackage adds one field to TYPO3's system categories: a **color**.

## The color field

| | |
|---|---|
| Backend label | **Farbe** (de) / **Color** (en), right after the title |
| Column | `sys_category.tx_sitepackage_color` |
| TCA | `type: color` in `Configuration/TCA/Overrides/sys_category.php`, `l10n_mode: exclude` (translations share the color) |
| Value | Hex color like `#c62828`, or empty |
| Labels | `field.category_color` / `field.category_color.description` in `Resources/Private/Language/(de.)locallang_db.xlf` |

There is no entry in `ext_tables.sql`: TYPO3 generates the column
(`varchar(7)`) from the TCA type. After updating the sitepackage on an
installation, create it with

```bash
vendor/bin/typo3 extension:setup -e sitepackage
```

or via *Admin Tools → Maintenance → Analyze Database Structure*.

The field is added for all categories, whatever they are used for. It is
`exclude: true`, so non-admin editors need it allowed in their backend group
(*Allowed excludefields → Category → Color*).

## For editors

1. Open the category in the List module (folder with the categories).
2. Pick a color in **Farbe**. Use a strong, dark enough color: it is used as
   background behind white text.
3. Save. Pages showing the color update when their cache is cleared or expires.

Leave the field empty to get a color assigned automatically (see below).

## Where the color is used

Currently only in the calendarize month view, see
[Calendarize.md → Month view: category colors](Calendarize.md#month-view-category-colors):

- a thin line under each event in the month grid, one segment per category
- the category filter buttons above the month grid

Categories without a color get one from a fixed palette of 8 strong colors
(`MonthCategoryColorEventListener::PALETTE`), assigned in order of the category
titles. Setting a color on one category can therefore shift the automatic
colors of others. To keep colors stable, set them explicitly on all categories.

## Using the color elsewhere

The value is a plain field. Read it in Fluid from a category record or via
`TYPO3\CMS\Core\Database\ConnectionPool` like
`MonthCategoryColorEventListener::getCategoryColors()` does. calendarize's and
Extbase's `Category` models do not map the field, so `{category.txSitepackageColor}`
does not work on Extbase objects. Use a uid → color map as the month view does,
and set the color as the CSS custom property `--category-color` so the styles stay
in SCSS.
