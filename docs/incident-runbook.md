# Incident Runbook

## First Checks

```bash
docker compose ps
docker compose logs --tail=200 iomad
docker compose logs --tail=200 db
docker compose exec iomad php admin/cli/check_database_schema.php
```

## Containment

- Enable maintenance mode for application incidents.
- Stop cron for upgrade/import incidents.
- Preserve current image, database, dataroot, and import report metadata.
- Do not run downgrade SQL.

## Recovery

- For code-only failure before schema migration, deploy the previous immutable image.
- For schema/import failure, restore the matching database and dataroot recovery set.
- Re-run tenant smoke tests on default and tenant hostnames.
