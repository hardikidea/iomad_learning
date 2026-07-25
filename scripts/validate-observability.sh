#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT_DIR}"

required=(
    docker-compose.observability.yml
    docker/observability/otel-collector.yaml
    docker/observability/prometheus.yml
    docker/observability/alerts.yml
    docker/observability/loki.yml
    docker/observability/tempo.yml
    docker/observability/grafana/provisioning/dashboards/dashboards.yml
    docker/observability/grafana/provisioning/dashboards/json/iomad-platform-overview.json
)
for path in "${required[@]}"; do
    test -s "${path}" || {
        echo "Missing observability configuration: ${path}" >&2
        exit 1
    }
done

ruby -rjson -e 'JSON.parse(File.read(ARGV.fetch(0)))' \
    docker/observability/grafana/provisioning/dashboards/json/iomad-platform-overview.json

while IFS= read -r runbook; do
    test -s "${runbook}" || {
        echo "Alert references missing runbook: ${runbook}" >&2
        exit 1
    }
done < <(sed -n 's/^[[:space:]]*runbook:[[:space:]]*//p' docker/observability/alerts.yml | sort -u)

while IFS= read -r runbook; do
    test -s "${runbook}" || {
        echo "Monitor source references missing runbook: ${runbook}" >&2
        exit 1
    }
done < <(
    rg -o 'docs/12-runbooks/[a-z0-9-]+\.md' \
        iomad-overrides/public/admin/tool/iomadmonitor/classes/local |
        sed 's/^[^:]*://' |
        sort -u
)

if rg -n 'image:\s*[^#\n]*:latest(?:\s|$)' docker-compose.observability.yml; then
    echo "Floating observability image tag detected." >&2
    exit 1
fi

mkdir -p .runtime-secrets
for name in monitor_metrics_token grafana_admin_password; do
    if [ ! -s ".runtime-secrets/${name}" ]; then
        printf 'validation-only-%048d\n' 0 >".runtime-secrets/${name}"
        chmod 600 ".runtime-secrets/${name}"
    fi
done

docker compose \
    -f docker-compose.yml \
    -f docker-compose.observability.yml \
    --profile observability \
    config --quiet

echo "Observability configuration validated."
