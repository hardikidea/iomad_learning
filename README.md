# IOMAD Learning

Production-ready IOMAD 5.1 multi-tenant LMS repository. The official upstream source is kept in ignored `iomad/`; project code lives in `iomad-overrides/` and is synced only after the pinned upstream commit is checked out detached.

## Version Pin

- Upstream: `https://github.com/iomad/iomad.git`
- Ref reviewed: `IOMAD_501_STABLE`
- Pinned commit: `55b3128b8058d27f6cc4320850ca709ed5a792a9`
- Web root: `/public`
- PHP: 8.2-8.4, default image `php:8.3-fpm-bookworm`
- PostgreSQL: 15+, default image `postgres:16-bookworm`

Never deploy `latest`, a floating branch, or `git pull` in production.

## Structure

- `iomad/`: ignored official IOMAD checkout.
- `iomad-overrides/`: tracked themes, local plugins, and supported additive overrides.
- `institution-packs/`: versioned school/university CSV/YAML packs.
- `commercial-integrations/`: fail-closed admission manifests for licensed reporting extensions; artifacts are ignored.
- `scripts/`: bootstrap, install, update, backup, restore, import, test, workbook tools.
- `docker/` and `docker-compose.yml`: local IOMAD, PostgreSQL, Redis, cron, MailPit.
- `docker-compose.local.yml`: decoupled Nginx/PHP-FPM stack backed by Floci-managed local AWS services.
- `terraform/`: AWS dev, stage, prod ECS/RDS/EFS/Redis/ALB/Route53 baseline.
- `.github/workflows/`: CI, release, deployment, backup, restore, upgrade.
- `docs/`: setup, operations, upgrade, rollback, data pack, onboarding, security runbooks.

## Quick Start

```bash
cp .env.example .env
make bootstrap
make build
make up
make install
make demo-data
```

Open IOMAD at `http://localhost:18080`. MailPit is at `http://localhost:18025`.
Tenant hostnames use port 80 locally, for example `http://school.localhost`.

Default local credentials are in `.env.example`; change them before any shared environment.

Tenant administration is available in **Site administration > Tenant Master**.
Normal tenant onboarding, academic setup, native user/role management, cohorts,
groups, enrolments, validation, drift, imports, and rollover are UI workflows.
Validated changes synchronize automatically to real IOMAD/Moodle records; a
Tenant Master CLI is not required. See the
[Tenant Master guide](docs/tenant-master/README.md).

To rebuild local IOMAD from an empty database and seed exactly the sanitized
School and University companies:

```bash
make demo-reseed RESEED_ARGS="--yes"
make demo-verify
```

Use `make demo-clear RESET_ARGS="--yes --build"` to stop at clean IOMAD
defaults with zero companies. See
[Demo reset and reseed](docs/demo-reset-and-reseed.md).

For AWS-compatible integration development, provision Floci and install a
separate IOMAD database:

```bash
make local-cloud-validate
make local-cloud-install
```

This exposes Floci at `http://localhost:4566`, its UI at
`http://localhost:4500`, and the isolated IOMAD runtime at
`http://localhost:18081`. Generated local secrets are kept in ignored,
mode-`0600` environment files.

## Data Packs

Validate, plan, and apply packs through the supported local plugin:

```bash
make pack-validate PACK=institution-packs/school/sample
make pack-plan PACK=institution-packs/school/sample
make pack-apply PACK=institution-packs/school/sample
```

Generate operator review workbooks without committing them:

```bash
make pack-workbooks PACK=institution-packs/school/sample
```

The importer is idempotent, checksum-aware, resumable through report metadata, and uses Moodle/IOMAD APIs for writes. Demo passwords are allowed only when `INSTITUTIONPACK_ALLOW_DEMO_PASSWORDS=true`.

## Product Suite

Project-owned IOMAD 5.1 extensions provide:

- AI-assisted draft course creation, H5P blueprints and SCORM export;
- video-first courses, ten dashboard widgets and rapid grading;
- 140 page-builder presets, 30 starter templates and 235 theme tokens;
- tenant analytics, scheduled PDF/XLSX/ODS/CSV reports and intelligent risk labels;
- multi-page conditional forms with files, notifications, privacy and backup/restore;
- tenant commerce, bulk seats, signed webhooks and a WordPress/WooCommerce companion;
- global events, tenant-safe XP/levels/badges, SCORM/H5P reward adapters and optional signed chat commands;
- service registry, health contracts, protected metrics and an optional pinned open-source observability profile.

External AI, payment, IdP and WordPress services remain disabled until their
runtime secrets and provider acceptance are complete. Password synchronization
is intentionally replaced by OIDC/OAuth federation.

## Upgrade Model

```bash
./scripts/update-iomad.sh <reviewed-commit-sha>
```

The update flow validates compatibility, backs up PostgreSQL and `iomaddata/`, records active image metadata, stops cron, enables maintenance mode, checks out the target commit detached, syncs overrides, runs Composer, runs `admin/cli/upgrade.php`, purges caches, and runs tenant smoke tests.

Rollback after schema migration is data rollback: restore the matching database, dataroot, and previous immutable image. Database downgrade is intentionally blocked.

## Backup And Restore

```bash
make backup
make backup-verify BACKUP_DIR=backups/YYYYMMDD-HHMMSS
make backup-prune KEEP_BACKUPS=3
make restore BACKUP_DIR=backups/YYYYMMDD-HHMMSS
make backup-restore-test
```

Local recovery sets contain PostgreSQL, `iomaddata/`, exact IOMAD/image metadata, a manifest, and SHA-256 checksums. Backup pauses cron and writes under maintenance mode; restore verifies the complete set before replacing state, rebuilds the matching immutable image, checks the database schema, and smoke-tests tenants before restarting cron.

## Tests

Safe repository validation:

```bash
make operational-baseline
make test
make phpunit
make ecosystem-verify VERIFY_ARGS="--company=GV_SCHOOL,NBU_ENGINEERING --format=table --max-report-ms=5000"
```

Destructive local install/upgrade tests:

```bash
make clean-install-test
PREVIOUS_IOMAD_COMMIT=<reviewed-older-sha> make upgrade-test
```

## Documentation

- [Documentation index](docs/index.md)
- [Tenant Master UI administration](docs/tenant-master/README.md)
- [Tenant Master architecture and native ownership](docs/tenant-master/architecture.md)
- [Tenant Master import packages](docs/tenant-master/import-packages.md)
- [Tenant Master operations](docs/tenant-master/operations.md)
- [Tenant Master ecosystem verification](docs/tenant-master/ecosystem-verification.md)
- [Tenant Master testing and acceptance](docs/tenant-master/testing-acceptance.md)
- [Setup](docs/setup.md)
- [Architecture](docs/architecture.md)
- [Tenant onboarding](docs/tenant-onboarding.md)
- [Data packs](docs/data-packs.md)
- [White label](docs/white-label.md)
- [Security](docs/security.md)
- [CI/CD](docs/ci-cd.md)
- [Upgrade](docs/upgrade.md)
- [Rollback](docs/rollback.md)
- [Backup and restore](docs/backup-restore.md)
- [Academic rollover](docs/academic-rollover.md)
- [Incident runbook](docs/incident-runbook.md)
- [Compatibility matrix](docs/compatibility-matrix.md)
- [Plugin compatibility policy](docs/plugin-compatibility.md)
- [Feature capability matrix](docs/feature-capability-matrix.md)
- [Product suite acceptance results](docs/acceptance-results-2026-07-25.md)
- [Page builder catalog](docs/page-builder-catalog.md)
- [Commerce and WordPress](docs/commerce-wordpress.md)
- [Theme and live customizer](docs/theme-customizer.md)
- [Product icon system](docs/icon-system.md)
- [Site monitor](docs/site-monitor.md)
- [Health and observability](docs/11-operations/health-and-observability.md)
- [Gamification and global events](docs/05-iomad-domains/gamification-and-global-events.md)
- [Interactive content](docs/05-iomad-domains/interactive-content.md)
- [Notifications and integrations](docs/05-iomad-domains/notifications-and-integrations.md)
- [Role-aware UX](docs/04-development/role-aware-ux.md)
- [Local test data](docs/02-getting-started/local-test-data.md)
- [Demo reset and reseed](docs/demo-reset-and-reseed.md)
- [Administrative CLI operations](docs/cli-operations.md)
- [Commercial reporting integrations](docs/commercial-reporting-integrations.md)
- [IOMAD operational gap assessment](docs/iomad-operational-gap-assessment.md)
- [Local AWS emulation with Floci](docs/local-cloud-floci.md)
- [Terraform](terraform/README.md)

## Official References

- IOMAD upstream: https://github.com/iomad/iomad
- Moodle developer docs: https://moodledev.io/
