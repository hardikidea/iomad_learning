#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT_DIR}"

usage() {
    cat <<'USAGE'
Usage:
  ./scripts/provision-category-structure.sh \
    --company=SHORTNAME --organization=NAME|ALL [--apply]

Plans or applies separate Moodle course-category and high-level IOMAD
department hierarchies from the reviewed CSV. Planning is the default. The
command does not create or change companies, courses, users, cohorts, groups,
roles, enrolments, manager assignments, or organization-profile records.
USAGE
}

company="${COMPANY:-}"
organization="${ORGANIZATION:-}"
apply="0"

while [ "$#" -gt 0 ]; do
    case "$1" in
        --company=*)
            company="${1#*=}"
            ;;
        --organization=*)
            organization="${1#*=}"
            ;;
        --apply)
            apply="1"
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

if [[ ! "${company}" =~ ^[A-Za-z0-9_-]+$ ]]; then
    echo "--company must be an existing IOMAD company shortname using letters, numbers, underscores, or hyphens." >&2
    exit 1
fi
if [ -z "${organization}" ]; then
    echo "--organization is required. Use an exact TOP PARENT name from the CSV or ALL." >&2
    exit 1
fi

docker compose exec -T \
    -e CATEGORY_SETUP_COMPANY="${company}" \
    -e CATEGORY_SETUP_ORGANIZATION="${organization}" \
    -e CATEGORY_SETUP_APPLY="${apply}" \
    iomad php /dev/stdin < "${ROOT_DIR}/scripts/provision-category-structure.php"
