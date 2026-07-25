#!/usr/bin/env bash
set -euo pipefail

MODE="${1:-commit}"
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT_DIR}"

changed_files=()
while IFS= read -r changed_file; do
    changed_files+=("${changed_file}")
done < <(./scripts/changed-files-from-main.sh "${MODE}")

if [ "${#changed_files[@]}" -eq 0 ]; then
    echo "No changed files found against main for lint checks."
    exit 0
fi

shell_files=()
json_files=()
yaml_files=()
php_files=()
terraform_files=()
dockerfiles=()
compose_check_required=0
renovate_check_required=0

for changed_file in "${changed_files[@]}"; do
    case "${changed_file}" in
        *.sh)
            shell_files+=("${changed_file}")
            ;;
        *.json)
            json_files+=("${changed_file}")
            ;;
        *.yml|*.yaml)
            yaml_files+=("${changed_file}")
            ;;
        *.php)
            php_files+=("${changed_file}")
            ;;
        *.tf)
            terraform_files+=("${changed_file}")
            ;;
        Dockerfile|*/Dockerfile|*.Dockerfile)
            dockerfiles+=("${changed_file}")
            ;;
    esac

    case "${changed_file}" in
        docker-compose.yml|compose.yml|compose.yaml|docker/*|.env.example)
            compose_check_required=1
            ;;
        renovate.json|.github/renovate.json)
            renovate_check_required=1
            ;;
    esac
done

if [ "${#shell_files[@]}" -gt 0 ]; then
    bash -n "${shell_files[@]}"
    if command -v shellcheck >/dev/null 2>&1; then
        shellcheck "${shell_files[@]}"
    else
        echo "shellcheck not found; skipped shellcheck for changed shell files."
    fi
fi

if [ "${#json_files[@]}" -gt 0 ]; then
    if command -v python3 >/dev/null 2>&1; then
        for json_file in "${json_files[@]}"; do
            python3 -m json.tool "${json_file}" >/dev/null
        done
    else
        echo "python3 not found; skipped JSON validation for changed JSON files."
    fi
fi

if [ "${#yaml_files[@]}" -gt 0 ]; then
    if command -v yamllint >/dev/null 2>&1; then
        yamllint "${yaml_files[@]}"
    else
        echo "yamllint not found; skipped YAML lint for changed YAML files."
    fi
fi

if [ "${#php_files[@]}" -gt 0 ]; then
    if command -v php >/dev/null 2>&1; then
        for php_file in "${php_files[@]}"; do
            php -l "${php_file}" >/dev/null
        done
    else
        echo "php not found; skipped PHP syntax lint for changed PHP files."
    fi
fi

if [ "${#terraform_files[@]}" -gt 0 ]; then
    if command -v terraform >/dev/null 2>&1; then
        terraform fmt -check "${terraform_files[@]}"
    else
        echo "terraform not found; skipped Terraform fmt check for changed Terraform files."
    fi
fi

if [ "${#dockerfiles[@]}" -gt 0 ]; then
    if command -v hadolint >/dev/null 2>&1; then
        for dockerfile in "${dockerfiles[@]}"; do
            hadolint --config .hadolint.yaml "${dockerfile}"
        done
    else
        echo "hadolint not found; skipped Dockerfile lint for changed Dockerfiles."
    fi
fi

if [ "${compose_check_required}" -eq 1 ]; then
    docker_bin="${DOCKER_BIN:-}"
    if [ -z "${docker_bin}" ]; then
        docker_bin="$(command -v docker || true)"
    fi
    if [ -z "${docker_bin}" ] && [ -x /usr/local/bin/docker ]; then
        docker_bin="/usr/local/bin/docker"
    fi

    if [ -n "${docker_bin}" ]; then
        "${docker_bin}" compose config --quiet
    else
        echo "Docker CLI not found; skipped Docker Compose config validation."
    fi
fi

if [ "${renovate_check_required}" -eq 1 ]; then
    if command -v node >/dev/null 2>&1 && command -v npx >/dev/null 2>&1; then
        npx --yes --package renovate@43.243.0 -- renovate-config-validator --strict
    else
        echo "node/npx not found; skipped Renovate config validation."
    fi
fi

echo "Changed-file lint checks passed."
