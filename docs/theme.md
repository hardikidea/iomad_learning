# Theme Setup

The supported project theme is the Boost child theme
`theme_iomad_learning`. IOMAD company logo, compact logo, favicon, hostname,
and company CSS remain authoritative; the child theme supplies reviewed
fallbacks and validated shared design tokens.

The default layout is fluid and flex-based. It removes Boost's limited-width
content cap while preserving drawer offsets and responsive behavior. The page
shell keeps a dark token-managed header at the top and a persistent,
theme-managed footer at the bottom without forking Boost templates.
Theme-owned action collections, dashboards, preview cards, navigation and
footer bands use flex layout. Upstream data tables retain native table
semantics and receive a keyboard-focusable horizontal scroll region only when
their intrinsic width exceeds the viewport.

Page spacing, SVG icon size and stroke, icon colors, content width, header
colors, footer colors, and footer content remain typed theme settings. The
active IOMAD company fields `bgcolor_header`, `bgcolor_content`, `maincolor`,
`headingcolor`, `linkcolor`, and `customcss` override shared defaults at
request time. Header text and icon tokens are checked against the effective
header background at runtime, including global settings and tenant overrides.
An unreadable configured foreground falls back to a contrast-safe neutral.
IOMAD company-admin action names remain owned by IOMAD.

The theme supplies a centralized local SVG sprite for core, activity, plugin,
navigation, and status icons. Its client adapter upgrades legacy icon markup
without changing IOMAD templates. Breadcrumb and page-navbar links use the same
semantic icon catalogue.

IOMAD department trees, search/filter bars, alphabet filters, flexible report
tables, and company user/manager dual-list selectors retain their native form
controls and server behavior. Theme layout rules make those controls compact,
scrollable where appropriate, and vertically ordered on narrow screens.

The shared Moodle `#page-header` remains native markup but follows the fluid
page width. Breadcrumbs, context title, page buttons, course controls, profile
actions and dynamically injected header actions use one wrapping flex layout.
Structurally empty headers are hidden after render so they do not reserve blank
space or create an empty landmark.

The Footer settings tab controls shared brand, tagline, address, contact,
support hours, legal content, help/privacy/terms links, Facebook, Instagram,
LinkedIn, X, YouTube and WhatsApp links, and whether Moodle support links,
login information and platform information remain visible. Social links use
the local reviewed SVG sprite and render only when configured. Tenant company colours
are normalized at runtime; light action, heading and link colours are adjusted
to preserve WCAG AA foreground contrast.

See [Theme and live customizer](theme-customizer.md) for configuration and
acceptance. See [Product icon system](icon-system.md) for icon mappings and
custom SVG rules.

Apply and rebuild the theme with:

```bash
make theme-install
```

Do not edit bundled IOMAD themes or templates in the ignored upstream
checkout.
