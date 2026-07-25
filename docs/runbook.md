# Operator Runbook

## Routine Commands

```bash
make bootstrap
make build
make up
make install
make demo-data
make backup
make status
```

Verify or drill recovery:

```bash
make backup-verify BACKUP_DIR=backups/YYYYMMDD-HHMMSS
make backup-restore-test
```

## Import

```bash
make pack-validate PACK=institution-packs/school/sample
make pack-plan PACK=institution-packs/school/sample
make pack-apply PACK=institution-packs/school/sample
```

## Upgrade

```bash
./scripts/update-iomad.sh <reviewed-commit-sha>
```

## Incident Response

Use [incident-runbook.md](incident-runbook.md). For schema or import failures, restore the matching database, dataroot, and immutable image recovery set.
