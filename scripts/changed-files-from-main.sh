#!/usr/bin/env bash
set -euo pipefail

MODE="${1:-push}"
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT_DIR}"

base_ref="${BASE_REF:-}"
if [ -z "${base_ref}" ]; then
    for candidate in origin/main main; do
        if git rev-parse --verify --quiet "${candidate}^{commit}" >/dev/null; then
            base_ref="${candidate}"
            break
        fi
    done
fi

merge_base=""
if [ -n "${base_ref}" ]; then
    merge_base="$(git merge-base HEAD "${base_ref}" 2>/dev/null || true)"
fi

{
    if [ -n "${merge_base}" ]; then
        git diff --name-only --diff-filter=ACMRTUXB "${merge_base}...HEAD"
    elif git rev-parse --verify --quiet HEAD~1 >/dev/null; then
        git diff --name-only --diff-filter=ACMRTUXB HEAD~1...HEAD
    else
        git ls-files
    fi

    case "${MODE}" in
        commit)
            git diff --cached --name-only --diff-filter=ACMRTUXB
            ;;
        push)
            ;;
        *)
            echo "Unsupported mode: ${MODE}. Use commit or push." >&2
            exit 1
            ;;
    esac
} | sort -u | while IFS= read -r changed_file; do
    [ -n "${changed_file}" ] && [ -e "${changed_file}" ] && printf '%s\n' "${changed_file}"
done
