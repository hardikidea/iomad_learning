# SLO, Alert, And Dashboard Catalogue

## Proposed Indicators

These are initial engineering targets, not contractual customer SLAs.

| Service indicator | Proposed target | Window | Source |
|---|---:|---|---|
| readiness success | 99.9% | 30 days | `probe_success` |
| core health success | 99.9% | 30 days | `iomad_health_report` |
| p95 health dependency latency | under 2 seconds | 24 hours | health duration |
| scheduled cron freshness | under 10 minutes | continuous | `cron` check |
| verified recovery-set age | under 24 hours | continuous | `backup` check |
| tenant leakage anomalies | zero | every deep audit | `isolation` check |

Production owners must approve windows, maintenance exclusions, and error
budgets after load and recovery testing.

## Alerts

| Alert | Condition | Severity | Runbook |
|---|---|---|---|
| `IomadReadinessFailed` | readiness probe fails for 2 minutes | critical | [health](../12-runbooks/health-failure.md) |
| `IomadAggregateCheckFailed` | any health check fails for 5 minutes | warning | [health](../12-runbooks/health-failure.md) |
| `IomadMetricsMissing` | report timestamp absent for 5 minutes | warning | [collector](../12-runbooks/collector-failure.md) |
| `IomadHealthCheckSlow` | check exceeds 2 seconds for 10 minutes | warning | [health](../12-runbooks/health-failure.md) |
| `IomadApplicationErrors` | stable server-side categories increase | warning | [incident](../incident-runbook.md) |

Alerts contain no personal or tenant dimensions. Alertmanager routing in local
development is intentionally a console sink; production receivers and
escalation ownership require environment approval.

## Dashboard

`IOMAD Platform Overview` is provisioned read-only from
`docker/observability/grafana/provisioning/dashboards/json/`. It contains:

- aggregate platform health;
- failed-check count;
- health dependency latency;
- stable exception rate;
- liveness, readiness, and startup probe state.

Grafana anonymous access and sign-up are disabled. The local password is an
ignored file secret.

