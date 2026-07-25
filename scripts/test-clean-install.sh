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
./scripts/tenant-smoke-test.sh
