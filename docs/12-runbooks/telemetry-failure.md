# Telemetry Failure

1. Confirm IOMAD liveness/readiness independently. Telemetry is fail-open and
   must not become a learning dependency.
2. Check exporter endpoint/TLS, collector memory/batch queues, Prometheus
   scrape authentication, Loki/Tempo storage, and Grafana provisioning.
3. Disable optional export if it increases application latency or errors.
4. Never broaden labels or enable payload/SQL capture as a quick fix.
5. Recover the collector, verify bounded metrics/logs/traces and alerts, then
   run `make observability-validate`.

