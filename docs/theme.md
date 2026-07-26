# Theme Setup

The supported project theme is the Boost child theme
`theme_iomad_learning`. IOMAD company logo, compact logo, favicon, hostname,
and company CSS remain authoritative; the child theme supplies reviewed
fallbacks and validated shared design tokens.

The default layout is fluid. It removes Boost's limited-width content cap while
preserving drawer offsets and responsive behavior. Page padding, navigation
icon size, icon color, active icon color, and content width remain typed theme
settings. IOMAD's company `linkcolor` overrides link and navigation-icon tokens
for the active tenant. IOMAD company-admin action names remain owned by IOMAD.
The theme supplies a centralized Font Awesome 6 mapping layer for core,
activity, plugin and status icons without changing IOMAD templates.

See [Theme and live customizer](theme-customizer.md) for configuration and
acceptance. See [Product icon system](icon-system.md) for icon mappings and
custom SVG rules.

Apply and rebuild the theme with:

```bash
make theme-install
```

Do not edit bundled IOMAD themes or templates in the ignored upstream
checkout.
