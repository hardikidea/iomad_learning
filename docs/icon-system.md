# Product Icon System

`theme_iomad_learning` owns the product-wide interface icon layer. It renders a
local, versioned SVG sprite and does not modify upstream IOMAD, Moodle, or Boost
files. Logos, user avatars, course photography, certificate seals, generated
documents, and uploaded content remain media and are not replaced.

## Rendering Contract

1. PHP `pix_icon` output is resolved by
   `theme_iomad_learning\output\icon_system_svg`.
2. Every installed plugin receives a deterministic `icon` and `monologo`
   fallback based on component type.
3. Project components, high-use IOMAD components, Moodle activities, actions,
   and operational states have reviewed semantic mappings in
   `theme_iomad_learning\local\icon_catalog`.
4. Image-based activity icons loaded by Moodle templates are upgraded by the
   `iconify` AMD adapter.
5. Raw `fa-*` elements emitted by legacy upstream IOMAD templates are upgraded
   regardless of whether the wrapper is an `i`, `span`, `div`, or another HTML
   element. The reviewed legacy-class map avoids an upstream template fork.
6. Navigation links without an upstream icon receive a route/label mapping and
   then a neutral `link` fallback. This includes `#page-navbar` breadcrumbs.
7. Transparent spacer images become layout-only spans.

The reviewed sprite currently contains 106 symbols, including dedicated
loading, permissions, restore, content-bank, cohort, file-picker view,
language, location, attachment and emoji-category controls. A generic activity
waveform is used only where the source action is genuinely an activity or
telemetry event.

All interface symbols use a `24 24` view box, round line caps and joins, the
configured `iconstrokewidth`, and `currentColor`. Size, active state, header
color, and tenant color therefore remain controlled by typed theme tokens.
Decorative icons are hidden from assistive technology. Meaningful icons retain
the pix alt text as an accessible name. Directional symbols mirror in RTL when
the `rtlmirroricons` token is enabled.

## Semantic Catalogue

| Domain | Sprite symbol |
|---|---|
| Company | `building` |
| Tenant or institution | `institution` |
| Departments and workflow | `workflow` |
| Users and participants | `user`, `group` |
| Courses | `course` |
| Assignments and grading | `edit`, `list` |
| Quizzes and question banks | `list`, `help` |
| H5P and external activities | `puzzle` |
| SCORM and content packages | `package` |
| Certificates | `certificate` |
| Analytics and reports | `chart`, `report` |
| Commerce and payments | `store`, `creditCard` |
| Forms | `form` |
| Monitoring | `monitor` |
| AI course creation | `wand` |

Status meaning does not depend on color. Completion uses `checkCircle`, failure
uses `failure`, pending uses `clock`, incomplete uses `alert`, and suspended
uses `pause`.

## Adding An Icon

1. Reuse an existing semantic sprite symbol where its meaning is exact.
2. Add component, action, or legacy-class resolution to `icon_catalog`.
3. Add a new symbol only when the existing catalogue has no accurate concept.
4. New symbols must use `viewBox="0 0 24 24"`, outline paths, no embedded
   scripts, no external resources, and no hard-coded presentation color.
5. Add catalog tests and verify anonymous, authenticated, mobile, RTL,
   keyboard, and high-contrast states.

Validation:

```bash
./scripts/test-phpunit.sh \
  public/theme/iomad_learning/tests/icon_catalog_test.php
make test
make theme-install
```
