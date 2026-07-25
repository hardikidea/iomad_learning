# Cache Unavailable

## Trigger

`redis` health is failed, login sessions fail, or cache latency is sustained.

## Response

1. Freeze deployments and inspect Redis endpoint, TLS, authentication, memory,
   eviction, and connection limits.
2. Do not switch production sessions to local filesystem storage.
3. Restart application tasks only after Redis is healthy; active sessions may
   require users to authenticate again.

## Verify

```bash
make health
docker compose exec iomad php admin/cli/purge_caches.php
```

Check anonymous and authenticated tenant-hostname smoke tests.

