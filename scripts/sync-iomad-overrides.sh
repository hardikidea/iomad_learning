#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
IOMAD_DIR="${ROOT_DIR}/iomad"
OVERRIDES_DIR="${ROOT_DIR}/iomad-overrides"

if [ ! -d "${IOMAD_DIR}/.git" ]; then
    echo "IOMAD checkout is missing at ${IOMAD_DIR}. Run ./scripts/bootstrap-iomad.sh first." >&2
    exit 1
fi

if [ ! -d "${OVERRIDES_DIR}" ]; then
    echo "No iomad-overrides directory found. Nothing to sync."
    exit 0
fi

"${ROOT_DIR}/scripts/apply-iomad-overrides.sh" \
    --skip-tracked-overrides \
    "${OVERRIDES_DIR}" \
    "${IOMAD_DIR}"

"${ROOT_DIR}/scripts/configure-iomad-git-excludes.sh"

echo "IOMAD overrides synced into ${IOMAD_DIR}"
