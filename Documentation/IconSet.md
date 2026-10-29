# Sitepackage icon set

`Classes/Icons/SitepackageIconProvider.php` adds a third icon set, **Sitepackage**,
next to bootstrap_package's own Glyphicons and Ionicons.

It serves every `*.svg` in `Resources/Public/Icons/Set/` (sorted by filename;
the icon name is the filename without extension). To add an icon, drop an SVG
into that folder and flush caches.

## Registration

No configuration is needed. bootstrap_package's `IconProviderInterface` carries
`#[AutoconfigureTag('bootstrapPackage.iconProvider')]`, and this extension's
`Configuration/Services.yaml` autoconfigures everything under `Classes/`, so the
class is picked up by bootstrap_package's `IconProviderService` (a
`ModifyIconProvidersEvent` listener).

## Where it shows up

Every field that offers bootstrap_package's icon-set selector, since they all read
`IconService::getIconProviders()`:

- Icon Group items (`tx_bootstrappackage_icon_group_item`)
- Card Group items (`tx_bootstrappackage_card_group_item`)
- Timeline items (`tx_bootstrappackage_timeline_item`)
- Text & Icon content element (`texticon`)
- Page navigation icon (`pages`)

The stored identifier is the `EXT:sitepackage/Resources/Public/Icons/Set/<file>.svg`
path, rendered by bootstrap_package's `IconsDataProcessor` like any other SVG icon.
