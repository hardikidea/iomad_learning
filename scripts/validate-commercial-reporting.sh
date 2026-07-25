#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DEFAULT_MANIFEST="${ROOT_DIR}/commercial-integrations/reporting.env.example"
manifest="${1:-${DEFAULT_MANIFEST}}"

fail() {
    echo "Commercial reporting validation failed: $*" >&2
    exit 1
}

trim() {
    local value="$1"
    value="${value#"${value%%[![:space:]]*}"}"
    value="${value%"${value##*[![:space:]]}"}"
    printf '%s' "${value}"
}

file_sha256() {
    if command -v sha256sum >/dev/null 2>&1; then
        sha256sum "$1" | awk '{print $1}'
    else
        shasum -a 256 "$1" | awk '{print $1}'
    fi
}

test -f "${manifest}" || fail "manifest not found: ${manifest}"

REPORTING_PROVIDER=__MISSING__
REPORTING_PLUGIN_COMPONENT=__MISSING__
REPORTING_PLUGIN_VERSION=__MISSING__
REPORTING_ARTIFACT_PATH=__MISSING__
REPORTING_ARTIFACT_SHA256=__MISSING__
REPORTING_VENDOR_COMPATIBILITY_REFERENCE=__MISSING__
REPORTING_LICENSE_APPROVED=__MISSING__
REPORTING_DPA_APPROVED=__MISSING__
REPORTING_IOMAD_501_APPROVED=__MISSING__
REPORTING_TENANT_ISOLATION_APPROVED=__MISSING__
seen_keys="|"

while IFS= read -r rawline || [ -n "${rawline}" ]; do
    line="$(trim "${rawline%$'\r'}")"
    if [ -z "${line}" ] || [[ "${line}" == \#* ]]; then
        continue
    fi
    [[ "${line}" == *=* ]] || fail "invalid manifest line"

    key="$(trim "${line%%=*}")"
    value="$(trim "${line#*=}")"
    [[ "${key}" =~ ^[A-Z0-9_]+$ ]] || fail "invalid key: ${key}"
    case "${key}" in
        REPORTING_PROVIDER | \
            REPORTING_PLUGIN_COMPONENT | \
            REPORTING_PLUGIN_VERSION | \
            REPORTING_ARTIFACT_PATH | \
            REPORTING_ARTIFACT_SHA256 | \
            REPORTING_VENDOR_COMPATIBILITY_REFERENCE | \
            REPORTING_LICENSE_APPROVED | \
            REPORTING_DPA_APPROVED | \
            REPORTING_IOMAD_501_APPROVED | \
            REPORTING_TENANT_ISOLATION_APPROVED)
            ;;
        *)
            fail "unsupported key: ${key}"
            ;;
    esac
    [[ "${seen_keys}" != *"|${key}|"* ]] || fail "duplicate key: ${key}"
    printf -v "${key}" '%s' "${value}"
    seen_keys="${seen_keys}${key}|"
done < "${manifest}"

for key in \
    REPORTING_PROVIDER \
    REPORTING_PLUGIN_COMPONENT \
    REPORTING_PLUGIN_VERSION \
    REPORTING_ARTIFACT_PATH \
    REPORTING_ARTIFACT_SHA256 \
    REPORTING_VENDOR_COMPATIBILITY_REFERENCE \
    REPORTING_LICENSE_APPROVED \
    REPORTING_DPA_APPROVED \
    REPORTING_IOMAD_501_APPROVED \
    REPORTING_TENANT_ISOLATION_APPROVED; do
    [[ "${seen_keys}" == *"|${key}|"* ]] || fail "missing key: ${key}"
done

provider="${REPORTING_PROVIDER}"
case "${provider}" in
    none)
        for path in \
            "${ROOT_DIR}/iomad-overrides/public/local/learnerscript" \
            "${ROOT_DIR}/iomad-overrides/public/local/intelliboard"; do
            [ ! -e "${path}" ] || fail "provider is disabled but plugin code exists: ${path#"${ROOT_DIR}/"}"
        done
        echo "Commercial reporting integrations are disabled and no provider plugin is tracked."
        exit 0
        ;;
    learnerscript)
        expected_component="local_learnerscript"
        ;;
    intelliboard)
        expected_component="local_intelliboard"
        ;;
    *)
        fail "REPORTING_PROVIDER must be none, learnerscript, or intelliboard"
        ;;
esac

[ "${REPORTING_PLUGIN_COMPONENT}" = "${expected_component}" ] ||
    fail "component must be ${expected_component} for ${provider}"
[[ "${REPORTING_PLUGIN_VERSION}" =~ ^[A-Za-z0-9._+-]+$ ]] ||
    fail "a stable vendor plugin version is required"
[[ "${REPORTING_ARTIFACT_SHA256}" =~ ^[a-fA-F0-9]{64}$ ]] ||
    fail "REPORTING_ARTIFACT_SHA256 must be a SHA-256 digest"
[ -n "${REPORTING_VENDOR_COMPATIBILITY_REFERENCE}" ] ||
    fail "written IOMAD 5.1 compatibility evidence is required"

[ "${REPORTING_LICENSE_APPROVED}" = "true" ] ||
    fail "REPORTING_LICENSE_APPROVED must be true"
[ "${REPORTING_DPA_APPROVED}" = "true" ] ||
    fail "REPORTING_DPA_APPROVED must be true"
[ "${REPORTING_IOMAD_501_APPROVED}" = "true" ] ||
    fail "REPORTING_IOMAD_501_APPROVED must be true"
[ "${REPORTING_TENANT_ISOLATION_APPROVED}" = "true" ] ||
    fail "REPORTING_TENANT_ISOLATION_APPROVED must be true"

artifact_path="${REPORTING_ARTIFACT_PATH}"
case "${artifact_path}" in
    commercial-integrations/artifacts/*.zip)
        ;;
    *)
        fail "artifact must be a ZIP below commercial-integrations/artifacts/"
        ;;
esac

artifact="${ROOT_DIR}/${artifact_path}"
test -f "${artifact}" || fail "artifact not found: ${artifact_path}"
actual_sha="$(file_sha256 "${artifact}")"
expected_sha="$(printf '%s' "${REPORTING_ARTIFACT_SHA256}" | tr '[:upper:]' '[:lower:]')"
[ "${actual_sha}" = "${expected_sha}" ] ||
    fail "artifact checksum mismatch"
command -v unzip >/dev/null 2>&1 || fail "unzip is required to inspect the artifact"

has_version=false
while IFS= read -r entry; do
    [[ "${entry}" != /* ]] || fail "archive contains an absolute path"
    [[ ! "${entry}" =~ (^|/)\.\.?(/|$) ]] || fail "archive contains path traversal"
    if [[ "${entry}" =~ (^|/)version\.php$ ]]; then
        has_version=true
    fi
done < <(unzip -Z1 "${artifact}")

[ "${has_version}" = "true" ] || fail "archive does not contain version.php"

echo "Commercial reporting artifact passed the admission manifest gate."
echo "Provider: ${provider}; component: ${expected_component}; version: ${REPORTING_PLUGIN_VERSION}"
