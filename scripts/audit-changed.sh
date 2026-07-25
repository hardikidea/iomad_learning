#!/usr/bin/env bash
set -euo pipefail

MODE="${1:-push}"
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT_DIR}"

changed_files=()
while IFS= read -r changed_file; do
    changed_files+=("${changed_file}")
done < <(./scripts/changed-files-from-main.sh "${MODE}")

if [ "${#changed_files[@]}" -eq 0 ]; then
    echo "No changed files found against main for audit checks."
    exit 0
fi

root_node_audit_required=0
npm_audit_dirs=()
composer_audit_dirs=()
trivy_paths=()
missing_required_tool=0
trivy_db_repository="${TRIVY_DB_REPOSITORY:-ghcr.io/aquasecurity/trivy-db:2}"
trivy_cache_volume="${TRIVY_CACHE_VOLUME:-iomadlearning-trivy-cache}"
trivy_insecure_args=""
if [ "${TRIVY_INSECURE:-true}" = "true" ]; then
    trivy_insecure_args="--insecure"
fi

add_unique() {
    value="$1"
    shift
    for existing in "$@"; do
        [ "${existing}" = "${value}" ] && return 1
    done
    return 0
}

find_docker_bin() {
    docker_bin="${DOCKER_BIN:-}"
    if [ -z "${docker_bin}" ]; then
        docker_bin="$(command -v docker || true)"
    fi
    if [ -z "${docker_bin}" ] && [ -x /usr/local/bin/docker ]; then
        docker_bin="/usr/local/bin/docker"
    fi
    printf '%s\n' "${docker_bin}"
}

for changed_file in "${changed_files[@]}"; do
    case "${changed_file}" in
        package.json|pnpm-lock.yaml)
            root_node_audit_required=1
            ;;
        */package-lock.json)
            audit_dir="$(dirname "${changed_file}")"
            if add_unique "${audit_dir}" "${npm_audit_dirs[@]+"${npm_audit_dirs[@]}"}"; then
                npm_audit_dirs+=("${audit_dir}")
            fi
            ;;
        */package.json)
            audit_dir="$(dirname "${changed_file}")"
            if [ -f "${audit_dir}/package-lock.json" ] && add_unique "${audit_dir}" "${npm_audit_dirs[@]+"${npm_audit_dirs[@]}"}"; then
                npm_audit_dirs+=("${audit_dir}")
            fi
            ;;
        */composer.lock)
            audit_dir="$(dirname "${changed_file}")"
            if add_unique "${audit_dir}" "${composer_audit_dirs[@]+"${composer_audit_dirs[@]}"}"; then
                composer_audit_dirs+=("${audit_dir}")
            fi
            ;;
        */composer.json)
            audit_dir="$(dirname "${changed_file}")"
            if [ -f "${audit_dir}/composer.lock" ] && add_unique "${audit_dir}" "${composer_audit_dirs[@]+"${composer_audit_dirs[@]}"}"; then
                composer_audit_dirs+=("${audit_dir}")
            fi
            ;;
    esac

    case "${changed_file}" in
        *.tf)
            scan_path="$(dirname "${changed_file}")"
            ;;
        Dockerfile|*/Dockerfile|*.Dockerfile|docker-compose.yml|compose.yml|compose.yaml|*.lock|package.json|*/package.json|composer.json|*/composer.json|.github/workflows/*.yml|.github/workflows/*.yaml)
            scan_path="${changed_file}"
            ;;
        *)
            scan_path=""
            ;;
    esac

    if [ -n "${scan_path}" ] && add_unique "${scan_path}" "${trivy_paths[@]+"${trivy_paths[@]}"}"; then
        trivy_paths+=("${scan_path}")
    fi
done

if [ "${root_node_audit_required}" -eq 1 ]; then
    if command -v node >/dev/null 2>&1 && command -v pnpm >/dev/null 2>&1; then
        pnpm audit --prod
    else
        docker_bin="$(find_docker_bin)"
        if [ -n "${docker_bin}" ]; then
            "${docker_bin}" run --rm \
                -v "${ROOT_DIR}:/repo" \
                -w /repo \
                node:24-bookworm \
                sh -lc 'corepack enable && pnpm audit --prod'
        else
            echo "Node.js and pnpm, or Docker, are required for root dependency audit because package.json or pnpm-lock.yaml changed." >&2
            missing_required_tool=1
        fi
    fi
fi

if [ "${#npm_audit_dirs[@]}" -gt 0 ]; then
    if command -v node >/dev/null 2>&1 && command -v npm >/dev/null 2>&1; then
        for audit_dir in "${npm_audit_dirs[@]}"; do
            (cd "${audit_dir}" && npm audit --omit=dev)
        done
    else
        docker_bin="$(find_docker_bin)"
        if [ -n "${docker_bin}" ]; then
            for audit_dir in "${npm_audit_dirs[@]}"; do
                # AUDIT_DIR must expand inside the container shell.
                # shellcheck disable=SC2016
                "${docker_bin}" run --rm \
                    -v "${ROOT_DIR}:/repo" \
                    -w /repo \
                    -e AUDIT_DIR="${audit_dir}" \
                    node:24-bookworm \
                    sh -lc 'cd "${AUDIT_DIR}" && npm audit --omit=dev'
            done
        else
            echo "Node.js and npm, or Docker, are required for package-lock audit in changed package directories." >&2
            missing_required_tool=1
        fi
    fi
fi

if [ "${#composer_audit_dirs[@]}" -gt 0 ]; then
    if command -v composer >/dev/null 2>&1; then
        for audit_dir in "${composer_audit_dirs[@]}"; do
            if [ -f "${audit_dir}/composer.json" ]; then
                (cd "${audit_dir}" && composer audit --locked --no-dev --format=plain)
                continue
            fi

            if [ "${audit_dir}" = "iomad-overrides" ] && [ -f "${ROOT_DIR}/iomad/composer.json" ]; then
                audit_workspace="$(mktemp -d)"
                trap 'rm -rf "${audit_workspace:-}"' EXIT
                cp "${ROOT_DIR}/iomad/composer.json" "${audit_workspace}/composer.json"
                cp "${ROOT_DIR}/${audit_dir}/composer.lock" "${audit_workspace}/composer.lock"
                (cd "${audit_workspace}" && composer audit --locked --no-dev --format=plain)
                rm -rf "${audit_workspace}"
                audit_workspace=""
                trap - EXIT
                continue
            fi

            echo "Composer manifest is missing for changed lock file in ${audit_dir}." >&2
            missing_required_tool=1
        done
    else
        echo "Composer is required for composer.lock audit in changed Composer directories." >&2
        missing_required_tool=1
    fi
fi

if [ "${#trivy_paths[@]}" -gt 0 ]; then
    if command -v trivy >/dev/null 2>&1; then
        for scan_path in "${trivy_paths[@]}"; do
            trivy fs \
                ${trivy_insecure_args} \
                --db-repository "${trivy_db_repository}" \
                --scanners vuln,misconfig \
                --severity HIGH,CRITICAL \
                --ignore-unfixed \
                --exit-code 1 \
                --skip-dirs iomaddata,backups,iomad/.git \
                "${scan_path}"
        done
    else
        docker_bin="$(find_docker_bin)"

        if [ -n "${docker_bin}" ]; then
            for scan_path in "${trivy_paths[@]}"; do
                "${docker_bin}" run --rm \
                    -v "${ROOT_DIR}:/repo" \
                    -v "${trivy_cache_volume}:/root/.cache/trivy" \
                    -w /repo \
                    aquasec/trivy:0.71.2 fs \
                    ${trivy_insecure_args} \
                    --db-repository "${trivy_db_repository}" \
                    --scanners vuln,misconfig \
                    --severity HIGH,CRITICAL \
                    --ignore-unfixed \
                    --exit-code 1 \
                    --skip-dirs iomaddata,backups,iomad/.git \
                    "${scan_path}"
            done
        else
            echo "Trivy or Docker is required for vulnerability and IaC audit of changed files." >&2
            missing_required_tool=1
        fi
    fi
fi

if [ "${missing_required_tool}" -ne 0 ]; then
    exit 1
fi

echo "Changed-file audit checks passed."
