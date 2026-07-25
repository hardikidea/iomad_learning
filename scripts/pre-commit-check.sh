#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT_DIR}"

if [ -d "${ROOT_DIR}/iomad" ]; then
    ./scripts/sync-iomad-overrides.sh
else
    echo "Local IOMAD checkout not found; skipping override sync. Run ./scripts/bootstrap-iomad.sh before local IOMAD development."
fi

forbidden_files=""
while IFS= read -r staged_file; do
    [ -z "${staged_file}" ] && continue

    case "${staged_file}" in
        .env|.env.*)
            case "${staged_file}" in
                .env.example|.env.local.example)
                    continue
                    ;;
            esac
            forbidden_files="${forbidden_files}${staged_file}"$'\n'
            ;;
        iomad|iomad/*|iomaddata|iomaddata/*|backups|backups/*|plugins|plugins/*)
            forbidden_files="${forbidden_files}${staged_file}"$'\n'
            ;;
        *.tfstate|*.tfstate.*|*.tfplan|terraform.tfvars|*/terraform.tfvars|.terraform/*|*/.terraform/*)
            forbidden_files="${forbidden_files}${staged_file}"$'\n'
            ;;
    esac
done < <(git diff --cached --name-only)

if [ -n "${forbidden_files}" ]; then
    printf 'Refusing to commit local runtime, state, or secret files:\n%s\n' "${forbidden_files}" >&2
    exit 1
fi

./scripts/lint-changed.sh commit

echo "Pre-commit checks passed."
