#!/usr/bin/env bash
set -euo pipefail

PACK_PATH="${1:-${PACK:-institution-packs/school/sample}}"
./scripts/institutionpack-cli.sh plan "${PACK_PATH}"
