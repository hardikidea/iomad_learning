#!/usr/bin/env bash
set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "${ROOT_DIR}"

COMPOSE_FILE="${LOCAL_CLOUD_COMPOSE_FILE:-docker-compose.local.yml}"
RUNTIME_ENV="${LOCAL_CLOUD_RUNTIME_ENV:-.env.local.runtime}"
SECRETS_ENV="${LOCAL_CLOUD_SECRETS_ENV:-.env.local.secrets}"
START_APP=true
INSTALL_SITE=false

usage() {
    cat <<'USAGE'
Usage: ./init-local-cloud.sh [--provision-only] [--install] [--help]

  --provision-only  Start Floci and provision resources without starting IOMAD.
  --install         Install IOMAD after provisioning when the database is empty.
  --help            Show this help.
USAGE
}

while [ "$#" -gt 0 ]; do
    case "$1" in
        --provision-only)
            START_APP=false
            ;;
        --install)
            INSTALL_SITE=true
            ;;
        --help|-h)
            usage
            exit 0
            ;;
        *)
            echo "Unknown option: $1" >&2
            usage >&2
            exit 2
            ;;
    esac
    shift
done

load_env_file() {
    local file="$1"
    if [ -f "${file}" ]; then
        set -a
        # shellcheck disable=SC1090
        source "${file}"
        set +a
    fi
}

load_env_file versions.env
load_env_file .env.local.example
load_env_file .env.local

DOCKER_BIN="${DOCKER_BIN:-$(command -v docker || true)}"
if [ -z "${DOCKER_BIN}" ] && [ -x /usr/local/bin/docker ]; then
    DOCKER_BIN=/usr/local/bin/docker
fi
if [ -z "${DOCKER_BIN}" ] && [ -x /Applications/Docker.app/Contents/Resources/bin/docker ]; then
    DOCKER_BIN=/Applications/Docker.app/Contents/Resources/bin/docker
fi
if [ -z "${DOCKER_BIN}" ]; then
    echo "Docker CLI was not found. Set DOCKER_BIN to its absolute path." >&2
    exit 1
fi

TF_ENVIRONMENT="${TF_ENVIRONMENT:-dev}"
TF_ENV_DIR="${TF_ENV_DIR:-terraform/envs/${TF_ENVIRONMENT}}"
TF_VARIABLES_FILE="${TF_VARIABLES_FILE:-${TF_ENV_DIR}/variables.tf}"
TFVARS_FILE="${TFVARS_FILE:-${TF_ENV_DIR}/terraform.tfvars}"
if [ ! -f "${TFVARS_FILE}" ]; then
    TFVARS_FILE="${TF_ENV_DIR}/terraform.tfvars.example"
fi

tf_default() {
    local name="$1"
    local fallback="$2"
    local value

    value="$(
        awk -v wanted="${name}" '
            $0 ~ "^[[:space:]]*variable[[:space:]]+\"" wanted "\"[[:space:]]*\\{" {
                invariable = 1
                next
            }
            invariable && $0 ~ "^[[:space:]]*default[[:space:]]*=" {
                value = $0
                sub(/^[^=]*=[[:space:]]*/, "", value)
                sub(/[[:space:]]*#.*$/, "", value)
                gsub(/^[[:space:]]+|[[:space:]]+$/, "", value)
                if (value ~ /^".*"$/) {
                    sub(/^"/, "", value)
                    sub(/"$/, "", value)
                }
                print value
                exit
            }
            invariable && $0 ~ "^[[:space:]]*\\}" {
                invariable = 0
            }
        ' "${TF_VARIABLES_FILE}"
    )"

    if [ -z "${value}" ] || [ "${value}" = "null" ]; then
        printf '%s\n' "${fallback}"
    else
        printf '%s\n' "${value}"
    fi
}

tf_override() {
    local name="$1"
    local value

    if [ ! -f "${TFVARS_FILE}" ]; then
        return 1
    fi

    value="$(
        awk -v wanted="${name}" '
            $0 ~ "^[[:space:]]*" wanted "[[:space:]]*=" {
                value = $0
                sub(/^[^=]*=[[:space:]]*/, "", value)
                sub(/[[:space:]]*#.*$/, "", value)
                gsub(/^[[:space:]]+|[[:space:]]+$/, "", value)
                if (value ~ /^".*"$/) {
                    sub(/^"/, "", value)
                    sub(/"$/, "", value)
                }
                print value
                exit
            }
        ' "${TFVARS_FILE}"
    )"

    [ -n "${value}" ] || return 1
    printf '%s\n' "${value}"
}

tf_value() {
    local name="$1"
    local fallback="$2"
    local value

    if value="$(tf_override "${name}")"; then
        printf '%s\n' "${value}"
    else
        tf_default "${name}" "${fallback}"
    fi
}

tf_resource_value() {
    local type="$1"
    local name="$2"
    local attribute="$3"
    local fallback="$4"
    local file="${5:-terraform/modules/iomad_environment/data.tf}"
    local value

    value="$(
        awk -v wantedtype="${type}" -v wantedname="${name}" -v wantedattribute="${attribute}" '
            $0 ~ "^[[:space:]]*resource[[:space:]]+\"" wantedtype "\"[[:space:]]+\"" wantedname "\"" {
                inresource = 1
                next
            }
            inresource && $0 ~ "^[[:space:]]*" wantedattribute "[[:space:]]*=" {
                value = $0
                sub(/^[^=]*=[[:space:]]*/, "", value)
                sub(/[[:space:]]*#.*$/, "", value)
                gsub(/^[[:space:]]+|[[:space:]]+$/, "", value)
                if (value ~ /^".*"$/) {
                    sub(/^"/, "", value)
                    sub(/"$/, "", value)
                }
                print value
                exit
            }
            inresource && $0 ~ "^[[:space:]]*\\}" {
                inresource = 0
            }
        ' "${file}"
    )"

    printf '%s\n' "${value:-${fallback}}"
}

normalize_aws_name() {
    printf '%s' "$1" \
        | tr '[:upper:]_' '[:lower:]-' \
        | sed -E 's/[^a-z0-9-]+/-/g; s/^-+//; s/-+$//; s/-+/-/g'
}

generate_secret() {
    if command -v openssl >/dev/null 2>&1; then
        openssl rand -hex 24
    else
        LC_ALL=C od -An -N24 -tx1 /dev/urandom | tr -d ' \n'
    fi
}

validate_secret() {
    local name="$1"
    local value="$2"
    if [[ ! "${value}" =~ ^[A-Za-z0-9._~!@%+=:-]+$ ]]; then
        echo "${name} contains unsupported characters for the local env file." >&2
        exit 1
    fi
}

PROJECT_NAME="${PROJECT_NAME:-$(tf_value project_name iomad_learning)}"
AWS_REGION="${AWS_REGION:-$(tf_value aws_region us-west-2)}"
DB_INSTANCE_CLASS="${DB_INSTANCE_CLASS:-$(tf_value database_instance_class db.t4g.micro)}"
DB_ALLOCATED_STORAGE="${DB_ALLOCATED_STORAGE:-$(tf_value database_allocated_storage 50)}"
REDIS_NODE_TYPE="${REDIS_NODE_TYPE:-$(tf_value redis_node_type cache.t4g.micro)}"
DB_ENGINE="${DB_ENGINE:-$(tf_resource_value aws_db_instance iomad engine postgres)}"
DB_ENGINE_VERSION="${DB_ENGINE_VERSION:-$(tf_resource_value aws_db_instance iomad engine_version 16)}"
REDIS_ENGINE="${REDIS_ENGINE:-$(tf_resource_value aws_elasticache_replication_group redis engine redis)}"
REDIS_ENGINE_VERSION="$(
    printf '%s' "${REDIS_ENGINE_VERSION:-$(tf_resource_value aws_elasticache_replication_group redis engine_version 7.1)}" \
        | tr -d '"'
)"

NORMALIZED_PROJECT="$(normalize_aws_name "${PROJECT_NAME}")"
RESOURCE_PREFIX="${NORMALIZED_PROJECT}-${TF_ENVIRONMENT}"
RDS_INSTANCE_ID="${FLOCI_RDS_INSTANCE_ID:-${RESOURCE_PREFIX}-postgres}"
REDIS_GROUP_ID="${FLOCI_REDIS_GROUP_ID:-${RESOURCE_PREFIX}-redis}"
DB_NAME="${IOMAD_DB_NAME:-iomad}"
DB_USER="${IOMAD_DB_USER:-iomad}"
AWS_ACCOUNT_ID="${AWS_ACCOUNT_ID:-000000000000}"
AWS_ACCESS_KEY_ID="${AWS_ACCESS_KEY_ID:-test}"
AWS_SECRET_ACCESS_KEY="${AWS_SECRET_ACCESS_KEY:-test}"
FLOCI_COMPOSE_PROJECT_NAME="${FLOCI_COMPOSE_PROJECT_NAME:-iomad_learning_floci}"
FLOCI_NETWORK_NAME="${FLOCI_NETWORK_NAME:-${FLOCI_COMPOSE_PROJECT_NAME}}"
FLOCI_ENDPOINT="${FLOCI_ENDPOINT:-http://localhost:4566}"
FLOCI_INTERNAL_ENDPOINT="${FLOCI_INTERNAL_ENDPOINT:-http://floci:4566}"
IOMAD_LOCAL_WWWROOT="${IOMAD_LOCAL_WWWROOT:-http://localhost:18081}"
AWS_CLI_IMAGE="${AWS_CLI_IMAGE:-amazon/aws-cli:2.36.7}"
FLOCI_POSTGRES_IMAGE="${FLOCI_POSTGRES_IMAGE:-${POSTGRES_IMAGE:-postgres:16-bookworm}}"
FLOCI_REDIS_IMAGE="${FLOCI_REDIS_IMAGE:-${REDIS_IMAGE:-redis:7-bookworm}}"

export AWS_ACCOUNT_ID AWS_ACCESS_KEY_ID AWS_REGION AWS_SECRET_ACCESS_KEY
export FLOCI_COMPOSE_PROJECT_NAME FLOCI_ENDPOINT FLOCI_INTERNAL_ENDPOINT
export FLOCI_NETWORK_NAME FLOCI_POSTGRES_IMAGE FLOCI_REDIS_IMAGE
export COMPOSE_PROGRESS="${COMPOSE_PROGRESS:-plain}"

umask 077
load_env_file "${SECRETS_ENV}"
FLOCI_DB_PASSWORD="${FLOCI_DB_PASSWORD:-$(generate_secret)}"
IOMAD_ADMIN_PASSWORD="${IOMAD_ADMIN_PASSWORD:-$(generate_secret)}"
validate_secret FLOCI_DB_PASSWORD "${FLOCI_DB_PASSWORD}"
validate_secret IOMAD_ADMIN_PASSWORD "${IOMAD_ADMIN_PASSWORD}"

{
    printf 'FLOCI_DB_PASSWORD=%s\n' "${FLOCI_DB_PASSWORD}"
    printf 'IOMAD_ADMIN_PASSWORD=%s\n' "${IOMAD_ADMIN_PASSWORD}"
} > "${SECRETS_ENV}"
chmod 600 "${SECRETS_ENV}"

compose() {
    "${DOCKER_BIN}" compose \
        --project-name "${FLOCI_COMPOSE_PROJECT_NAME}" \
        -f "${COMPOSE_FILE}" \
        "$@"
}

ensure_image() {
    local image="$1"
    local clean_docker_config

    if "${DOCKER_BIN}" image inspect "${image}" >/dev/null 2>&1; then
        return
    fi

    clean_docker_config="$(mktemp -d "${TMPDIR:-/tmp}/iomad-docker-config.XXXXXX")"
    echo "Pulling ${image} with an isolated Docker client configuration..."
    DOCKER_CONFIG="${clean_docker_config}" \
        "${DOCKER_BIN}" pull --quiet "${image}" >/dev/null
}

prepare_child_images() {
    local postgres_runtime_image
    local redis_runtime_image

    ensure_image "${FLOCI_POSTGRES_IMAGE}"
    ensure_image "${FLOCI_REDIS_IMAGE}"

    # Floci derives major-version aliases from the requested AWS engine
    # version. Point those aliases at the reviewed patch-version images.
    postgres_runtime_image="postgres:${DB_ENGINE_VERSION%%.*}-bookworm"
    redis_runtime_image="redis:${REDIS_ENGINE_VERSION%%.*}-bookworm"
    "${DOCKER_BIN}" image tag "${FLOCI_POSTGRES_IMAGE}" "${postgres_runtime_image}"
    "${DOCKER_BIN}" image tag "${FLOCI_REDIS_IMAGE}" "${redis_runtime_image}"
}

aws_cli() {
    if command -v aws >/dev/null 2>&1; then
        AWS_PAGER="" aws \
            --endpoint-url "${FLOCI_ENDPOINT}" \
            --region "${AWS_REGION}" \
            --no-cli-pager \
            "$@"
        return
    fi

    ensure_image "${AWS_CLI_IMAGE}"
    "${DOCKER_BIN}" run --rm -i \
        --network "${FLOCI_NETWORK_NAME}" \
        -e AWS_ACCESS_KEY_ID="${AWS_ACCESS_KEY_ID}" \
        -e AWS_SECRET_ACCESS_KEY="${AWS_SECRET_ACCESS_KEY}" \
        -e AWS_DEFAULT_REGION="${AWS_REGION}" \
        -e AWS_EC2_METADATA_DISABLED=true \
        "${AWS_CLI_IMAGE}" \
        --endpoint-url "${FLOCI_INTERNAL_ENDPOINT}" \
        --region "${AWS_REGION}" \
        --no-cli-pager \
        "$@"
}

wait_for_floci() {
    for _ in $(seq 1 60); do
        if curl -fsS "${FLOCI_ENDPOINT}/_floci/health" >/dev/null; then
            return
        fi
        sleep 2
    done
    echo "Floci did not become healthy at ${FLOCI_ENDPOINT}." >&2
    return 1
}

create_bucket() {
    local bucket="$1"

    if aws_cli s3api head-bucket --bucket "${bucket}" >/dev/null 2>&1; then
        return
    fi

    if [ "${AWS_REGION}" = "us-east-1" ]; then
        aws_cli s3api create-bucket --bucket "${bucket}" >/dev/null
    else
        aws_cli s3api create-bucket \
            --bucket "${bucket}" \
            --create-bucket-configuration "LocationConstraint=${AWS_REGION}" >/dev/null
    fi

    aws_cli s3api put-public-access-block \
        --bucket "${bucket}" \
        --public-access-block-configuration \
            BlockPublicAcls=true,IgnorePublicAcls=true,BlockPublicPolicy=true,RestrictPublicBuckets=true \
        >/dev/null
    aws_cli s3api put-bucket-versioning \
        --bucket "${bucket}" \
        --versioning-configuration Status=Enabled \
        >/dev/null
}

put_parameter() {
    local name="$1"
    local value="$2"
    local type="${3:-String}"

    aws_cli ssm put-parameter \
        --name "${name}" \
        --type "${type}" \
        --value "${value}" \
        --overwrite \
        >/dev/null
}

wait_for_rds() {
    local status

    for _ in $(seq 1 90); do
        status="$(
            aws_cli rds describe-db-instances \
                --db-instance-identifier "${RDS_INSTANCE_ID}" \
                --query 'DBInstances[0].DBInstanceStatus' \
                --output text 2>/dev/null || true
        )"
        if [ "${status}" = "available" ]; then
            return
        fi
        sleep 2
    done
    echo "RDS instance ${RDS_INSTANCE_ID} did not become available." >&2
    return 1
}

wait_for_redis() {
    local status

    for _ in $(seq 1 90); do
        status="$(
            aws_cli elasticache describe-replication-groups \
                --replication-group-id "${REDIS_GROUP_ID}" \
                --query 'ReplicationGroups[0].Status' \
                --output text 2>/dev/null || true
        )"
        if [ "${status}" = "available" ]; then
            return
        fi
        sleep 2
    done
    echo "ElastiCache group ${REDIS_GROUP_ID} did not become available." >&2
    return 1
}

install_iomad_if_requested() {
    local config_table
    local installed

    [ "${INSTALL_SITE}" = "true" ] || return 0
    config_table="${IOMAD_DB_PREFIX:-mdl_}config"
    installed="$(
        "${DOCKER_BIN}" run --rm \
            --network "${FLOCI_NETWORK_NAME}" \
            -e PGPASSWORD="${FLOCI_DB_PASSWORD}" \
            "${FLOCI_POSTGRES_IMAGE}" \
            psql \
                -h floci \
                -p "${RDS_PORT}" \
                -U "${DB_USER}" \
                -d "${DB_NAME}" \
                -tAc "select to_regclass('${config_table}')" \
            | tr -d '[:space:]'
    )"

    if [ "${installed}" = "${config_table}" ]; then
        echo "IOMAD database is already installed."
    else
        compose exec -T iomad-php php admin/cli/install_database.php \
            --agree-license \
            --adminuser="${IOMAD_ADMIN_USER:-admin}" \
            --adminpass="${IOMAD_ADMIN_PASSWORD}" \
            --adminemail="${IOMAD_ADMIN_EMAIL:-admin@example.local}" \
            --fullname="${IOMAD_SITE_FULLNAME:-IOMAD Learning Floci Local}" \
            --shortname="${IOMAD_SITE_SHORTNAME:-iomadfloci}"
    fi

    compose exec -T iomad-php php admin/cli/cfg.php --name=cookiesecure --set=0
    compose exec -T iomad-php php admin/cli/cfg.php --name=forcelogin --set=0
    compose exec -T iomad-php php admin/cli/cfg.php \
        --name=noreplyaddress \
        --set="${IOMAD_NOREPLY_ADDRESS:-noreply@example.com}"
    compose exec -T iomad-php php admin/cli/upgrade.php --non-interactive
    compose exec -T iomad-php php admin/cli/cfg.php \
        --name=theme \
        --set="${IOMAD_THEME:-iomad_learning}"
    compose exec -T iomad-php php admin/cli/purge_caches.php
}

prepare_child_images
echo "Starting the pinned Floci control plane and UI..."
compose up -d --wait floci floci-ui
wait_for_floci
aws_cli sts get-caller-identity >/dev/null

STATE_BUCKET="${FLOCI_STATE_BUCKET:-${NORMALIZED_PROJECT}-${AWS_ACCOUNT_ID}-${AWS_REGION}-tfstate}"
FILES_BUCKET="${FLOCI_FILES_BUCKET:-${RESOURCE_PREFIX}-iomad-files}"
BACKUP_BUCKET="${FLOCI_BACKUP_BUCKET:-${RESOURCE_PREFIX}-recovery-sets}"

echo "Creating local S3, SSM, IAM, Secrets Manager, and SES resources..."
create_bucket "${STATE_BUCKET}"
create_bucket "${FILES_BUCKET}"
create_bucket "${BACKUP_BUCKET}"

RDS_INSTANCE_COUNT="$(
    aws_cli rds describe-db-instances \
        --db-instance-identifier "${RDS_INSTANCE_ID}" \
        --query 'length(DBInstances)' \
        --output text 2>/dev/null || printf '0\n'
)"
if [ "${RDS_INSTANCE_COUNT}" != "1" ]; then
    aws_cli rds create-db-instance \
        --db-instance-identifier "${RDS_INSTANCE_ID}" \
        --db-instance-class "${DB_INSTANCE_CLASS}" \
        --engine "${DB_ENGINE}" \
        --engine-version "${DB_ENGINE_VERSION}" \
        --allocated-storage "${DB_ALLOCATED_STORAGE}" \
        --db-name "${DB_NAME}" \
        --master-username "${DB_USER}" \
        --master-user-password "${FLOCI_DB_PASSWORD}" \
        >/dev/null
fi
wait_for_rds

REDIS_GROUP_COUNT="$(
    aws_cli elasticache describe-replication-groups \
        --replication-group-id "${REDIS_GROUP_ID}" \
        --query 'length(ReplicationGroups)' \
        --output text 2>/dev/null || printf '0\n'
)"
if [ "${REDIS_GROUP_COUNT}" != "1" ]; then
    aws_cli elasticache create-replication-group \
        --replication-group-id "${REDIS_GROUP_ID}" \
        --replication-group-description "Local cache for ${RESOURCE_PREFIX}" \
        --engine "${REDIS_ENGINE}" \
        --engine-version "${REDIS_ENGINE_VERSION}" \
        --cache-node-type "${REDIS_NODE_TYPE}" \
        >/dev/null
fi
wait_for_redis

RDS_PORT="$(
    aws_cli rds describe-db-instances \
        --db-instance-identifier "${RDS_INSTANCE_ID}" \
        --query 'DBInstances[0].Endpoint.Port' \
        --output text
)"
REDIS_PORT="$(
    aws_cli elasticache describe-replication-groups \
        --replication-group-id "${REDIS_GROUP_ID}" \
        --query 'ReplicationGroups[0].ConfigurationEndpoint.Port' \
        --output text
)"
if [ "${REDIS_PORT}" = "None" ] || [ "${REDIS_PORT}" = "0" ] || [ -z "${REDIS_PORT}" ]; then
    REDIS_PORT="$(
        aws_cli elasticache describe-replication-groups \
            --replication-group-id "${REDIS_GROUP_ID}" \
            --query 'ReplicationGroups[0].NodeGroups[0].PrimaryEndpoint.Port' \
            --output text
    )"
fi

redis_wire_ready() {
    "${DOCKER_BIN}" run --rm \
        --network "${FLOCI_NETWORK_NAME}" \
        "${FLOCI_REDIS_IMAGE}" \
        redis-cli -h floci -p "${REDIS_PORT}" ping 2>/dev/null \
        | grep -qx PONG
}

# Floci 1.5.x persists ElastiCache metadata but does not always restart the
# disposable Redis child after the control plane is recreated.
if ! redis_wire_ready; then
    echo "Reconciling unavailable ElastiCache wire backend..."
    aws_cli elasticache delete-replication-group \
        --replication-group-id "${REDIS_GROUP_ID}" \
        >/dev/null
    for _ in $(seq 1 30); do
        REDIS_GROUP_COUNT="$(
            aws_cli elasticache describe-replication-groups \
                --replication-group-id "${REDIS_GROUP_ID}" \
                --query 'length(ReplicationGroups)' \
                --output text 2>/dev/null || printf '0\n'
        )"
        [ "${REDIS_GROUP_COUNT}" = "0" ] && break
        sleep 1
    done
    aws_cli elasticache create-replication-group \
        --replication-group-id "${REDIS_GROUP_ID}" \
        --replication-group-description "Local cache for ${RESOURCE_PREFIX}" \
        --engine "${REDIS_ENGINE}" \
        --engine-version "${REDIS_ENGINE_VERSION}" \
        --cache-node-type "${REDIS_NODE_TYPE}" \
        >/dev/null
    wait_for_redis
    REDIS_PORT="$(
        aws_cli elasticache describe-replication-groups \
            --replication-group-id "${REDIS_GROUP_ID}" \
            --query 'ReplicationGroups[0].ConfigurationEndpoint.Port' \
            --output text
    )"
fi

PARAMETER_PREFIX="/${NORMALIZED_PROJECT}/${TF_ENVIRONMENT}/iomad"
put_parameter "${PARAMETER_PREFIX}/db/host" floci
put_parameter "${PARAMETER_PREFIX}/db/port" "${RDS_PORT}"
put_parameter "${PARAMETER_PREFIX}/db/name" "${DB_NAME}"
put_parameter "${PARAMETER_PREFIX}/db/user" "${DB_USER}"
put_parameter "${PARAMETER_PREFIX}/db/password" "${FLOCI_DB_PASSWORD}" SecureString
put_parameter "${PARAMETER_PREFIX}/redis/host" floci
put_parameter "${PARAMETER_PREFIX}/redis/port" "${REDIS_PORT}"
put_parameter "${PARAMETER_PREFIX}/s3/endpoint" "${FLOCI_INTERNAL_ENDPOINT}"
put_parameter "${PARAMETER_PREFIX}/s3/files-bucket" "${FILES_BUCKET}"
put_parameter "${PARAMETER_PREFIX}/s3/backup-bucket" "${BACKUP_BUCKET}"

SECRET_ID="${RESOURCE_PREFIX}/iomad"
SECRET_VALUE="$(
    jq -cn \
        --arg dbpassword "${FLOCI_DB_PASSWORD}" \
        --arg adminpassword "${IOMAD_ADMIN_PASSWORD}" \
        '{POSTGRES_PASSWORD: $dbpassword, IOMAD_ADMIN_PASSWORD: $adminpassword}'
)"
if aws_cli secretsmanager describe-secret --secret-id "${SECRET_ID}" >/dev/null 2>&1; then
    aws_cli secretsmanager put-secret-value \
        --secret-id "${SECRET_ID}" \
        --secret-string "${SECRET_VALUE}" \
        >/dev/null
else
    aws_cli secretsmanager create-secret \
        --name "${SECRET_ID}" \
        --secret-string "${SECRET_VALUE}" \
        >/dev/null
fi

TRUST_POLICY='{"Version":"2012-10-17","Statement":[{"Effect":"Allow","Principal":{"Service":"ecs-tasks.amazonaws.com"},"Action":"sts:AssumeRole"}]}'
ROLE_NAME="${RESOURCE_PREFIX}-iomad-task"
if ! aws_cli iam get-role --role-name "${ROLE_NAME}" >/dev/null 2>&1; then
    aws_cli iam create-role \
        --role-name "${ROLE_NAME}" \
        --assume-role-policy-document "${TRUST_POLICY}" \
        >/dev/null
fi
TASK_POLICY="$(
    jq -cn \
        --arg files "arn:aws:s3:::${FILES_BUCKET}" \
        --arg backups "arn:aws:s3:::${BACKUP_BUCKET}" \
        --arg parameters "arn:aws:ssm:${AWS_REGION}:${AWS_ACCOUNT_ID}:parameter${PARAMETER_PREFIX}/*" \
        '{
            Version: "2012-10-17",
            Statement: [
                {
                    Effect: "Allow",
                    Action: ["s3:ListBucket"],
                    Resource: [$files, $backups]
                },
                {
                    Effect: "Allow",
                    Action: ["s3:GetObject", "s3:PutObject", "s3:DeleteObject"],
                    Resource: [($files + "/*"), ($backups + "/*")]
                },
                {
                    Effect: "Allow",
                    Action: ["ssm:GetParameter", "ssm:GetParameters"],
                    Resource: [$parameters]
                },
                {
                    Effect: "Allow",
                    Action: ["ses:SendEmail", "ses:SendRawEmail"],
                    Resource: ["*"]
                }
            ]
        }'
)"
aws_cli iam put-role-policy \
    --role-name "${ROLE_NAME}" \
    --policy-name "${RESOURCE_PREFIX}-application" \
    --policy-document "${TASK_POLICY}" \
    >/dev/null
aws_cli ses verify-email-identity \
    --email-address "${IOMAD_NOREPLY_ADDRESS:-noreply@example.local}" \
    >/dev/null

{
    printf 'IOMAD_DB_TYPE=pgsql\n'
    printf 'IOMAD_DB_HOST=floci\n'
    printf 'IOMAD_DB_PORT=%s\n' "${RDS_PORT}"
    printf 'IOMAD_DB_NAME=%s\n' "${DB_NAME}"
    printf 'IOMAD_DB_USER=%s\n' "${DB_USER}"
    printf 'IOMAD_DB_PASSWORD=%s\n' "${FLOCI_DB_PASSWORD}"
    printf 'IOMAD_DB_PREFIX=%s\n' "${IOMAD_DB_PREFIX:-mdl_}"
    printf 'POSTGRES_DB=%s\n' "${DB_NAME}"
    printf 'POSTGRES_USER=%s\n' "${DB_USER}"
    printf 'POSTGRES_PASSWORD=%s\n' "${FLOCI_DB_PASSWORD}"
    printf 'IOMAD_REDIS_HOST=floci\n'
    printf 'IOMAD_REDIS_PORT=%s\n' "${REDIS_PORT}"
    printf 'IOMAD_REDIS_TLS=false\n'
    printf 'IOMAD_REDIS_PREFIX=%s\n' "${RESOURCE_PREFIX}-session-"
    printf 'IOMAD_AWS_ENDPOINT=%s\n' "${FLOCI_INTERNAL_ENDPOINT}"
    printf 'IOMAD_AWS_S3_BUCKET=%s\n' "${FILES_BUCKET}"
    printf 'IOMAD_AWS_USE_PATH_STYLE_ENDPOINT=true\n'
    printf 'AWS_ENDPOINT_URL=%s\n' "${FLOCI_INTERNAL_ENDPOINT}"
    printf 'AWS_REGION=%s\n' "${AWS_REGION}"
    printf 'AWS_DEFAULT_REGION=%s\n' "${AWS_REGION}"
    printf 'AWS_ACCESS_KEY_ID=%s\n' "${AWS_ACCESS_KEY_ID}"
    printf 'AWS_SECRET_ACCESS_KEY=%s\n' "${AWS_SECRET_ACCESS_KEY}"
    printf 'AWS_EC2_METADATA_DISABLED=true\n'
    printf 'IOMAD_WWWROOT=%s\n' "${IOMAD_LOCAL_WWWROOT}"
    printf 'IOMAD_DATAROOT=/var/www/iomaddata\n'
    printf 'IOMAD_DIR=/var/www/iomad\n'
    printf 'IOMAD_COMPOSER_INSTALL=false\n'
    printf 'IOMAD_ADMIN_USER=%s\n' "${IOMAD_ADMIN_USER:-admin}"
    printf 'IOMAD_ADMIN_PASSWORD=%s\n' "${IOMAD_ADMIN_PASSWORD}"
    printf 'IOMAD_ADMIN_EMAIL=%s\n' "${IOMAD_ADMIN_EMAIL:-admin@example.local}"
    printf 'IOMAD_NOREPLY_ADDRESS=%s\n' \
        "${IOMAD_NOREPLY_ADDRESS:-noreply@example.com}"
    printf 'IOMAD_DISABLE_EMAIL=%s\n' "${IOMAD_DISABLE_EMAIL:-true}"
    printf 'IOMAD_SITE_FULLNAME=%s\n' "${IOMAD_SITE_FULLNAME:-IOMAD Learning Floci Local}"
    printf 'IOMAD_SITE_SHORTNAME=%s\n' "${IOMAD_SITE_SHORTNAME:-iomadfloci}"
    printf 'IOMAD_THEME=%s\n' "${IOMAD_THEME:-iomad_learning}"
    printf 'INSTITUTIONPACK_ALLOW_DEMO_PASSWORDS=true\n'
    printf 'INSTITUTIONPACK_DEFAULT_PASSWORD=%s\n' \
        "${INSTITUTIONPACK_DEFAULT_PASSWORD:-DemoOnly2026!}"
    printf 'TZ=%s\n' "${TZ:-Asia/Kolkata}"
} > "${RUNTIME_ENV}"
chmod 600 "${RUNTIME_ENV}"

echo "Verifying S3 and real PostgreSQL/Redis wire protocols..."
printf 'iomad-local-cloud-ok\n' \
    | aws_cli s3 cp - "s3://${FILES_BUCKET}/_health/network-loop.txt" --only-show-errors
aws_cli s3api head-object \
    --bucket "${FILES_BUCKET}" \
    --key _health/network-loop.txt \
    >/dev/null
"${DOCKER_BIN}" run --rm \
    --network "${FLOCI_NETWORK_NAME}" \
    -e PGPASSWORD="${FLOCI_DB_PASSWORD}" \
    "${FLOCI_POSTGRES_IMAGE}" \
    psql \
        -h floci \
        -p "${RDS_PORT}" \
        -U "${DB_USER}" \
        -d "${DB_NAME}" \
        -v ON_ERROR_STOP=1 \
        -tAc 'select 1' \
    | grep -qx '1'
redis_wire_ready

if [ "${START_APP}" = "true" ]; then
    echo "Starting the decoupled IOMAD PHP-FPM and Nginx services..."
    compose up -d --build --wait iomad-php web
    install_iomad_if_requested
fi

cat <<EOF
Local cloud provisioning completed.
Floci API: ${FLOCI_ENDPOINT}
Floci UI: http://localhost:${FLOCI_UI_PORT:-4500}
IOMAD: ${IOMAD_LOCAL_WWWROOT}
RDS: localhost:${RDS_PORT} (${DB_ENGINE} ${DB_ENGINE_VERSION})
Redis: localhost:${REDIS_PORT} (${REDIS_ENGINE} ${REDIS_ENGINE_VERSION})
Runtime configuration: ${RUNTIME_ENV}
Local secrets: ${SECRETS_ENV} (mode 600; values not printed)
EOF
