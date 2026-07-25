#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT_DIR}"

./scripts/reset-local-iomad.sh "$@"
docker compose exec -T iomad php public/local/institutionpack/cli/verify_clean.php

echo "Clean IOMAD defaults verified: no companies or company mappings exist."
