<?php
// This file is part of Moodle - http://moodle.org/

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_tenantanalytics\local\exporter;
use local_tenantanalytics\local\report_catalog;
use local_tenantanalytics\local\report_engine;
use local_tenantanalytics\local\tenant_scope;
use local_tenantanalytics\task\deliver_scheduled_reports;

[$options, $unrecognized] = cli_get_params([
    'mode' => 'doctor',
    'company' => '',
    'report' => 'course_engagement',
    'format' => 'csv',
    'since' => 0,
    'until' => 0,
    'course' => 0,
    'cohort' => 0,
    'group' => 0,
    'output' => '',
    'help' => false,
], [
    'h' => 'help',
]);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error("Unknown options:\n  {$unrecognized}");
}
if ($options['help']) {
    echo <<<HELP
Tenant analytics CLI

Options:
  --mode=doctor|catalog|run|deliver
  --company=SHORTNAME
  --report=KEY
  --format=csv|excel|ods|pdf
  --since=UNIX_TIMESTAMP
  --until=UNIX_TIMESTAMP
  --course=ID --cohort=ID --group=ID
  --output=/absolute/directory
  -h, --help

HELP;
    exit(0);
}

$mode = (string)$options['mode'];
if ($mode === 'doctor') {
    $checks = [
        'reports' => count(report_catalog::all()),
        'formats' => array_keys(report_catalog::formats()),
        'standard_log' => $DB->get_manager()->table_exists('logstore_standard_log'),
        'iomad_tracks' => $DB->get_manager()->table_exists('local_iomad_tracks'),
        'schedules' => $DB->get_manager()->table_exists('local_tanalytics_schedule'),
    ];
    echo json_encode($checks, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(in_array(false, $checks, true) ? 1 : 0);
}
if ($mode === 'catalog') {
    echo json_encode(report_catalog::all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
}
if ($mode === 'deliver') {
    (new deliver_scheduled_reports())->execute();
    exit(0);
}
if ($mode !== 'run') {
    cli_error('Mode must be doctor, catalog, run, or deliver.');
}

$company = $DB->get_record(
    'local_iomad_companies',
    ['shortname' => trim((string)$options['company'])],
);
if (!$company) {
    cli_error('--company must identify an existing IOMAD company.');
}
$companyid = (int)$company->id;
$reportkey = (string)$options['report'];
$format = (string)$options['format'];
$now = time();
$filters = [
    'since' => (int)$options['since'] ?: $now - (30 * DAYSECS),
    'until' => (int)$options['until'] ?: $now,
    'courseid' => (int)$options['course'],
    'cohortid' => (int)$options['cohort'],
    'groupid' => (int)$options['group'],
];
$result = (new report_engine())->generate(
    $reportkey,
    new tenant_scope($companyid, (int)get_admin()->id, false),
    $filters
);
$filepath = (new exporter())->write(
    'tenant-' . $reportkey . '-' . gmdate('Ymd-His'),
    $format,
    $result
);
if ($options['output']) {
    $outputdir = rtrim((string)$options['output'], DIRECTORY_SEPARATOR);
    if (!is_dir($outputdir) || !is_writable($outputdir)) {
        cli_error('--output must be an existing writable directory.');
    }
    $target = $outputdir . DIRECTORY_SEPARATOR . basename($filepath);
    if (!copy($filepath, $target)) {
        cli_error('Could not copy the generated report.');
    }
    unlink($filepath);
    $filepath = $target;
}
echo json_encode([
    'report' => $reportkey,
    'company' => $company->shortname,
    'rows' => count($result->get_rows()),
    'report_checksum' => $result->get_checksum(),
    'file_sha256' => hash_file('sha256', $filepath),
    'file' => $filepath,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
