#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
IOMAD_DIR="${ROOT_DIR}/iomad"
OVERRIDES_DIR="${ROOT_DIR}/iomad-overrides"
TRACKED_OVERRIDES="${OVERRIDES_DIR}/.iomad-tracked-overrides"
EXPIRY_TASK="public/local/iomad/classes/task/course_expiry_warning_task.php"
COMPOSER_LOCK="composer.lock"

fail() {
    echo "IOMAD operational baseline validation failed: $*" >&2
    exit 1
}

require_fixed() {
    local pattern="$1"
    local file="$2"
    grep -Fq -- "${pattern}" "${file}" ||
        fail "missing '${pattern}' in ${file#"${ROOT_DIR}/"}"
}

file_sha256() {
    if command -v sha256sum >/dev/null 2>&1; then
        sha256sum "$1" | awk '{print $1}'
    else
        shasum -a 256 "$1" | awk '{print $1}'
    fi
}

lock_package_version() {
    local package="$1"
    local file="$2"
    awk -v package="${package}" '
        $0 ~ "\"name\": \"" package "\"" { found = 1; next }
        found && $0 ~ /"version":/ {
            value = $0
            sub(/^.*"version": "/, "", value)
            sub(/".*$/, "", value)
            print value
            exit
        }
    ' "${file}"
}

test -d "${IOMAD_DIR}/.git" || fail "run make bootstrap first"

# Tenant-aware OIDC is native; HR lifecycle reconciliation remains external.
test -f "${IOMAD_DIR}/public/auth/iomadoidc/version.php" ||
    fail "auth_iomadoidc is missing"
require_fixed "iomad::get_my_companyid" "${IOMAD_DIR}/public/auth/iomadoidc/settings.php"
require_fixed "auth_iomadoidc_get_field_mappings" "${IOMAD_DIR}/public/auth/iomadoidc/lib.php"

# Company commerce uses Moodle payment accounts, not credential columns.
require_fixed 'FIELD NAME="paymentaccountid"' "${IOMAD_DIR}/public/local/iomad/db/install.xml"
require_fixed 'REFTABLE="payment_accounts"' "${IOMAD_DIR}/public/local/iomad/db/install.xml"
require_fixed 'core_payment\helper::get_payment_accounts_menu' \
    "${IOMAD_DIR}/public/blocks/iomad_company_admin/classes/forms/company_edit_form.php"

# These upstream indexes supersede the ad hoc SQL proposed for obsolete table names.
require_fixed 'INDEX NAME="shortname" UNIQUE="true" FIELDS="shortname"' \
    "${IOMAD_DIR}/public/local/iomad/db/install.xml"
require_fixed 'INDEX NAME="userid-companyid" UNIQUE="false" FIELDS="userid, companyid"' \
    "${IOMAD_DIR}/public/local/iomad/db/install.xml"
require_fixed 'INDEX NAME="companyid-courseid" UNIQUE="false" FIELDS="companyid, courseid"' \
    "${IOMAD_DIR}/public/local/iomad/db/install.xml"

# Tenant mail, templates, and expiry workers are native in the pinned release.
require_fixed "company_edit_smtp" "${IOMAD_DIR}/public/blocks/iomad_company_admin/db/access.php"
require_fixed "course_expiry_warning_task" "${IOMAD_DIR}/public/local/iomad/db/tasks.php"
require_fixed "manager_expiring_digest_task" "${IOMAD_DIR}/public/local/iomad/db/tasks.php"
require_fixed "company_license_expiring_task" "${IOMAD_DIR}/public/local/iomad/db/tasks.php"

# Guard the reviewed 5.1 expiry-task regression fix against upstream drift.
upstream_task="${IOMAD_DIR}/${EXPIRY_TASK}"
override_task="${OVERRIDES_DIR}/${EXPIRY_TASK}"
test -f "${override_task}" || fail "reviewed expiry-task override is missing"
upstream_sha="$(file_sha256 "${upstream_task}")"
grep -Fq "${upstream_sha} ${EXPIRY_TASK}" "${TRACKED_OVERRIDES}" ||
    fail "expiry-task upstream checksum is not recorded"
if grep -Fq "companyid = 771" "${override_task}"; then
    fail "expiry task still contains the upstream hard-coded company"
fi
require_fixed "['templatename' => 'expiry_warn_user']" "${override_task}"
require_fixed "\$DB->get_record('user'" "${override_task}"
require_fixed "\$DB->get_record('course'" "${override_task}"
require_fixed "\$templateinfo->disabledsupervisor" "${override_task}"

# The deployable development lock must not reintroduce the reviewed advisories.
upstream_lock="${IOMAD_DIR}/${COMPOSER_LOCK}"
override_lock="${OVERRIDES_DIR}/${COMPOSER_LOCK}"
test -f "${override_lock}" || fail "reviewed Composer lock override is missing"
upstream_lock_sha="$(file_sha256 "${upstream_lock}")"
grep -Fq "${upstream_lock_sha} ${COMPOSER_LOCK}" "${TRACKED_OVERRIDES}" ||
    fail "Composer lock upstream checksum is not recorded"
require_fixed '"version": "v7.4.12"' "${override_lock}"
require_fixed '"version": "v7.4.13"' "${override_lock}"
require_fixed '"version": "v1.38.1"' "${override_lock}"
require_fixed '"version": "v7.4.14"' "${override_lock}"
test "$(lock_package_version symfony/dom-crawler "${override_lock}")" = "v7.4.12" ||
    fail "Composer lock contains an unexpected Symfony DomCrawler release"
test "$(lock_package_version symfony/mime "${override_lock}")" = "v7.4.13" ||
    fail "Composer lock contains an unexpected Symfony Mime release"
test "$(lock_package_version symfony/polyfill-intl-idn "${override_lock}")" = "v1.38.1" ||
    fail "Composer lock contains an unexpected Symfony IDN polyfill release"
test "$(lock_package_version symfony/yaml "${override_lock}")" = "v7.4.14" ||
    fail "Composer lock contains an unexpected Symfony YAML release"

echo "IOMAD operational baseline validated against the pinned source."
