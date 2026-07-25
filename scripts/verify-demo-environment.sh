#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT_DIR}"

docker compose exec -T iomad php public/local/institutionpack/cli/verify_demo.php
docker compose exec -T iomad php public/local/institutionpack/cli/tenant_security_audit.php \
    --mode=strict-isolation-check

echo "Two-company demo environment verification passed."
