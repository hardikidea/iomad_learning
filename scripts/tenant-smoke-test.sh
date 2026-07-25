#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT_DIR}"

if [ -f .env ]; then
    set -a
    # shellcheck disable=SC1091
    source .env
    set +a
fi

base_url="${IOMAD_WWWROOT:-http://localhost:18080}"
tested_hosts=()

curl -fsS "${base_url}/login/index.php" >/dev/null
docker compose exec -T iomad php admin/cli/cfg.php --name=release >/dev/null

if [ -n "${TENANT_HOSTNAMES:-}" ]; then
    IFS=',' read -r -a hosts <<< "${TENANT_HOSTNAMES}"
    for host in "${hosts[@]}"; do
        host="$(printf '%s' "${host}" | xargs)"
        [ -z "${host}" ] && continue
        curl -fsS -H "Host: ${host}" "${base_url}/login/index.php" >/dev/null
        tested_hosts+=("${host}")
    done
fi

if [ "${#tested_hosts[@]}" -gt 0 ]; then
    echo "Tenant smoke checks passed for ${base_url} and hosts: ${tested_hosts[*]}"
else
    echo "Tenant smoke checks passed for ${base_url}"
fi
