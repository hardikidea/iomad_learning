#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT_DIR}"

MODE="${1:-}"
PACK_PATH="${2:-${PACK:-${INSTITUTIONPACK_DEFAULT_PACK:-institution-packs/school/sample}}}"
COMPOSE_FILE="${IOMAD_COMPOSE_FILE:-docker-compose.yml}"
COMPOSE_SERVICE="${IOMAD_COMPOSE_SERVICE:-iomad}"
COMPOSE_PROJECT_NAME="${IOMAD_COMPOSE_PROJECT_NAME:-}"

if [ -z "${MODE}" ]; then
    echo "Usage: ./scripts/institutionpack-cli.sh <doctor|validate|plan|dry-run|apply|resume|report> [pack-path]" >&2
    exit 1
fi

if [ ! -d iomad/public/local/institutionpack ]; then
    ./scripts/sync-iomad-overrides.sh
fi

DOCKER_BIN="${DOCKER_BIN:-$(command -v docker || true)}"
if [ -z "${DOCKER_BIN}" ] && [ -x /usr/local/bin/docker ]; then
    DOCKER_BIN=/usr/local/bin/docker
fi
if [ -z "${DOCKER_BIN}" ] && [ -x /Applications/Docker.app/Contents/Resources/bin/docker ]; then
    DOCKER_BIN=/Applications/Docker.app/Contents/Resources/bin/docker
fi
if [ -z "${DOCKER_BIN}" ]; then
    echo "Docker CLI was not found. Set DOCKER_BIN to its absolute path." >&2
    exit 1
fi

compose() {
    if [ -n "${COMPOSE_PROJECT_NAME}" ]; then
        "${DOCKER_BIN}" compose \
            --project-name "${COMPOSE_PROJECT_NAME}" \
            -f "${COMPOSE_FILE}" "$@"
        return
    fi
    "${DOCKER_BIN}" compose -f "${COMPOSE_FILE}" "$@"
}

if ! compose ps --status running --quiet "${COMPOSE_SERVICE}" | grep -q .; then
    echo "IOMAD container is not running. Start and install the local stack first:" >&2
    echo "  make bootstrap build up install" >&2
    exit 1
fi

CONTAINER_PACK_PATH="${PACK_PATH}"
case "${PACK_PATH}" in
    institution-packs/*)
        CONTAINER_PACK_PATH="/var/www/${PACK_PATH}"
        ;;
esac

compose exec -T "${COMPOSE_SERVICE}" php public/local/institutionpack/cli/institutionpack.php \
    --mode="${MODE}" \
    --pack="${CONTAINER_PACK_PATH}" \
    --format=json
