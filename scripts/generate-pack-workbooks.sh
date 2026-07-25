#!/usr/bin/env bash
set -euo pipefail

if [ -z "${PYTHON_BIN:-}" ]; then
    bundled_python="/Users/hardik.chauhan/.cache/codex-runtimes/codex-primary-runtime/dependencies/python/bin/python3"
    if [ -x "${bundled_python}" ]; then
        PYTHON_BIN="${bundled_python}"
    elif command -v python3 >/dev/null 2>&1; then
        PYTHON_BIN="$(command -v python3)"
    else
        echo "python3 is required to generate workbooks." >&2
        exit 1
    fi
fi
PACK_PATH="${1:-${PACK:-institution-packs/school/sample}}"

"${PYTHON_BIN}" scripts/generate-pack-workbooks.py "${PACK_PATH}"
