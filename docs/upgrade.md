# Upgrade

## Supported Upgrade Boundary

Moodle core and IOMAD are upgraded together. This repository pins the complete
IOMAD distribution, including its Moodle core version, to `IOMAD_COMMIT` in
`versions.env`. Do not update Moodle core separately inside `iomad/` and do not
run `git pull` in production.

Upgrade only to a reviewed IOMAD commit:

```bash
make update IOMAD_COMMIT=<reviewed-40-character-commit-sha>
```

The script:

1. Resolves refs to an exact commit.
2. Validates PHP and PostgreSQL compatibility.
3. Refuses apparent downgrades from the current checkout.
4. Creates a PostgreSQL and `iomaddata/` backup.
5. Records active image/container metadata.
6. Stops cron and enables maintenance mode.
7. Checks out the target commit detached.
8. Runs `make operational-baseline`; source drift in native primitives or a
   tracked hotfix blocks the upgrade for review.
9. Syncs `iomad-overrides/`.
10. Runs Composer, `admin/cli/upgrade.php`, cache purge, and tenant smoke tests.
11. Disables maintenance mode and restarts cron.

Additive project plugin directories are mirrored, not merged. Synchronization
recreates each project-owned plugin root before copying it, so a removed or
renamed override file cannot survive from an older release. The
`scripts/test-override-application.sh` contract exercises this stale-file
failure mode in CI.

For a local environment where automatic restore-on-failure is appropriate:

```bash
make update-restore-on-fail \
  IOMAD_COMMIT=<reviewed-40-character-commit-sha>
```

## Database Upgrade For Current Code

Moodle's database upgrade discovers and upgrades Moodle core, IOMAD plugins,
and project plugins from the already deployed application code. Take a backup,
stop cron, and enter maintenance mode first:

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

Use this sequence only after deploying the exact intended image. If the
database upgrade fails, keep maintenance mode enabled and cron stopped while
investigating. Once schema migration starts, rollback requires the matching
database, `iomaddata/`, and previous immutable image; database downgrade is not
supported.

See [Operator command reference](operator-command-reference.md) for the
complete local lifecycle command list.
