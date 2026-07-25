# Backup And Restore

## Recovery-Set Rule

An IOMAD recovery point is one indivisible set:

1. PostgreSQL database dump or RDS snapshot.
2. `iomaddata/` archive or EFS recovery point from the same maintenance window.
3. The exact immutable application image and IOMAD commit.
4. Manifest, checksums, creation time, environment, and operator/audit metadata.

Never restore only the image after `admin/cli/upgrade.php` or an import has changed the schema. Never restore a database without its matching dataroot. Database downgrade is not supported.

## Local Backup Pipeline

Create a recovery set:

```bash
make backup
```

`scripts/backup.sh` performs these stages:

1. Confirms the configured database contains IOMAD tables.
2. Records whether web, cron, and maintenance mode are active.
3. Stops cron and enables maintenance mode when the web service is running.
4. Captures a transactional PostgreSQL dump and the matching `iomaddata/` tree.
5. Records the exact IOMAD commit, `versions.env`, active images, and containers.
6. Writes `manifest.env` and `checksums.sha256`.
7. Runs `scripts/verify-backup.sh`.
8. Atomically updates `backups/latest.env` only after verification succeeds.
9. Returns maintenance and the existing cron container to their original
   state without building, recreating, or changing the active image.

The default destination is `backups/<UTC timestamp>`. Override it without changing source:

```bash
BACKUP_ROOT=/mnt/encrypted-iomad-backups \
BACKUP_REASON=pre-release-2026-07 \
  make backup
```

Each complete set contains:

| File | Purpose |
|---|---|
| `postgres.sql` | PostgreSQL database dump |
| `iomaddata.tar.gz` | Matching dataroot |
| `iomad-version.txt` | Checked-out commit and Git description |
| `versions.env` | Exact source and deterministic image versions |
| `active-images.json` | Compose image metadata |
| `active-containers.json` | Runtime state at capture |
| `manifest.env` | Recovery-set identity, reason, versions, and state |
| `checksums.sha256` | Integrity checks for every artifact above |

`backups/latest.env` is a non-secret pointer containing the latest verified
set path, creation epoch, set ID and manifest hash. The application and cron
containers mount the backup root read-only at `/var/backups/iomad`.
`tool_iomadmonitor` uses the pointer for recovery-freshness alerts. Failed or
interrupted backups never replace it.

An interrupted set contains `INCOMPLETE` and is rejected by verification and restore.

This is the only supported local backup format. Do not use loose SQL dumps,
standalone dataroot archives, Docker-volume copies, or files from the Moodle
course-backup area as platform recovery points. They cannot prove that the
database, dataroot, source commit, and image belong to the same state.

## Local Verification And Restore

Verify without changing runtime state:

```bash
make backup-verify BACKUP_DIR=backups/YYYYMMDD-HHMMSS
```

Restore is destructive and requires an explicit set:

```bash
make restore BACKUP_DIR=backups/YYYYMMDD-HHMMSS
```

The restore pipeline:

1. Verifies required files, manifest status, SHA-256 checksums, PostgreSQL dump header, safe tar paths, and commit agreement.
2. Stops cron, enables maintenance mode, and stops web.
3. Restores the recorded `versions.env` and checks out the recorded commit detached.
4. Applies tracked overrides, recreates PostgreSQL, and imports `postgres.sql`.
5. Moves the current dataroot aside, then extracts the matching archive.
6. Rebuilds and recreates web/cron from the recorded immutable versions.
7. Runs Composer, the no-op/required CLI upgrade, schema validation, cache purge, MailPit configuration, and tenant smoke tests.
8. Disables maintenance and starts cron only after validation succeeds.

On failure, cron stays stopped and maintenance must remain enabled. If dataroot replacement had started, the script prints the preserved pre-restore path. Diagnose before changing traffic or retrying.

Exercise the complete pipeline with temporary artifacts:

```bash
make backup-restore-test
```

The test changes the database-backed no-reply address and adds a dataroot sentinel after backup. It restores the set, proves both changes disappeared, runs tenant smoke tests, and removes the temporary recovery set.

## Retention

`backups/` is ignored by Git and excluded from Docker build context. Move completed local recovery sets to encrypted storage with restricted access. Retention deletion must be a separate operator action after:

- `make backup-verify` succeeds on the retained replacement set;
- the replacement exists in a second failure domain;
- its restore drill has passed within the required recovery objective;
- legal and institutional retention rules allow deletion.

Do not commit dumps, dataroot archives, manifests containing infrastructure identifiers, or personal data.

Superseded local sets are not deleted automatically. To retire one, first
verify the replacement and confirm the replacement has passed
`make backup-restore-test`. Then remove the complete old timestamp directory,
never individual files within a recovery set. Keep the pre-upgrade set until
the release acceptance and rollback window have both closed.

Preview the guarded retention operation:

```bash
make backup-prune KEEP_BACKUPS=3
```

After reviewing the exact timestamp directories:

```bash
make backup-prune KEEP_BACKUPS=3 APPLY=--apply
```

The command verifies the newest retained replacement, refuses to delete the
set referenced by `latest.env`, rejects incomplete/legacy directories, and
defaults to dry-run. With only one verified set, it removes nothing.

## AWS Backup Pipeline

Run `.github/workflows/server_backup.yml` through the protected GitHub environment for `dev`, `stage`, or `prod`. It resolves Terraform outputs, creates an RDS snapshot and EFS AWS Backup job under one recovery-set ID, waits for both when requested, and records the task definition, image, snapshot, recovery point, vault, Git SHA, and timestamps in the job summary.

Required GitHub environment configuration:

- `AWS_ACCOUNT_ID`
- `AWS_REGION`
- `BACKUP_VAULT_NAME`
- `BACKUP_ROLE_ARN`
- environment-specific OIDC role and approvals

For an upgrade backup, always wait for both RDS and EFS completion before migration starts.

## AWS Restore And Cutover

`.github/workflows/server_restore.yml` is a guarded recovery validation and immutable-image rollback workflow. It validates correlated RDS/EFS restore points, pauses cron, enables maintenance, optionally deploys the previous ECS task definition, purges caches, smoke-tests, and restarts cron on success.

It intentionally does not replace the live RDS instance or EFS filesystem. Those stateful operations need environment-specific target identifiers, network validation, Secrets Manager updates, Terraform reconciliation, and an approved traffic cutover. Treat a successful workflow run as authorization evidence and restore-point validation, not proof that data has been restored.

For a schema/data recovery:

1. Record the incident, affected tenant hostnames, current task definition, image digest, RDS identifier, EFS ID, and recovery-set ID.
2. Scale cron to zero and enable IOMAD maintenance mode.
3. Verify the RDS snapshot and EFS recovery point carry the same recovery-set ID and are complete.
4. Restore RDS and EFS to new recovery resources; do not overwrite the only existing copy.
5. Validate encryption, security groups, mount targets, access point ownership, PostgreSQL extensions, and Secrets Manager values.
6. Deploy the exact immutable image recorded by the recovery set.
7. Run `admin/cli/check_database_schema.php`, cache purge, default URL smoke tests, and every tenant-hostname smoke test.
8. Cut the ECS service to the recovered resources only after validation and approval.
9. Restart cron, monitor logs/alarms, and reconcile Terraform state and configuration.
10. Retain the replaced resources until the post-incident review closes the rollback window.

Stage must complete this drill before the same recovery set is used for production.
