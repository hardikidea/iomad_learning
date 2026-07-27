# Theme And Live Customizer

`theme_iomad_learning` is a Boost child theme. IOMAD company branding remains
the first source for logo, compact logo, favicon, hostname and company CSS.
The theme supplies safe shared fallbacks and a versioned design-token layer.

## Token Catalog

The catalog contains 251 allow-listed settings across:

- colors and semantic state colors;
- typography, uploaded WOFF2 fonts and reading width;
- spacing, sizing, shape and elevation;
- fluid or constrained content widths and compact page padding;
- dark primary navigation and two-drawer behavior;
- SVG icon visibility, size, stroke, header state, and fallback colors;
- login layout and background assets;
- flex-based course cards and six course-layout treatments;
- dashboard density and information hierarchy;
- focus, reduced-motion, focus-ring and contrast controls.

Values are validated by type and range before they become SCSS variables.
Administrators cannot inject arbitrary PHP, JavaScript or unbounded SCSS
through token fields.

Open **Site administration > Appearance > Themes > IOMAD Learning** for
persisted settings, or `/theme/iomad_learning/customizer.php` for searchable
live preview. The preview does not bypass Moodle capability checks.

IOMAD company `bgcolor_header`, `bgcolor_content`, `maincolor`,
`headingcolor`, and `linkcolor` are applied at request time after shared
tokens. Company `customcss` is applied after those variables. This avoids
compiling one SCSS cache per tenant. Shared footer content is configured on the
Footer tab; tenant logo and favicon continue to come from IOMAD company
branding. The same tab can independently retain or hide Moodle contextual
support links, current-login information and platform/version information.
Tenant button, heading and link colours are checked against their standard
surfaces and normalized to a WCAG AA-readable value when necessary.
Global header text and icon values are also validated against the configured
header background during CSS generation. Normal navigation and controls use a
neutral/burgundy interaction palette; green, blue, amber, and red are reserved
for semantic success, information, warning, and failure states.

Wide Moodle and IOMAD data tables remain semantic tables. The theme wraps them
at runtime in a labelled, keyboard-focusable scroll region on narrow
viewports, avoiding page-level overflow or clipped actions. IOMAD department
trees, search filters, alphabet bars, and company user/manager selectors use
responsive layout rules without changing their native submission behavior.
The native page header is full-width within the content region and keeps
breadcrumbs, titles and actions aligned on desktop, mobile and RTL layouts.
Empty page-header wrappers are removed from the visual and accessibility flow.

## Acceptance

For each release, verify:

1. anonymous login and site home;
2. learner, educator and tenant-manager dashboards;
3. company logo/favicon and theme fallback behavior;
4. desktop and mobile navigation/drawers;
5. dark header contrast and persistent footer content;
6. consistent server, activity, navigation, and legacy SVG icons;
7. focus mode on a course page;
8. LTR and RTL rendering;
9. keyboard focus order and visible focus indicators;
10. reduced-motion preference;
11. automated accessibility scan plus manual zoom/contrast review.
