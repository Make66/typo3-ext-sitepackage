# Sticky footer

On pages whose content is shorter than the viewport, the footer
(`#page-footer`) sits at the bottom of the browser window instead of directly
below the content. On longer pages nothing changes: the footer follows the
content as usual. The footer is not `position: fixed`; it does not stay
visible while scrolling.

## Where

`Resources/Public/Scss/Theme/global.scss`, rule `.body-bg`. Every mandant's
glue file `fileadmin/templates/<mandant>/global.scss` imports that file, so the
rule applies to all sites.

## How

`.body-bg` is the page wrapper in `Resources/Private/Layouts/Page/Default.html`.
The only layout all sites use is this one; the `_pl*` overrides contain no page
layouts.

- The wrapper becomes a flex column with `min-height: 100vh`, or `100dvh`
  where the browser supports it, so mobile browser toolbars are accounted for.
- `#page-footer` gets `margin-top: auto`, which takes up the remaining space
  above it.
- Direct children get `flex-shrink: 0`, so header, breadcrumb and content keep
  their natural height and never shrink.

The `.body-bg-top { padding-top }` that mandants set to make room for the
fixed header is included in the `min-height` because Bootstrap uses
`box-sizing: border-box`. Short pages therefore do not get a scrollbar.

## Caveat

Direct children of `.body-bg` are now flex items. A child that relied on
`margin: 0 auto` without an explicit width would shrink to its content width.
None of the current children (navigation, breadcrumb, `main`, footer,
scroll-top, cookie consent, drop-ins) do this. Keep it in mind when adding
markup directly under the wrapper.
