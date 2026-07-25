# White Label

The `theme_iomad_learning` child theme extends Boost and avoids template forks. Shared tokens live in the theme settings and SCSS preset.

Tenant-specific branding should use IOMAD company fields wherever possible:

- company logo and favicon
- hostname and domains
- theme assignment
- `maincolor`, `headingcolor`, `linkcolor`
- `customcss`
- email copy through IOMAD email templates

Verification checklist:

- anonymous homepage and login
- authenticated dashboard
- mobile viewport
- RTL language state
- keyboard focus and color contrast
- tenant hostname routing
