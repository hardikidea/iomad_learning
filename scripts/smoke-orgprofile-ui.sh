#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT_DIR}"

if [ ! -f .env ]; then
    echo ".env is missing. Run make bootstrap first."
    exit 1
fi

set -a
# shellcheck disable=SC1091
source .env
set +a

: "${IOMAD_ADMIN_USER:?IOMAD_ADMIN_USER must be set in .env}"
: "${IOMAD_ADMIN_PASSWORD:?IOMAD_ADMIN_PASSWORD must be set in .env}"

baseurl="${IOMAD_WWWROOT:-http://localhost:18080}"
workdir="$(mktemp -d)"
cookiejar="${workdir}/cookies.txt"
loginpage="${workdir}/login.html"
trap 'rm -rf "${workdir}"' EXIT

curl --fail --silent --show-error --cookie-jar "${cookiejar}" \
    "${baseurl}/login/index.php" --output "${loginpage}"
logintoken="$(sed -n 's/.*name="logintoken" value="\([^"]*\)".*/\1/p' "${loginpage}" | head -n 1)"
if [ -z "${logintoken}" ]; then
    echo "Could not obtain the Moodle login token."
    exit 1
fi

curl --fail --silent --show-error --location \
    --cookie "${cookiejar}" --cookie-jar "${cookiejar}" \
    --data-urlencode "username=${IOMAD_ADMIN_USER}" \
    --data-urlencode "password=${IOMAD_ADMIN_PASSWORD}" \
    --data-urlencode "logintoken=${logintoken}" \
    "${baseurl}/login/index.php" --output "${workdir}/authenticated.html"

if rg -q 'Invalid login|name="username"' "${workdir}/authenticated.html"; then
    echo "Moodle administrator login failed."
    exit 1
fi

pages=(
    "Site administration|/admin/search.php|Site administration"
    "Dashboard|/local/orgprofile/index.php|Configuration overview"
    "Organization types|/local/orgprofile/manage.php?entity=orgtype|Organization Types"
    "Filtered organization types|/local/orgprofile/manage.php?entity=orgtype&q=School&sort=shortname&dir=desc&perpage=10|Organization Types"
    "User types|/local/orgprofile/manage.php?entity=usertype|User Types"
    "Field library|/local/orgprofile/manage.php?entity=field|Field Library"
    "Profile forms|/local/orgprofile/manage.php?entity=form|Profile Forms"
    "Form categories|/local/orgprofile/manage.php?entity=category|Form Categories"
    "Form fields|/local/orgprofile/formfields.php|Form Fields"
    "Company mapping|/local/orgprofile/company.php|Company Mapping"
    "Sorted company mapping|/local/orgprofile/company.php?sort=assignments&dir=desc&perpage=10|Company Mapping"
    "User assignments|/local/orgprofile/assignment.php|User Type Assignment"
    "Add organization type|/local/orgprofile/edit.php?entity=orgtype|Add Organization type"
    "Create profiled company|/local/orgprofile/company_create.php|Create company with organization profile"
)

for page in "${pages[@]}"; do
    IFS='|' read -r label path expected <<< "${page}"
    output="${workdir}/page.html"
    response="$(curl --silent --show-error --location \
        --cookie "${cookiejar}" --cookie-jar "${cookiejar}" \
        --write-out '%{http_code}|%{url_effective}' --output "${output}" "${baseurl}${path}")"
    IFS='|' read -r status effectiveurl <<< "${response}"
    if [ "${status}" != "200" ]; then
        echo "${label}: HTTP ${status} at ${effectiveurl}"
        sed -n 's/.*<title>\([^<]*\)<\/title>.*/Title: \1/p' "${output}" | head -n 1
        rg -n -m 5 'alert-danger|errormessage|errorcode|stacktrace|Debug info' "${output}" || true
        exit 1
    fi
    if ! rg -q --fixed-strings "${expected}" "${output}"; then
        echo "${label}: expected page content was not rendered."
        exit 1
    fi
    if rg -q '\[\[[[:alnum:]_]+\]\]|Exception -|errorcode' "${output}"; then
        echo "${label}: unresolved language string or Moodle exception detected."
        exit 1
    fi
    echo "${label}: OK"
done

# Exercise the resolved dynamic form as well as the user-type selection page. This is a GET-only
# check: it renders step 2 but never submits or creates a user.
stepone="${workdir}/company-user-step-one.html"
mappingpage="${workdir}/company-mappings.html"
curl --fail --silent --show-error --location \
    --cookie "${cookiejar}" --cookie-jar "${cookiejar}" \
    "${baseurl}/local/orgprofile/company.php" --output "${mappingpage}"
companyid="$(sed -n 's/.*assignment\.php?companyid=\([0-9][0-9]*\).*/\1/p' "${mappingpage}" | head -n 1)"
if [ -z "${companyid}" ]; then
    echo "Create profiled user step 1: no mapped company was available."
    exit 1
fi

curl --fail --silent --show-error --location \
    --cookie "${cookiejar}" --cookie-jar "${cookiejar}" \
    "${baseurl}/local/orgprofile/company_user_create.php?companyid=${companyid}" --output "${stepone}"
if ! rg -q --fixed-strings "Select the business user type" "${stepone}"; then
    echo "Create profiled user step 1: user-type selection was not rendered."
    rg -n -m 5 'alert-danger|errormessage|errorcode|stacktrace|Debug info' "${stepone}" || true
    exit 1
fi
echo "Create profiled user step 1: OK"

usertypeid="$(sed -n '/name="usertypeid"/,/<\/select>/p' "${stepone}" \
    | sed -n 's/.*<option value="\([0-9][0-9]*\)".*/\1/p' | head -n 1)"

if [ -z "${usertypeid}" ]; then
    echo "Create profiled user step 2: no enabled user type was available."
    exit 1
fi

steptwo="${workdir}/company-user-step-two.html"
response="$(curl --silent --show-error --location \
    --cookie "${cookiejar}" --cookie-jar "${cookiejar}" \
    --write-out '%{http_code}|%{url_effective}' --output "${steptwo}" \
    "${baseurl}/local/orgprofile/company_user_create.php?companyid=${companyid}&usertypeid=${usertypeid}")"
IFS='|' read -r status effectiveurl <<< "${response}"
if [ "${status}" != "200" ]; then
    echo "Create profiled user step 2: HTTP ${status} at ${effectiveurl}"
    rg -n -m 5 'alert-danger|errormessage|errorcode|stacktrace|Debug info' "${steptwo}" || true
    exit 1
fi
if ! rg -q --fixed-strings "Moodle account details" "${steptwo}"; then
    echo "Create profiled user step 2: resolved dynamic form was not rendered."
    exit 1
fi
if rg -q '\[\[[[:alnum:]_]+\]\]|Exception -|errorcode' "${steptwo}"; then
    echo "Create profiled user step 2: unresolved language string or Moodle exception detected."
    exit 1
fi
if ! rg -q --fixed-strings "local_orgprofile/accordion" "${steptwo}"; then
    echo "Create profiled user step 2: accordion initialization was not queued."
    exit 1
fi
countryoptions="$(sed -n '/name="core_country"/,/<\/select>/p' "${steptwo}" \
    | awk '/<option / {count++} END {print count + 0}')"
if [ "${countryoptions}" -lt 2 ]; then
    echo "Create profiled user step 2: Moodle country options were not rendered."
    exit 1
fi
echo "Create profiled user step 2: OK"
