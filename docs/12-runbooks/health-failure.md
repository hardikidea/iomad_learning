# Health Failure Runbook

## Trigger

- `/health/ready` returns 503 for two minutes;
- `IomadReadinessFailed` fires;
- the protected monitor reports a failed critical check.

## Triage

1. Record the UTC time, deployment image digest, request ID, environment, and
   affected tenant hostnames. Do not record learner data.
2. Check liveness:

   ```bash
   curl -fsS http://localhost:18080/health/live
   ```

3. Check readiness and the protected monitor:

   ```bash
   curl -i http://localhost:18080/health/ready
   docker compose exec -T iomad \
     php public/admin/tool/iomadmonitor/cli/check.php --output=text
   ```

4. Inspect service state:

   ```bash
   docker compose ps
   docker compose logs --since=15m iomad cron db redis
   ```

5. If database, Redis, or dataroot is unavailable, stop deployment promotion.
   Do not run the Moodle upgrade again until the dependency is stable.

## Containment

- Database write or storage integrity risk: enable maintenance mode and stop
  cron.
- Redis failure: restore Redis availability; do not disable secure session
  handling as an emergency workaround.
- Bad deployment with no schema migration: redeploy the previous image.
- Failure after schema migration: follow the matching recovery-set rollback;
  never run a database downgrade.

## Recovery

After the dependency is healthy:

```bash
docker compose exec -T iomad php admin/cli/purge_caches.php
docker compose exec -T iomad php admin/cli/cron.php --keep-alive=0
docker compose exec -T iomad \
  php public/admin/tool/iomadmonitor/cli/check.php --deep --output=json
```

Verify the default URL and every configured tenant hostname. Close the incident
only after cron freshness, queue state, tenant isolation, and backup freshness
are healthy.
