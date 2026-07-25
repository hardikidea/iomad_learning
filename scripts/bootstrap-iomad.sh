#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT_DIR}"

if [ ! -f .env ]; then
    cp .env.example .env
    echo "Created .env from .env.example"
fi

set -a
# shellcheck disable=SC1091
source .env
set +a

if [ -f versions.env ]; then
    set -a
    # shellcheck disable=SC1091
    source versions.env
    set +a
fi

IOMAD_REPO="${IOMAD_REPO:-https://github.com/iomad/iomad.git}"
IOMAD_REF="${IOMAD_REF:-IOMAD_501_STABLE}"
IOMAD_COMMIT="${IOMAD_COMMIT:?IOMAD_COMMIT must be set in versions.env or .env}"

mkdir -p iomaddata

remote_ref_sha="$(git ls-remote "${IOMAD_REPO}" "refs/heads/${IOMAD_REF}" | awk '{print $1}')"
if [ -z "${remote_ref_sha}" ]; then
    echo "Unable to resolve ${IOMAD_REF} from ${IOMAD_REPO}" >&2
    exit 1
fi

if [ "${remote_ref_sha}" != "${IOMAD_COMMIT}" ]; then
    cat >&2 <<EOF
Warning: ${IOMAD_REF} currently points at ${remote_ref_sha}; this repository remains pinned to ${IOMAD_COMMIT}.
Update versions.env only after reviewing the new upstream commit.
EOF
fi

if [ ! -d iomad/.git ]; then
    git clone --filter=blob:none --no-checkout "${IOMAD_REPO}" iomad
else
    if ! git -C iomad diff --quiet || ! git -C iomad diff --cached --quiet; then
        echo "Refusing to bootstrap over tracked changes in ignored upstream checkout iomad/." >&2
        echo "Move project customisations into iomad-overrides/ or set aside local upstream edits first." >&2
        exit 1
    fi
    git -C iomad remote set-url origin "${IOMAD_REPO}" || true
fi

git -C iomad fetch --depth 1 origin "${IOMAD_COMMIT}" \
    || git -C iomad fetch --depth 1000 origin "${IOMAD_REF}"
git -C iomad cat-file -e "${IOMAD_COMMIT}^{commit}"
git -C iomad checkout --detach "${IOMAD_COMMIT}"

actual_commit="$(git -C iomad rev-parse HEAD)"
if [ "${actual_commit}" != "${IOMAD_COMMIT}" ]; then
    echo "Expected ${IOMAD_COMMIT}, got ${actual_commit}" >&2
    exit 1
fi

cat > iomad/.iomad-source.env <<EOF
IOMAD_REPO=${IOMAD_REPO}
IOMAD_REF=${IOMAD_REF}
IOMAD_COMMIT=${IOMAD_COMMIT}
BOOTSTRAPPED_AT=$(date -u +%Y-%m-%dT%H:%M:%SZ)
EOF

./scripts/sync-iomad-overrides.sh

echo "IOMAD source is ready at ${ROOT_DIR}/iomad"
git -C iomad log --oneline -1
