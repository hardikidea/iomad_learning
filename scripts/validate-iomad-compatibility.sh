#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT_DIR}"

TARGET_COMMIT="${1:-}"

if [ -f .env ]; then
    set -a
    # shellcheck disable=SC1091
    source .env
    set +a
fi

if [ -f versions.env ]; then
    set -a
    # shellcheck disable=SC1091
    source versions.env
    set +a
fi

if [ -z "${TARGET_COMMIT}" ]; then
    echo "Usage: ./scripts/validate-iomad-compatibility.sh <commit-sha>" >&2
    exit 1
fi

if ! [[ "${TARGET_COMMIT}" =~ ^[0-9a-fA-F]{40}$ ]]; then
    echo "Target must be a resolved 40-character commit SHA: ${TARGET_COMMIT}" >&2
    exit 1
fi

php_image="${PHP_IMAGE:-php:8.3-fpm-bookworm}"
php_minor="$(printf '%s\n' "${php_image}" | sed -n 's/^php:\([0-9][.][0-9]\).*/\1/p')"
case "${php_minor}" in
    8.2|8.3|8.4) ;;
    *)
        echo "Unsupported PHP image for IOMAD 5.1: ${php_image}. Use PHP 8.2, 8.3, or 8.4." >&2
        exit 1
        ;;
esac

postgres_image="${POSTGRES_IMAGE:-postgres:16-bookworm}"
postgres_major="$(printf '%s\n' "${postgres_image}" | sed -n 's/^postgres:\([0-9][0-9]*\).*/\1/p')"
if [ -z "${postgres_major}" ] || [ "${postgres_major}" -lt "${POSTGRES_MIN_MAJOR:-15}" ]; then
    echo "Unsupported PostgreSQL image for IOMAD 5.1: ${postgres_image}. Use PostgreSQL 15 or newer." >&2
    exit 1
fi

echo "Compatibility checks passed for ${TARGET_COMMIT}"
