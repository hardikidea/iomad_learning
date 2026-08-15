# Operator Command Reference

Run these commands from the repository root. Local lifecycle commands use the
Docker Compose project configured by `.env` and `versions.env`.

## Initial Installation

```bash
cp .env.example .env
make bootstrap
make build
make up
make install
make status
make health
```

`make bootstrap` checks out the exact IOMAD commit from `versions.env` and
syncs project-owned files from `iomad-overrides/`. `make install` creates the
Moodle database only when it is not already installed.

## Routine Local Operations

| Operation | Command |
|---|---|
| Start containers | `make up` |
| Stop containers without deleting data | `make down` |
| Restart the application and cron | `make restart` |
| Show container state | `make status` |
| Follow application logs | `make logs` |
| Open an application shell | `make shell` |
| Run Moodle cron once | `make cron` |
| Check readiness | `make health` |
| Sync project overrides into the detached checkout | `make sync-overrides` |

`make down` does not reset the database. Do not substitute
`docker compose down -v` for the reviewed reset command because that does not
handle `iomaddata/`, optional backup, source synchronization, installation, or
clean-state verification.

## Moodle And IOMAD Upgrade

Moodle core and IOMAD are not independently upgraded in this repository.
IOMAD is a Moodle distribution and the complete supported checkout is pinned
to `IOMAD_COMMIT` in `versions.env`.

### Upgrade To A Reviewed IOMAD/Moodle Commit

```bash
make update IOMAD_COMMIT=<reviewed-40-character-commit-sha>
```

For a local environment where automatic restoration of the recovery set is
desired after a post-image-activation failure:

```bash
make update-restore-on-fail \
  IOMAD_COMMIT=<reviewed-40-character-commit-sha>
```

The update workflow validates compatibility, creates a matching PostgreSQL and
`iomaddata/` recovery set, stops cron, enables maintenance mode, checks out the
target commit detached, syncs overrides, rebuilds the images, runs Composer,
runs Moodle's database upgrade, purges caches, and smoke-tests tenant hosts.
It refuses an apparent source downgrade.

### Upgrade The Database For Already-Deployed Code

Use this sequence only when the exact intended application image is already
running, for example after deploying a reviewed project-plugin release. It
upgrades Moodle core, IOMAD plugins, and project plugins registered in that
image:

```bash
make backup
docker compose stop cron
docker compose exec -T iomad php admin/cli/maintenance.php --enable
docker compose exec -T iomad php admin/cli/upgrade.php --non-interactive
docker compose exec -T iomad php admin/cli/purge_caches.php
docker compose exec -T iomad php admin/cli/maintenance.php --disable
./scripts/tenant-smoke-test.sh
docker compose up -d cron
```

If `admin/cli/upgrade.php` fails, leave maintenance mode enabled and cron
stopped while investigating. After a schema migration starts, do not check out
older code or attempt a database downgrade. Restore the matching database,
dataroot, and immutable image together.

### Upgrade Validation

```bash
make operational-baseline
make test
make phpunit
PREVIOUS_IOMAD_COMMIT=<reviewed-older-commit-sha> make upgrade-test
```

`make upgrade-test` is destructive to the local Docker installation.

## Complete Fresh Local Database

Recommended interactive reset with a verified recovery set and image rebuild:

```bash
make reset-local RESET_ARGS="--backup --build"
```

The operator must type `RESET LOCAL IOMAD`. For an explicitly approved
unattended local reset:

```bash
make reset-local RESET_ARGS="--yes --backup --build"
```

To reset, reinstall, and additionally prove that no IOMAD companies or company
mappings remain:

```bash
make demo-clear RESET_ARGS="--yes --backup --build"
```

The reset removes the local PostgreSQL and Redis volumes and deletes
`iomaddata/`. It then restores the pinned source state and installs a fresh
Moodle/IOMAD database. It keeps `.env`, the detached `iomad/` source checkout,
`iomad-overrides/`, tracked institution packs, and recovery sets in `backups/`.

The fresh site still contains the Moodle site administrator, standard Moodle
roles and plugins, IOMAD, and project plugins. It contains no tenant companies,
tenant users, company mappings, courses created by the demo packs, enrolments,
grades, logs, uploaded files, certificates, or organization-profile records.
All previous local Moodle and IOMAD site data is destroyed; only the newly
installed baseline records remain.

For local disposable data where no recovery set is required:

```bash
make reset-local RESET_ARGS="--yes --build"
```

Never use the local reset workflow in stage or production.

## Demonstration Data

| Operation | Command |
|---|---|
| Verify generated packs | `make demo-check` |
| Import the School and University packs | `make demo-data` |
| Rebuild the local site and import all demos | `make demo-reseed RESEED_ARGS="--yes --backup"` |
| Verify demo counts and tenant isolation | `make demo-verify` |

## Organization Category And Department Setup

Plan all reviewed categories and high-level departments for an existing
company:

```bash
make category-setup COMPANY=GV_SCHOOL ORGANIZATION=ALL
```

Apply the reviewed conflict-free plan:

```bash
make category-setup \
  COMPANY=GV_SCHOOL \
  ORGANIZATION=ALL \
  APPLY=1
```

## Backup And Restore

```bash
make backup
make backup-verify BACKUP_DIR=backups/YYYYMMDD-HHMMSS
make restore BACKUP_DIR=backups/YYYYMMDD-HHMMSS
make backup-restore-test
```

Restore replaces local state and is destructive. Always use one complete,
verified recovery set.

## Validation Commands

```bash
make test
make phpunit
make operational-baseline
make ecosystem-verify \
  VERIFY_ARGS="--company=GV_SCHOOL,NBU_ENGINEERING --format=table --max-report-ms=5000"
```

See [Upgrade](upgrade.md), [backup and restore](backup-restore.md), and
[demo reset and reseed](demo-reset-and-reseed.md) for the complete safety
contracts.
