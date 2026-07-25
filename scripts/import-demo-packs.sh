#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT_DIR}"

COMPOSE_FILE="${IOMAD_COMPOSE_FILE:-docker-compose.yml}"
COMPOSE_SERVICE="${IOMAD_COMPOSE_SERVICE:-iomad}"
COMPOSE_CRON_SERVICE="${IOMAD_COMPOSE_CRON_SERVICE:-cron}"
COMPOSE_PROJECT_NAME="${IOMAD_COMPOSE_PROJECT_NAME:-}"
compose=(docker compose -f "${COMPOSE_FILE}")
if [ -n "${COMPOSE_PROJECT_NAME}" ]; then
    compose+=(--project-name "${COMPOSE_PROJECT_NAME}")
fi

run_iomad() {
    "${compose[@]}" exec -T "${COMPOSE_SERVICE}" php "$@"
}

cron_was_running=false
if "${compose[@]}" config --services | grep -Fxq "${COMPOSE_CRON_SERVICE}"; then
    if "${compose[@]}" ps --status running --services | grep -Fxq "${COMPOSE_CRON_SERVICE}"; then
        cron_was_running=true
        "${compose[@]}" stop "${COMPOSE_CRON_SERVICE}"
    fi
fi

restore_cron() {
    if [ "${cron_was_running}" = "true" ]; then
        "${compose[@]}" start "${COMPOSE_CRON_SERVICE}" >/dev/null
    fi
}
trap restore_cron EXIT

# IOMAD treats company educators and automatic manager course enrolment as
# mutually exclusive. Demo teachers must remain scoped educators and retain
# their explicit editingteacher enrolments from the canonical packs.
run_iomad admin/cli/cfg.php \
    --component=local_iomad \
    --name=autoenrol_managers \
    --set=0

./scripts/pack-apply.sh institution-packs/school/sample
./scripts/pack-apply.sh institution-packs/university/sample
./scripts/seed-product-demos.sh
run_iomad admin/cli/scheduled_task.php \
    --execute='\local_tenantmaster\task\process_dirty_records'

restore_cron
trap - EXIT

echo "Sanitized school, university and product-suite demos imported."
