# Theme And Live Customizer

`theme_iomad_learning` is a Boost child theme. IOMAD company branding remains
the first source for logo, compact logo, favicon, hostname and company CSS.
The theme supplies safe shared fallbacks and a versioned design-token layer.

## Token Catalog

The catalog contains 241 allow-listed settings across:

- colors and semantic state colors;
- typography, uploaded WOFF2 fonts and reading width;
- spacing, sizing, shape and elevation;
- fluid or constrained content widths and compact page padding;
- primary navigation and two-drawer behavior;
- Font Awesome navigation icon visibility, size and fallback colors;
- login layout and background assets;
- course cards, grids and six course-layout treatments;
- dashboard density and information hierarchy;
- focus, reduced-motion, focus-ring and contrast controls.

Values are validated by type and range before they become SCSS variables.
Administrators cannot inject arbitrary PHP, JavaScript or unbounded SCSS
through token fields.

Open **Site administration > Appearance > Themes > IOMAD Learning** for
persisted settings, or `/theme/iomad_learning/customizer.php` for searchable
live preview. The preview does not bypass Moodle capability checks.

IOMAD company `linkcolor` is applied at request time after shared tokens and
controls both links and navigation icons. Company `customcss` is applied after
those variables. This avoids compiling one SCSS cache per tenant.

## Acceptance

For each release, verify:

1. anonymous login and site home;
2. learner, educator and tenant-manager dashboards;
3. company logo/favicon and theme fallback behavior;
4. desktop and mobile navigation/drawers;
5. focus mode on a course page;
6. LTR and RTL rendering;
7. keyboard focus order and visible focus indicators;
8. reduced-motion preference;
9. automated accessibility scan plus manual zoom/contrast review.
