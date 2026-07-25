#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SECRETS_DIR="${ROOT_DIR}/.runtime-secrets"
COMPOSE_ARGS=(-f docker-compose.yml -f docker-compose.observability.yml --profile observability)

umask 077
mkdir -p "${SECRETS_DIR}"

generate_secret() {
    local path="$1"
    if [ ! -s "${path}" ]; then
        openssl rand -hex 32 >"${path}"
    fi
    chmod 600 "${path}"
}

generate_secret "${SECRETS_DIR}/monitor_metrics_token"
generate_secret "${SECRETS_DIR}/grafana_admin_password"

docker compose "${COMPOSE_ARGS[@]}" config --quiet

if [ "${1:-}" = "--validate-only" ]; then
    echo "Observability configuration validated."
    exit 0
fi

docker compose "${COMPOSE_ARGS[@]}" up -d --wait
echo "Grafana: http://localhost:${GRAFANA_PORT:-13000}"
echo "Prometheus: http://localhost:${PROMETHEUS_PORT:-19090}"
echo "The Grafana administrator password is stored in .runtime-secrets/grafana_admin_password."
