#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT_DIR}"

file_count=0
failed=0
while IFS= read -r path; do
    file_count=$((file_count + 1))
    if ! XMLDB_FILE="${path}" php <<'PHP'
<?php
define('MOODLE_INTERNAL', true);

$root = getcwd();
$CFG = (object)[
    'admin' => 'admin',
    'dirroot' => realpath($root . '/iomad-overrides/public'),
];

require $root . '/iomad/public/lib/classes/xml_parser.php';
require $root . '/iomad/public/lib/xmldb/xmldb_object.php';
foreach (glob($root . '/iomad/public/lib/xmldb/*.php') as $library) {
    require_once $library;
}

$path = getenv('XMLDB_FILE');
$file = new xmldb_file($path);
if (!$file->loadXMLStructure()) {
    fwrite(STDERR, "Invalid XMLDB structure: {$path}\n");
    exit(1);
}
$errors = $file->getStructure()->getAllErrors();
if ($errors) {
    fwrite(STDERR, "XMLDB validation errors in {$path}: " . json_encode($errors) . "\n");
    exit(1);
}
PHP
    then
        failed=1
    fi
done < <(find iomad-overrides/public -path '*/db/install.xml' -type f -print | sort)

if [ "${file_count}" -eq 0 ]; then
    echo "No project XMLDB install files found." >&2
    exit 1
fi

if [ "${failed}" -ne 0 ]; then
    exit 1
fi

echo "Project XMLDB install files validated."
