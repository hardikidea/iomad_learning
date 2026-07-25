# Architecture

This repository runs one IOMAD platform for many institutions. Each institution is an IOMAD company. Parent companies model trusts, societies, groups, universities, or training organisations; child companies model schools, faculties, campuses, departments, or colleges.

## Runtime

- PHP-FPM and Nginx serve IOMAD from `/public`.
- PostgreSQL stores the application database.
- Redis is available for cache/session configuration.
- Cron runs as a separate service using the same image.
- MailPit catches local outbound email.

## Source Model

`versions.env` pins the official IOMAD source. Production images clone the pinned commit, apply tracked overrides, install Composer dependencies, and label the image with `org.opencontainers.image.revision`.

## Tenant Model

Company departments are organisational reporting scopes. Course categories are curriculum hierarchies and must not be used as department substitutes.

School category hierarchy: academic year -> board -> medium -> standard -> stream -> subject.

University category hierarchy: academic year -> faculty -> programme -> semester -> course.

## Capability Boundaries

The product capability register is maintained in
[feature-capability-matrix.md](feature-capability-matrix.md). Native
IOMAD/Moodle availability is not the same as a production-verified feature.
Only capabilities marked **Verified baseline** may be represented as delivered.

Optional domains remain separate supported components:

- AI course authoring: `local_aicoursecreator`
- video-first course presentation: a dedicated course format
- WordPress/WooCommerce synchronization: an external connector plus a narrow
  local web-service plugin when required
- commerce orchestration: payment webhooks, order state and license allocation
- advanced reporting, forms and rapid grading: separate plugins with explicit
  capability and company-scope boundaries

These names define ownership boundaries, not installed plugins. Implementations
must live under `iomad-overrides/`, use Moodle/IOMAD APIs and pass the same
clean-install, upgrade, tenant-isolation and recovery gates as the baseline.
