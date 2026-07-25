#!/usr/bin/env bash
set -euo pipefail

if [ "${1:-}" != "--yes" ] || [ -z "${PREVIOUS_IOMAD_COMMIT:-}" ]; then
    cat <<'USAGE'
Usage:
  PREVIOUS_IOMAD_COMMIT=<reviewed-older-sha> ./scripts/test-upgrade-from-previous.sh --yes

Runs a destructive local previous-version upgrade test:
  checkout previous commit -> clean install -> import demos -> update to versions.env IOMAD_COMMIT -> smoke test.
USAGE
    exit 0
fi

if [ -f versions.env ]; then
    set -a
    # shellcheck disable=SC1091
    source versions.env
    set +a
fi

TARGET_IOMAD_COMMIT="${IOMAD_COMMIT:?IOMAD_COMMIT must be set in versions.env}"

tmp_versions="$(mktemp)"
cp versions.env "${tmp_versions}"
restore_versions() {
    cp "${tmp_versions}" versions.env
    rm -f "${tmp_versions}"
}
trap restore_versions EXIT

set_iomad_commit() {
    local commit="$1"
    local tmpfile
    tmpfile="$(mktemp)"
    awk -v target="${commit}" '
        BEGIN { done = 0 }
        /^IOMAD_COMMIT=/ { print "IOMAD_COMMIT=" target; done = 1; next }
        { print }
        END { if (done == 0) print "IOMAD_COMMIT=" target }
    ' versions.env > "${tmpfile}"
    mv "${tmpfile}" versions.env
}

git -C iomad fetch --depth 1 origin "${PREVIOUS_IOMAD_COMMIT}" \
    || git -C iomad fetch --depth 1000 origin "${IOMAD_REF:-IOMAD_501_STABLE}"
git -C iomad checkout --detach "${PREVIOUS_IOMAD_COMMIT}"

set_iomad_commit "${PREVIOUS_IOMAD_COMMIT}"
./scripts/reset-local-iomad.sh --yes --build
./scripts/import-demo-packs.sh

cp "${tmp_versions}" versions.env
./scripts/update-iomad.sh "${TARGET_IOMAD_COMMIT}"
./scripts/tenant-smoke-test.sh

trap - EXIT
rm -f "${tmp_versions}"
