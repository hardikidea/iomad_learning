# Telemetry Data Dictionary

## Metrics

All metrics are owned by platform engineering, retained for 15 days locally,
and shown on `IOMAD Platform Overview` directly or through aggregate health.

| Metric | Type/unit | Labels/cardinality | Purpose | Alert |
|---|---|---|---|---|
| `iomad_health_check` | gauge/state | `check`, under 16 | pass 1, warn 0.5, fail 0 | aggregate failure |
| `iomad_health_check_duration_seconds` | gauge/seconds | `check`, under 16 | latest dependency latency | slow check |
| `iomad_health_report` | gauge/state | none, 1 | aggregate health | readiness/aggregate |
| `iomad_health_report_timestamp_seconds` | gauge/epoch seconds | none, 1 | scrape freshness | metrics missing |
| `iomad_exception_total` | counter/events | `category`, under 40 | bounded application errors | application errors |
| `iomad_cron_heartbeat_age_seconds` | gauge/seconds | none, 1 | cron freshness | aggregate health |
| `iomad_dataroot_free_percent` | gauge/percent | none, 1 | private storage capacity | aggregate health |
| `iomad_failed_adhoc_tasks` | gauge/records | none, 1 | failed task saturation | aggregate health |
| `iomad_recovery_set_age_seconds` | gauge/seconds | none, 1 | recovery readiness | aggregate health |
| `iomad_integration_queue_problem_records` | gauge/records | none, 1 | integration saturation | aggregate health |
| `probe_success` | gauge/state | `instance`, 3 local | endpoint availability | readiness |

Allowed check and category labels are fixed in source. User ID, company ID,
tenant hostname, course, raw URL, query string, IP address, email, message
content, prompt, form response, and exception text are prohibited labels.

## Logs

Repository-owned structured error events contain:

| Field | Rule |
|---|---|
| `event` | fixed lowercase event code |
| `category` | exception catalogue key |
| `request_id` | validated or generated opaque ID |
| `component` | allowlisted Moodle component |
| `context` | recursively redacted and bounded |

No log is an authorization or audit substitute. Import manifests, payment
transitions, webhook replay claims, and gamification ledgers remain in their
own domain stores.

## Traces

Manual spans exist only for bounded repository-owned operations such as queued
notification delivery. The exporter:

- validates W3C trace context;
- creates cryptographically random trace/span IDs;
- accepts only fixed operation names and allowlisted scalar attributes;
- uses 100 ms connect and 250 ms total export timeouts;
- fails open when absent or unavailable;
- permits plain HTTP only in the explicit local environment.

This is not full Moodle auto-instrumentation. SQL statements, HTTP bodies,
session data, and personal or tenant identifiers are not captured.
