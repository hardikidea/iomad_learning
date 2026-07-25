#!/usr/bin/env bash
set -euo pipefail

if [ "${1:-}" != "--yes" ]; then
    cat <<'USAGE'
Usage:
  ./scripts/test-clean-install.sh --yes

Runs a destructive local clean-install test:
  bootstrap -> build -> reset local data -> install -> import sanitized demo packs -> smoke test.
USAGE
    exit 0
fi

./scripts/reset-local-iomad.sh --yes --build
./scripts/import-demo-packs.sh
./scripts/verify-demo-environment.sh
docker compose exec -T iomad php \
    public/local/tenantmaster/cli/verify_mdm_ecosystem.php \
    --company=GV_SCHOOL,NBU_ENGINEERING \
    --format=table \
    --max-report-ms=5000
./scripts/tenant-smoke-test.sh
