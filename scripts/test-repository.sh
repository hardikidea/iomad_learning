#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT_DIR}"

bash -n init-local-cloud.sh scripts/*.sh docker/iomad/*.sh
./scripts/validate-plugin-compatibility.sh
./scripts/validate-commercial-reporting.sh
./scripts/validate-local-cloud.sh
./scripts/validate-observability.sh
./scripts/validate-docs.sh
./scripts/validate-xmldb.sh
./scripts/test-override-application.sh

test ! -e iomad-overrides/scripts/seed_indian_school_demo.php
if rg -n 'const DEMO_PASSWORD|Demo password for seeded users' iomad-overrides; then
    echo "Legacy hardcoded demo-password workflow detected." >&2
    exit 1
fi

if command -v docker >/dev/null 2>&1; then
    docker compose config --quiet
fi

if command -v php >/dev/null 2>&1; then
    find \
        iomad-overrides/public/local/institutionpack \
        iomad-overrides/public/local/iomadpagebuilder \
        iomad-overrides/public/local/aicoursecreator \
        iomad-overrides/public/local/tenantanalytics \
        iomad-overrides/public/local/rapidgrader \
        iomad-overrides/public/local/iomadcommerce \
        iomad-overrides/public/local/iomadconnect \
        iomad-overrides/public/local/global_events \
        iomad-overrides/public/local/iomad_h5p_bridge \
        iomad-overrides/public/local/iomad_scorm_gen \
        iomad-overrides/public/admin/tool/iomadmonitor \
        iomad-overrides/public/mod/tenantform \
        iomad-overrides/public/blocks/iomadpagebuilder \
        iomad-overrides/public/blocks/iomaddashboard \
        iomad-overrides/public/blocks/gamification_telemetry \
        iomad-overrides/public/blocks/tenantform \
        iomad-overrides/public/course/format/iomadvideo \
        iomad-overrides/public/theme/iomad_learning \
        -name '*.php' -print0 \
        | xargs -0 -n1 php -l
fi

./scripts/validate-pack-files.sh institution-packs/school/sample
./scripts/validate-pack-files.sh institution-packs/university/sample

test -s docs/feature-capability-matrix.md
grep -q '^## Capability Register$' docs/feature-capability-matrix.md
grep -q '^## Implementation Rules$' docs/feature-capability-matrix.md
test -s docs/cli-operations.md
grep -q '^## Safety Contract$' docs/cli-operations.md
grep -q '^## Tenant Isolation Audit$' docs/cli-operations.md
test -s docs/commercial-reporting-integrations.md
grep -q '^## Rejected Configuration Patterns$' docs/commercial-reporting-integrations.md
grep -q '^## Tenant Acceptance$' docs/commercial-reporting-integrations.md
test -s docs/iomad-operational-gap-assessment.md
grep -q '^## Assessment$' docs/iomad-operational-gap-assessment.md
grep -q '^## Query Security$' docs/iomad-operational-gap-assessment.md
test -s docs/local-cloud-floci.md
grep -q '^## Docker Architecture$' docs/local-cloud-floci.md
grep -q '^## Shell Provisioning$' docs/local-cloud-floci.md
grep -q '^## PHP Configuration Overrides$' docs/local-cloud-floci.md
test -s docs/product-suite-acceptance.md
grep -q '^## Workstreams$' docs/product-suite-acceptance.md
test -s docs/page-builder-catalog.md
grep -q '^## Component Library$' docs/page-builder-catalog.md
grep -q '^## Starter Templates$' docs/page-builder-catalog.md
test -s docs/commerce-wordpress.md
grep -q '^## Acceptance Boundary$' docs/commerce-wordpress.md
test -s docs/theme-customizer.md
grep -q '^## Token Catalog$' docs/theme-customizer.md
test -s docs/site-monitor.md
grep -q '^## Checks$' docs/site-monitor.md
test -s docs/adr/001-product-suite-boundaries.md
grep -q '^## Security Boundaries$' docs/adr/001-product-suite-boundaries.md
test -s docs/03-architecture/component-boundaries.md
grep -q '^## Tenant Security Invariants$' docs/03-architecture/component-boundaries.md
test -s docs/audits/documentation-inventory.json
test -s docs/audits/master-prompt-compliance.md
test -s docs/audits/prompt-conflict-report.md
test -s docs/audits/documentation-debt.md
test -s docs/11-operations/service-catalogue.md
test -s docs/11-operations/exception-catalogue.md
test -s docs/11-operations/telemetry-data-dictionary.md
test -s docker/observability/grafana/provisioning/dashboards/json/iomad-platform-overview.json
test -s .github/CODEOWNERS
test -s .github/pull_request_template.md
test -s iomad-overrides/public/local/global_events/classes/communication/gateway_interface.php
test -s iomad-overrides/public/local/global_events/classes/communication/manager.php
test -s iomad-overrides/public/local/global_events/classes/communication/chatbot.php
test -s iomad-overrides/public/local/global_events/classes/output/dashboard.php
test -s iomad-overrides/public/local/global_events/templates/event_page.mustache
test -s iomad-overrides/public/blocks/gamification_telemetry/templates/telemetry_block.mustache

echo "Repository validation passed."
