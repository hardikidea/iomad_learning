#!/usr/bin/env bash
set -euo pipefail

project_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${project_root}"

csv_file="${project_root}/iomad-overrides/public/local/orgprofile/data/organization_profiles_master.csv"
apply=false

usage() {
    cat <<'EOF'
Usage: ./scripts/import-orgprofile-configuration.sh [--file PATH] [--apply]

Validates the maintained local_orgprofile CSV by default. Add --apply to store
organization types, user types, forms, categories, fields, and placements.

Options:
  --file PATH  Use a custom host-side CSV instead of the maintained master.
  --apply      Apply atomically. Without this option the command is read-only.
  -h, --help   Show this help.
EOF
}

while [ "$#" -gt 0 ]; do
    case "$1" in
        --file)
            [ "$#" -ge 2 ] || { echo "--file requires a path" >&2; exit 2; }
            csv_file="$2"
            shift 2
            ;;
        --apply)
            apply=true
            shift
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            echo "Unknown option: $1" >&2
            usage >&2
            exit 2
            ;;
    esac
done

[ -f "${csv_file}" ] || { echo "CSV file not found: ${csv_file}" >&2; exit 1; }

compose=(docker compose)
service="${IOMAD_COMPOSE_SERVICE:-iomad}"
cli=(php public/local/orgprofile/cli/import_configuration.php --file=-)
if [ "${apply}" = "true" ]; then
    cli+=(--apply)
fi

if ! "${compose[@]}" exec -T "${service}" test -f public/local/orgprofile/cli/import_configuration.php; then
    echo "The running IOMAD image does not contain the importer." >&2
    echo "Sync, rebuild, recreate, and upgrade the application before retrying." >&2
    exit 1
fi

"${compose[@]}" exec -T "${service}" "${cli[@]}" < "${csv_file}"
