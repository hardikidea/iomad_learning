# Product Icon System

`theme_iomad_learning` owns the product-wide icon presentation layer. It
extends Moodle's supported Font Awesome 6 icon system and does not modify
upstream IOMAD, Moodle or Boost files.

## Rendering Rules

1. Core Moodle controls retain Moodle's reviewed Font Awesome mappings.
2. Every installed plugin receives deterministic `icon` and `monologo`
   mappings based on its plugin type.
3. Project components, IOMAD components, activities and operational states use
   explicit semantic mappings from
   `theme_iomad_learning\local\icon_catalog`.
4. The institution and tenant hierarchy concept uses the versioned
   `institution-hierarchy.svg` mask because no single Font Awesome glyph
   represents the combined institution and hierarchy meaning.
5. Icons inherit the active tenant link/icon color and button state color.
6. Decorative icons are hidden from assistive technology. Icon-only controls
   must retain an accessible name on the link or button.

The catalog covers both `icon` and `monologo` keys for every installed plugin.
Standard `pix_icon` output uses the server-side icon system. Moodle views that
intentionally render activity monologos as image URLs are converted by the
`iconify` AMD adapter, including dynamically loaded chooser and calendar
content. The adapter receives its semantic map from `icon_catalog`; it does
not maintain a second independent catalogue. Original plugin assets remain
available as compatibility fallbacks for other themes and future upgrades.
Legacy navigation records that provide an empty icon receive a reviewed
route/label mapping and then a neutral `fa-link` fallback. Transparent Moodle
spacer images are converted to layout-only spans; they are not presented as
icons or exposed to assistive technology.

## Semantic Catalogue

| Domain | Font Awesome or custom icon |
|---|---|
| Company and institution | `fa-building` |
| Tenant hierarchy | custom `institution-hierarchy.svg` mask |
| Departments | `fa-network-wired` |
| Users and participants | `fa-user`, `fa-user-group` |
| Courses | `fa-graduation-cap` |
| Assignments | `fa-file-pen` |
| Quizzes and question banks | `fa-list-check`, `fa-circle-question` |
| H5P and SCORM | `fa-cubes`, `fa-box-open` |
| Certificates | `fa-certificate` |
| Analytics and reports | `fa-chart-line`, `fa-chart-column` |
| Commerce | `fa-cart-shopping` |
| Forms | `fa-file-signature` |
| Grading | `fa-table-list` |
| Monitoring | `fa-heart-pulse` |
| AI course creation | `fa-wand-magic-sparkles` |

Status icons must preserve meaning independently of color. Completion uses
`fa-circle-check`, failure uses `fa-circle-xmark`, pending uses
`fa-regular fa-clock`, and incomplete uses `fa-circle-half-stroke`.

## IOMAD Configuration

IOMAD company administration must use its Font Awesome menu mode:

```bash
docker compose exec -T iomad \
  php admin/cli/cfg.php --component=local_iomad --name=useicons --set=0
```

The repository's local environment is configured with this value. Do not add
new raster menu icons to `iomad-overrides/`.

## Adding An Icon

1. Choose a Font Awesome 6 Free icon with a direct semantic match.
2. Add the component or state key to `icon_catalog`.
3. Use a custom SVG only when Font Awesome has no accurate concept.
4. Custom SVGs must use a `viewBox`, contain no scripts or external resources,
   and render through the theme renderer as a CSS mask so tenant colors remain
   authoritative.
5. Add catalog tests and verify anonymous, authenticated, mobile and RTL
   states.

Validation:

```bash
./scripts/test-phpunit.sh \
  public/theme/iomad_learning/tests/icon_catalog_test.php
make test
make theme-install
```

Logos, user avatars, course photography, certificate seals, generated
documents and uploaded content are media, not interface icons, and are not
replaced by this system.
