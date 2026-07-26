# White Label

The `theme_iomad_learning` child theme extends Boost and avoids template forks. Shared tokens live in the theme settings and SCSS preset.

Tenant-specific branding should use IOMAD company fields wherever possible:

- company logo and favicon
- hostname and domains
- theme assignment
- `maincolor`, `headingcolor`, `linkcolor`
- `customcss`
- email copy through IOMAD email templates

When `theme_iomad_learning` is active, the company `linkcolor` controls normal
links and all theme-managed Font Awesome and custom SVG mask icons. The theme
computes a deterministic hover/active variant and keeps the shared theme
setting as the fallback for anonymous or unassigned users. See
[Product icon system](icon-system.md).

Verification checklist:

- anonymous homepage and login
- authenticated dashboard
- mobile viewport
- RTL language state
- keyboard focus and color contrast
- tenant hostname routing
