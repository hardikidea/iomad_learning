#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
IOMAD_DIR="${ROOT_DIR}/iomad"
OVERRIDES_DIR="${ROOT_DIR}/iomad-overrides"
RELPATH="${1:-}"

usage() {
    cat <<'USAGE'
Capture one locally installed IOMAD file or plugin directory into iomad-overrides/.

Usage:
  ./scripts/capture-iomad-override.sh public/local/example
  make capture-override RELPATH=public/local/example

This is intentionally the reverse of sync-overrides:
  capture-override: iomad/ -> iomad-overrides/
  sync-overrides:   iomad-overrides/ -> iomad/
USAGE
}

if [ -z "${RELPATH}" ] || [ "${RELPATH}" = "--help" ]; then
    usage
    exit 0
fi

case "${RELPATH}" in
    /*|*..*|"")
        echo "Invalid RELPATH: ${RELPATH}" >&2
        exit 1
        ;;
esac

SOURCE="${IOMAD_DIR}/${RELPATH}"
TARGET="${OVERRIDES_DIR}/${RELPATH}"

if [ ! -e "${SOURCE}" ]; then
    echo "Source does not exist in local IOMAD checkout: ${SOURCE}" >&2
    exit 1
fi

if git -C "${IOMAD_DIR}" ls-files --error-unmatch "${RELPATH}" >/dev/null 2>&1 \
    && [ "${ALLOW_TRACKED_IOMAD_OVERRIDE:-false}" != "true" ]; then
    echo "Refusing to capture tracked upstream IOMAD file: ${RELPATH}" >&2
    echo "Use additive plugins/themes. Set ALLOW_TRACKED_IOMAD_OVERRIDE=true only for a reviewed hotfix." >&2
    exit 1
fi

mkdir -p "$(dirname "${TARGET}")"

if [ -d "${SOURCE}" ]; then
    mkdir -p "${TARGET}"
    if command -v rsync >/dev/null 2>&1; then
        rsync -a --delete "${SOURCE}/" "${TARGET}/"
    else
        rm -rf "${TARGET}"
        cp -a "${SOURCE}" "${TARGET}"
    fi
else
    cp -a "${SOURCE}" "${TARGET}"
fi

"${ROOT_DIR}/scripts/configure-iomad-git-excludes.sh"

echo "Captured ${RELPATH} into iomad-overrides/"
