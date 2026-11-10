# Chatbot page type (1730)

`<page-url>?type=1730` returns the page content as minimal HTML for a chatbot
fetch: no navigation, breadcrumb, footer, cookie consent or scroll-top, and
no `bk2k:frame` wrappers around content elements.

## Pieces

| | |
|---|---|
| TypoScript | `Configuration/TypoScript/Setup/chatbot.typoscript` (imported from `setup.typoscript`) |
| Page template | `Resources/Private/Templates/Chatbot/Page.html`: `<h1>` page title, then `lib.dynamicContent` for colPos 8, 0, 9 |
| Content element layout | `Resources/Private/Layouts/Chatbot/ContentElements/Default.html`: `<section id="c{uid}">`, plain `<hN>` header (level from `header_layout`, default h2, hidden for 100) and `<p>` subheader, then the main section. No `frame-header` div, classes, `<span>`, header link, date, Before/After/Footer sections or frame |

The page object `chatbot` has `typeNum = 1730`, `disableAllHeaderCode = 1`
and sends `X-Robots-Tag: noindex`.

## How the slim layout is selected

The condition `[request.getQueryParams()['type'] == 1730]` adds
`lib.contentElement.layoutRootPaths.40`. Fluid searches the highest index
first, so this layout wins over the normal `ContentElements/Default.html`
only for that request. Normal pages are unaffected.

The condition reads the query parameter, so it only matches `?type=1730`, not a
route-enhancer suffix.

## Extending

Another column or a different content selection goes into `Page.html`. A
content element that renders its own markup (plugins, Hero, Linkbox) keeps
that markup; only the generic frame is removed.
