#!/usr/bin/env bash
set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT_DIR}"

bash -n init-local-cloud.sh
test -s docker-compose.local.yml
test -s docker/local/default.conf
test -s terraform/local/floci-provider.tf.example

grep -q 'root /var/www/iomad/public;' docker/local/default.conf
grep -q "session_handler_class = '\\\\core\\\\session\\\\redis'" docker/iomad/config.php
grep -q "'use_path_style_endpoint'" docker/iomad/config.php
grep -q 'skip_requesting_account_id  = true' terraform/local/floci-provider.tf.example

DOCKER_BIN="${DOCKER_BIN:-$(command -v docker || true)}"
if [ -z "${DOCKER_BIN}" ] && [ -x /usr/local/bin/docker ]; then
    DOCKER_BIN=/usr/local/bin/docker
fi
if [ -z "${DOCKER_BIN}" ] && [ -x /Applications/Docker.app/Contents/Resources/bin/docker ]; then
    DOCKER_BIN=/Applications/Docker.app/Contents/Resources/bin/docker
fi
if [ -z "${DOCKER_BIN}" ]; then
    echo "Static local-cloud validation passed; Docker Compose validation was skipped."
    exit 0
fi
if ! command -v jq >/dev/null 2>&1; then
    echo "jq is required for local-cloud Compose validation." >&2
    exit 1
fi

set -a
# shellcheck disable=SC1091
source versions.env
# shellcheck disable=SC1091
source .env.local.example
set +a

compose_json="$(
    "${DOCKER_BIN}" compose \
        --project-name "${FLOCI_COMPOSE_PROJECT_NAME}" \
        -f docker-compose.local.yml \
        config --format json
)"

jq -e '
    (.services | keys) == ["floci", "floci-ui", "iomad-php", "web"]
    and (.volumes | keys) == ["floci_state", "iomaddata"]
    and .services.floci.environment.FLOCI_SERVICES_RDS_MOCK == "false"
    and .services.floci.environment.FLOCI_SERVICES_DOCKER_NETWORK != ""
    and any(
        .services.floci.volumes[];
        .type == "bind"
        and .source == "/var/run/docker.sock"
        and .target == "/var/run/docker.sock"
    )
' <<<"${compose_json}" >/dev/null

jq -e \
    --arg floci "${FLOCI_IMAGE}" \
    --arg ui "${FLOCI_UI_IMAGE}" \
    --arg postgres "${FLOCI_POSTGRES_IMAGE}" \
    --arg redis "${FLOCI_REDIS_IMAGE}" \
    '
        .services.floci.image == $floci
        and .services["floci-ui"].image == $ui
        and .services.floci.environment.FLOCI_SERVICES_RDS_DEFAULT_POSTGRES_IMAGE == $postgres
        and .services.floci.environment.FLOCI_SERVICES_ELASTICACHE_DEFAULT_IMAGE == $redis
    ' \
    <<<"${compose_json}" >/dev/null

if jq -r '.services.floci.image, .services["floci-ui"].image' <<<"${compose_json}" \
    | grep -q ':latest'; then
    echo "Floating Floci image tag detected." >&2
    exit 1
fi

echo "Local Floci architecture validation passed."
