# Rollback

Image rollback alone is valid only before a database schema migration starts.

After `admin/cli/upgrade.php` starts, rollback must restore the matching recovery set:

- previous immutable application image
- database backup or RDS snapshot
- `iomaddata/` backup or EFS recovery point
- source metadata from `versions.env` and `iomad-version.txt`

Database downgrade is not supported and is blocked by `scripts/update-iomad.sh`.

Local restore:

```bash
make backup-verify BACKUP_DIR=backups/YYYYMMDD-HHMMSS
./scripts/restore-backup.sh backups/YYYYMMDD-HHMMSS --yes
```

The restore script leaves cron stopped and reports the preserved pre-restore dataroot when a recovery step fails. Do not resume traffic until schema and tenant smoke checks pass.

For AWS, `server_restore.yml` validates the recovery set and can roll ECS back to the recorded immutable task definition. RDS/EFS restoration and cutover follow the controlled stateful procedure in [Backup and restore](backup-restore.md); the workflow does not overwrite live state automatically.
