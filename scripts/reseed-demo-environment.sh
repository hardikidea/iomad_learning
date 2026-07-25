#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT_DIR}"

usage() {
    cat <<'USAGE'
Usage:
  ./scripts/reseed-demo-environment.sh [--yes] [--backup] [--no-build]

Destructively rebuilds the local stack from a fresh IOMAD database, imports the
sanitized School and University packs, seeds internal feature demonstrations,
and runs tenant-isolation and record-count acceptance checks.

Options:
  --yes       Do not prompt for confirmation.
  --backup    Create and verify a recovery set before resetting.
  --no-build  Reuse the existing application image.
USAGE
}

confirmed=false
backup=false
build=true
while [ "$#" -gt 0 ]; do
    case "$1" in
        --yes)
            confirmed=true
            ;;
        --backup)
            backup=true
            ;;
        --no-build)
            build=false
            ;;
        --help|-h)
            usage
            exit 0
            ;;
        *)
            echo "Unknown option: $1" >&2
            usage >&2
            exit 1
            ;;
    esac
    shift
done

if [ "${confirmed}" != "true" ]; then
    cat <<'EOF'
This permanently removes the current local PostgreSQL, Redis and iomaddata
state before installing and seeding exactly two sanitized demo companies.
Type RESEED SCHOOL UNIVERSITY to continue:
EOF
    IFS= read -r answer
    if [ "${answer}" != "RESEED SCHOOL UNIVERSITY" ]; then
        echo "Demo reseed cancelled."
        exit 1
    fi
fi

./scripts/generate-demo-packs.py
./scripts/generate-demo-packs.py --check
./scripts/validate-pack-files.sh institution-packs/school/sample
./scripts/validate-pack-files.sh institution-packs/university/sample

resetargs=(--yes)
if [ "${backup}" = "true" ]; then
    resetargs+=(--backup)
fi
if [ "${build}" = "true" ]; then
    resetargs+=(--build)
fi

./scripts/reset-local-iomad.sh "${resetargs[@]}"
docker compose exec -T iomad php public/local/institutionpack/cli/verify_clean.php
./scripts/import-demo-packs.sh
docker compose exec -T iomad php admin/cli/cron.php --keep-alive=0
./scripts/verify-demo-environment.sh

echo "Fresh School and University demo environment is ready."
