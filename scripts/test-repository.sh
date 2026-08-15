#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT_DIR}"

bash -n init-local-cloud.sh scripts/*.sh docker/iomad/*.sh
./scripts/test-backup-retention.sh
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
    find iomad-overrides/public -name '*.php' -print0 \
        | xargs -0 -n1 php -l
fi

./scripts/validate-pack-files.sh institution-packs/school/sample
./scripts/validate-pack-files.sh institution-packs/university/sample
./scripts/generate-demo-packs.py --check
for packtype in school university; do
    for masterfile in "institution-packs/${packtype}/master/"*.csv; do
        filename="$(basename "${masterfile}")"
        cmp "${masterfile}" "institution-packs/${packtype}/sample/${filename}"
    done
    for samplefile in "institution-packs/${packtype}/sample/"*.csv; do
        filename="$(basename "${samplefile}")"
        test -f "institution-packs/${packtype}/master/${filename}"
    done
done
test "$(tail -n +2 institution-packs/school/sample/companies.csv | wc -l | tr -d ' ')" = "1"
test "$(tail -n +2 institution-packs/university/sample/companies.csv | wc -l | tr -d ' ')" = "1"
test "$(rg -c '^SCH_STUDENT_[0-9]{3},' institution-packs/school/sample/users.csv)" = "100"
test "$(rg -c '^UNI_STU_[0-9]{3},' institution-packs/university/sample/users.csv)" = "100"
test "$(tail -n +2 institution-packs/school/sample/parent_links.csv | wc -l | tr -d ' ')" = "100"
test "$(tail -n +2 institution-packs/university/sample/parent_links.csv | wc -l | tr -d ' ')" = "100"
test -x scripts/reseed-demo-environment.sh
test -x scripts/verify-demo-environment.sh
test -x scripts/clear-demo-environment.sh
test -x scripts/provision-category-structure.sh
test -s scripts/provision-category-structure.php
test -s institution-packs/categories/moodle_iomad_category_grab_format.csv
python3 - <<'PY'
import csv

path = "institution-packs/categories/moodle_iomad_category_grab_format.csv"
expected = [
    "TOP PARENT",
    "PARENT-CATEGORY",
    "CATEGORY-NAME",
    "CATEGORY-ID-NUMBER (SHORT-CODE)",
    "DESCRIPTION",
]
with open(path, encoding="utf-8-sig", newline="") as source:
    reader = csv.DictReader(source)
    if reader.fieldnames != expected:
        raise SystemExit("Category CSV header mismatch")
    rows = list(reader)
if len(rows) != 598:
    raise SystemExit(f"Expected 598 category rows; found {len(rows)}")
shortcodes = [row[expected[3]].strip() for row in rows]
if len(shortcodes) != len(set(shortcodes)):
    raise SystemExit("Category CSV contains duplicate short codes")
if sum(not row[expected[1]].strip() for row in rows) != 1:
    raise SystemExit("Category CSV must contain exactly one root")
organizations = {row[expected[0]].strip() for row in rows}
if len(organizations) != 28:
    raise SystemExit(f"Expected 28 organization selectors; found {len(organizations)}")
anchors = [row for row in rows if row[expected[0]].strip() == row[expected[2]].strip()]
if len(anchors) != 28:
    raise SystemExit(f"Expected 28 organization anchor rows; found {len(anchors)}")
anchor_names = [row[expected[0]].strip() for row in anchors]
if len(anchor_names) != len(set(anchor_names)):
    raise SystemExit("Category CSV contains duplicate organization anchors")
department_shortnames = [
    "ORGDEP_" + row[expected[3]].strip().replace("-", "_")
    for row in anchors
    if row[expected[0]].strip() != "Organization"
]
if len(department_shortnames) != 27:
    raise SystemExit(f"Expected 27 managed departments; found {len(department_shortnames)}")
if len(department_shortnames) != len(set(department_shortnames)):
    raise SystemExit("Generated IOMAD department shortnames are not unique")
if any(len(shortname) > 32 for shortname in department_shortnames):
    raise SystemExit("Generated IOMAD department shortname exceeds 32 characters")
PY

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
test -s docs/demo-reset-and-reseed.md
grep -q '^## Resulting States$' docs/demo-reset-and-reseed.md
grep -q '^## Feature Coverage$' docs/demo-reset-and-reseed.md
test -s docs/operator-command-reference.md
grep -q '^## Moodle And IOMAD Upgrade$' docs/operator-command-reference.md
grep -q '^## Complete Fresh Local Database$' docs/operator-command-reference.md
test -s docs/category-setup.md
grep -q '^## CSV Contract$' docs/category-setup.md
grep -q '^## Apply$' docs/category-setup.md
grep -q '^## Department Contract$' docs/category-setup.md
grep -q '^### Complete `ALL` Department Map$' docs/category-setup.md
grep -q '^## Re-run and Missing-Record Recovery$' docs/category-setup.md
grep -q '^## Permissions and Access Separation$' docs/category-setup.md
grep -q '^## Company Lookup Troubleshooting$' docs/category-setup.md
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
