# Database Unavailable

## Trigger

`database` health is failed, readiness returns 503, or PostgreSQL connection
errors increase.

## Response

1. Freeze deployments and imports. Do not run schema repair or downgrade.
2. Confirm RDS/PostgreSQL availability, network policy, secret version, storage,
   connections, and recent failover events.
3. Keep cron stopped if writes cannot be guaranteed.
4. Recover only from a matching database, dataroot, and immutable-image set.

## Verify

```bash
make health
make cron
```

Resume writes only after database, Redis, dataroot, and tenant smoke checks pass.

