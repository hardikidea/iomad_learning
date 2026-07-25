#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TEST_ROOT="$(mktemp -d)"
trap 'rm -rf "${TEST_ROOT}"' EXIT

OVERRIDES_DIR="${TEST_ROOT}/overrides"
IOMAD_DIR="${TEST_ROOT}/iomad"
mkdir -p \
    "${OVERRIDES_DIR}/public/local/example" \
    "${IOMAD_DIR}/public/local"
git -C "${IOMAD_DIR}" init --quiet

cat > "${OVERRIDES_DIR}/.iomad-tracked-overrides" <<'EOF'
# No tracked upstream overrides in this fixture.
EOF
cat > "${OVERRIDES_DIR}/.iomad-stale-overrides" <<'EOF'
scripts/retired-project-file.php
EOF
cat > "${OVERRIDES_DIR}/public/local/example/version.php" <<'EOF'
<?php
$plugin->component = 'local_example';
EOF
cat > "${OVERRIDES_DIR}/public/local/example/current.php" <<'EOF'
<?php
EOF

"${ROOT_DIR}/scripts/apply-iomad-overrides.sh" \
    "${OVERRIDES_DIR}" \
    "${IOMAD_DIR}"

touch "${IOMAD_DIR}/public/local/example/stale.php"
mkdir -p "${IOMAD_DIR}/scripts"
touch "${IOMAD_DIR}/scripts/retired-project-file.php"

"${ROOT_DIR}/scripts/apply-iomad-overrides.sh" \
    "${OVERRIDES_DIR}" \
    "${IOMAD_DIR}"

test -f "${IOMAD_DIR}/public/local/example/current.php"
test ! -e "${IOMAD_DIR}/public/local/example/stale.php"
test ! -e "${IOMAD_DIR}/scripts/retired-project-file.php"

echo "Override application mirror validation passed."
