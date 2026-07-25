# Upgrade

Upgrade only to a reviewed IOMAD commit.

```bash
./scripts/update-iomad.sh <target-commit-sha>
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

Never use `git pull` in production.
