# Upgrade, Backup, And Restore

This document links the three required recovery workflows:

- [Upgrade](upgrade.md)
- [Rollback](rollback.md)
- [Backup and restore](backup-restore.md)

Upgrade safety rules:

- target a reviewed 40-character IOMAD commit SHA
- back up PostgreSQL/RDS and `iomaddata`/EFS before schema migration
- stop cron and enable maintenance mode
- run `admin/cli/upgrade.php` once
- smoke-test every tenant hostname
- restore database, dataroot, and previous immutable image together after schema migration failure
- verify local recovery sets with `make backup-verify`
- exercise database and dataroot rollback with `make backup-restore-test`

The local backup pipeline creates and verifies one checksummed recovery set. The AWS backup workflow correlates RDS and EFS under one recovery-set ID. The AWS restore workflow validates that set and handles immutable-image rollback; stateful RDS/EFS restoration requires the approved cutover steps in [Backup and restore](backup-restore.md).
