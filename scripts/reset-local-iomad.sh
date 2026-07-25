#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT_DIR}"

usage() {
    cat <<'USAGE'
Usage:
  ./scripts/reset-local-iomad.sh [--yes] [--backup] [--build] [--skip-install]

Destructive local reset for the Docker IOMAD stack.

Deletes:
  - Local Docker Compose database and Redis volumes.
  - Local iomaddata/ files.

Keeps:
  - iomad/ source checkout.
  - iomad-overrides/.
  - backups/.
  - .env.

Options:
  --yes           Do not ask for interactive confirmation.
  --backup        Run scripts/backup.sh before deleting data.
  --build         Run docker compose build before installation.
  --skip-install  Stop after clearing local data and syncing IOMAD source.
  --help          Show this help.
USAGE
}

confirm=false
backup=false
build=false
install=true

while [ "$#" -gt 0 ]; do
    case "$1" in
        --yes)
            confirm=true
            ;;
        --backup)
            backup=true
            ;;
        --build)
            build=true
            ;;
        --skip-install)
            install=false
            ;;
        --help|-h)
            usage
            exit 0
            ;;
        *)
            echo "Unknown option: $1" >&2
            usage
            exit 1
            ;;
    esac
    shift
done

if [ ! -f docker-compose.yml ]; then
    echo "docker-compose.yml not found. Run this script from the project repository." >&2
    exit 1
fi

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

project_name="${COMPOSE_PROJECT_NAME:-iomad_learning}"
http_url="${IOMAD_WWWROOT:-http://localhost:18080}"
admin_user="${IOMAD_ADMIN_USER:-admin}"
admin_password="${IOMAD_ADMIN_PASSWORD:-Admin123!ChangeMe}"

cat <<EOF
This will permanently reset the local IOMAD Docker data for project:
  ${project_name}

It removes local PostgreSQL/Redis Docker volumes and deletes iomaddata/.
All local demo institutions, users, courses, grades, files, logs and certificates will be removed from this stack.
EOF

if [ "${confirm}" != "true" ]; then
    printf '\nType RESET LOCAL IOMAD to continue: '
    IFS= read -r answer
    if [ "${answer}" != "RESET LOCAL IOMAD" ]; then
        echo "Reset cancelled."
        exit 1
    fi
fi

if [ "${backup}" = "true" ]; then
    ./scripts/backup.sh
fi

docker compose down -v --remove-orphans

rm -rf iomaddata
mkdir -p iomaddata

./scripts/bootstrap-iomad.sh

if [ "${build}" = "true" ]; then
    docker compose build
fi

if [ "${install}" = "true" ]; then
    ./scripts/install-site.sh
    cat <<EOF

Fresh local IOMAD reset completed.
URL: ${http_url}
Admin username: ${admin_user}
Admin password: ${admin_password}
EOF
else
    cat <<'EOF'

Local IOMAD data was cleared and IOMAD source was synced.
Run ./scripts/install-site.sh when you are ready to install the fresh database.
EOF
fi
