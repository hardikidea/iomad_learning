# White Label

The `theme_iomad_learning` child theme extends Boost and avoids template forks. Shared tokens live in the theme settings and SCSS preset.

Tenant-specific branding should use IOMAD company fields wherever possible:

- company logo and favicon
- hostname and domains
- theme assignment
- `bgcolor_header`, `bgcolor_content`
- `maincolor`, `headingcolor`, `linkcolor`
- `customcss`
- email copy through IOMAD email templates

When `theme_iomad_learning` is active, native company colors map directly to
header, page, primary-action, heading, link, and SVG-icon tokens. The theme
computes readable header foreground and deterministic hover variants. Shared
theme settings remain the fallback for anonymous or unassigned users. Primary
button foregrounds and tenant heading/link colours are also normalized against
their standard surfaces when the supplied colour would fail WCAG AA contrast.

Theme administrators manage shared footer brand, tagline, contact, help,
privacy, terms, and legal text under **Appearance > Themes > IOMAD Learning >
Footer**. Tenant-specific links or copy can still be supplied by supported
IOMAD company CSS. The same tab controls visibility of Moodle support,
current-login and platform/version footer sections. See
[Product icon system](icon-system.md).

Verification checklist:

- anonymous homepage and login
- authenticated dashboard
- mobile viewport
- RTL language state
- keyboard focus and color contrast
- tenant hostname routing
