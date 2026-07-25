# Collector Failure Runbook

## Expected Behavior

OpenTelemetry, Loki, Tempo, Grafana, Prometheus, and Alertmanager are optional
consumers. Their failure must not make `/health/ready` fail, block cron, reject
learning events, or alter tenant authorization.

## Triage

```bash
docker compose \
  -f docker-compose.yml \
  -f docker-compose.observability.yml \
  --profile observability ps

docker compose \
  -f docker-compose.yml \
  -f docker-compose.observability.yml \
  --profile observability logs --since=15m \
  otel-collector prometheus loki tempo grafana
```

Run `make observability-validate` before restart. Confirm disk space for the
five named data volumes and check that `.runtime-secrets/` files exist with
mode `0600`.

## Containment

Stop only the optional profile when it is consuming excessive resources:

```bash
make observability-down
```

Do not relax health endpoint authentication, log raw request bodies, enable
anonymous Grafana, or add user/company IDs to labels to accelerate diagnosis.

## Recovery

Start the profile with `make observability-up`. Confirm Prometheus can scrape
`iomad_health_report_timestamp_seconds`, black-box probes are successful, and
Grafana data sources are healthy. Record the telemetry gap in the incident
timeline.
