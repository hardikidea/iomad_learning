#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT_DIR}"

./scripts/pack-apply.sh institution-packs/school/sample
./scripts/pack-apply.sh institution-packs/university/sample
./scripts/seed-product-demos.sh

echo "Sanitized school, university and product-suite demos imported."
