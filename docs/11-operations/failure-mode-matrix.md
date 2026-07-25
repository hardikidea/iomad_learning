# Failure Mode Matrix

| Failure | Detection | User effect | Retry | Recovery |
|---|---|---|---|---|
| PostgreSQL unavailable | readiness/database check | LMS unavailable | platform-managed | [database runbook](../12-runbooks/database-unavailable.md) |
| Redis unavailable | readiness/Redis check | sessions unavailable | platform-managed | [cache runbook](../12-runbooks/cache-unavailable.md) |
| dataroot low/unmounted | storage check | files unavailable | no write retry | [storage runbook](../12-runbooks/storage-capacity.md) |
| cron stale | cron check | delayed background work | controlled | [cron runbook](../12-runbooks/cron-not-running.md) |
| integration timeout | queue and stable category | optional feature degraded | bounded/idempotent | [integration runbook](../12-runbooks/external-integration-failure.md) |
| import invalid | validator/manifest report | no partial admission | resume after correction | [import runbook](../12-runbooks/import-failure.md) |
| payment provider failure | order state and category | checkout degraded | reconcile first | [payment runbook](../12-runbooks/payment-failure.md) |
| telemetry collector down | missing metrics alert | no LMS effect | fail open | [collector runbook](../12-runbooks/collector-failure.md) |
| schema migration regression | smoke/upgrade tests | release blocked | never downgrade DB | matching DB+dataroot+image restore |
| tenant leakage anomaly | isolation suite/deep audit | security incident | no automatic repair | contain, preserve evidence, incident process |

