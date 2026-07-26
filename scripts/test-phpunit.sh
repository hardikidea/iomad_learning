#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT_DIR}"

PHPUNIT_DATAROOT="${IOMAD_PHPUNIT_DATAROOT:-/var/www/phpunitdata}"
PHPUNIT_DB_PREFIX="${IOMAD_PHPUNIT_DB_PREFIX:-phpu_}"
PHPUNIT_JUNIT_PATH="${PHPUNIT_JUNIT_PATH:-}"
DEPENDENCIES_CHANGED=false
PHPUNIT_INITIALIZED=false

restore_production_dependencies() {
    if [ "${PHPUNIT_INITIALIZED}" = "true" ]; then
        docker compose exec -T \
            -e IOMAD_PHPUNIT_DATAROOT="${PHPUNIT_DATAROOT}" \
            -e IOMAD_PHPUNIT_DB_PREFIX="${PHPUNIT_DB_PREFIX}" \
            iomad php public/admin/tool/phpunit/cli/util.php --drop >/dev/null 2>&1 || true
    fi
    docker compose exec -T iomad rm -f composer.phar >/dev/null 2>&1 || true
    if [ "${DEPENDENCIES_CHANGED}" = "true" ]; then
        docker compose exec -T iomad \
            composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader >/dev/null || true
    fi
}
trap restore_production_dependencies EXIT

DEPENDENCIES_CHANGED=true
docker compose exec -T iomad \
    composer install --no-interaction --prefer-dist --optimize-autoloader

docker compose exec -T iomad ln -sfn /usr/local/bin/composer composer.phar

docker compose exec -T \
    -e IOMAD_PHPUNIT_DATAROOT="${PHPUNIT_DATAROOT}" \
    -e IOMAD_PHPUNIT_DB_PREFIX="${PHPUNIT_DB_PREFIX}" \
    iomad php public/admin/tool/phpunit/cli/init.php --disable-composer
PHPUNIT_INITIALIZED=true

phpunit_args=(
    --configuration phpunit.xml.dist
    --colors=never
    --fail-on-empty-test-suite
    --fail-on-notice
    --fail-on-warning
)

if [ -n "${PHPUNIT_JUNIT_PATH}" ]; then
    phpunit_args+=(--log-junit "${PHPUNIT_JUNIT_PATH}")
fi

test_targets=(
    public/local/institutionpack/tests/tenant_isolation_test.php
    public/local/iomadpagebuilder/tests/catalog_test.php
    public/local/iomadpagebuilder/tests/page_repository_test.php
    public/local/aicoursecreator/tests/course_schema_validator_test.php
    public/local/aicoursecreator/tests/quota_service_test.php
    public/local/aicoursecreator/tests/draft_repository_test.php
    public/local/aicoursecreator/tests/scorm_exporter_test.php
    public/local/aicoursecreator/tests/course_publisher_test.php
    public/course/format/iomadvideo/tests/format_test.php
    public/course/format/iomadvideo/tests/playlist_service_test.php
    public/blocks/iomaddashboard/tests/widget_catalog_test.php
    public/blocks/iomaddashboard/tests/todo_repository_test.php
    public/blocks/iomaddashboard/tests/tenant_scope_test.php
    public/local/tenantanalytics/tests/catalog_sessionizer_test.php
    public/local/tenantanalytics/tests/tenant_scope_test.php
    public/local/tenantanalytics/tests/report_engine_test.php
    public/local/tenantanalytics/tests/schedule_repository_test.php
    public/local/rapidgrader/tests/course_scope_test.php
    public/local/rapidgrader/tests/gradebook_service_test.php
    public/local/rapidgrader/tests/exporter_test.php
    public/local/iomadcommerce/tests/webhook_verifier_test.php
    public/local/iomadcommerce/tests/product_repository_test.php
    public/local/iomadcommerce/tests/order_service_test.php
    public/local/iomadcommerce/tests/privacy_provider_test.php
    public/local/iomadconnect/tests/event_repository_test.php
    public/local/iomadconnect/tests/catalogue_exporter_test.php
    public/local/iomadconnect/tests/sync_service_test.php
    public/local/iomadconnect/tests/privacy_provider_test.php
    public/admin/tool/iomadmonitor/tests/health_service_test.php
    public/admin/tool/iomadmonitor/tests/service_registry_test.php
    public/admin/tool/iomadmonitor/tests/operability_contract_test.php
    public/local/global_events/tests/gamification_service_test.php
    public/local/global_events/tests/event_repository_test.php
    public/local/global_events/tests/dashboard_service_test.php
    public/local/global_events/tests/messaging_security_test.php
    public/local/iomad_h5p_bridge/tests/observer_test.php
    public/local/iomad_scorm_gen/tests/package_builder_test.php
    public/local/iomad_scorm_gen/tests/observer_test.php
    public/theme/iomad_learning/tests/token_catalog_test.php
    public/theme/iomad_learning/tests/tenant_branding_test.php
    public/theme/iomad_learning/tests/icon_catalog_test.php
    public/local/tenantmaster/tests/json_test.php
    public/local/tenantmaster/tests/default_service_test.php
    public/local/tenantmaster/tests/projection_test.php
    public/local/tenantmaster/tests/isolation_test.php
    public/local/tenantmaster/tests/native_user_test.php
    public/local/tenantmaster/tests/role_service_test.php
    public/local/tenantmaster/tests/import_service_test.php
    public/local/tenantmaster/tests/crud_integration_test.php
    public/local/tenantmaster/tests/lifecycle_test.php
    public/local/tenantmaster/tests/school_management_test.php
    public/mod/tenantform/tests/template_validator_test.php
    public/mod/tenantform/tests/submission_service_test.php
    public/mod/tenantform/tests/entry_repository_test.php
    public/mod/tenantform/tests/tenant_access_test.php
)

if [ "$#" -gt 0 ]; then
    test_targets=("$@")
fi

for test_target in "${test_targets[@]}"; do
    docker compose exec -T \
        -e IOMAD_PHPUNIT_DATAROOT="${PHPUNIT_DATAROOT}" \
        -e IOMAD_PHPUNIT_DB_PREFIX="${PHPUNIT_DB_PREFIX}" \
        iomad php vendor/bin/phpunit \
            "${phpunit_args[@]}" \
            "${test_target}"
done

trap - EXIT
restore_production_dependencies
echo "Project-owned IOMAD plugin PHPUnit tests passed."
