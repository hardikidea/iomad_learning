# Application Not Ready

## Trigger

`/health/ready` or `/health/startup` returns 503.

## Response

1. Compare active image metadata with `versions.env` and `.iomad-source.env`.
2. Inspect database, Redis, dataroot, security, and service-graph child checks.
3. Keep the target out of load-balancer rotation.
4. If a migration ran, restore the matching recovery set before using the
   previous immutable image. Never downgrade the database.

## Verify

```bash
curl -fsS http://localhost:8080/health/live
curl -fsS http://localhost:8080/health/ready
curl -fsS http://localhost:8080/health/startup
```

