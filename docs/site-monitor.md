# Site Monitor

`tool_iomadmonitor` provides application-level checks that complement
container, ALB, RDS, EFS and CloudWatch monitoring.

## Checks

- database round trip and schema availability;
- cron freshness;
- Redis-backed PHP session configuration and connectivity;
- dataroot free space;
- failed scheduled and ad-hoc tasks;
- latest complete, checksummed recovery-set age;
- runtime HTTPS/cookie/debug/security settings;
- commerce and connector queue failures;
- deep company/course/user tenant-isolation audit.

Run the normal and deep checks:

```bash
docker compose exec -T iomad \
  php public/admin/tool/iomadmonitor/cli/check.php --output=json

docker compose exec -T iomad \
  php public/admin/tool/iomadmonitor/cli/check.php --output=json --deep
```

Scheduled tasks send throttled alerts only to users with the dedicated alert
capability. Health output contains counts and stable operational identifiers,
not passwords, tokens, email addresses or imported personal rows.

The local backup pipeline atomically updates `backups/latest.env` only after a
complete set passes checksum verification. Web and cron mount that directory
read-only at `/var/backups/iomad`, allowing the monitor to report recovery
freshness without write access.

The monitor now also owns the validated service registry, liveness,
readiness, startup, protected metrics, correlation, redaction, and public
problem contracts. See
[Health and observability](11-operations/health-and-observability.md).
