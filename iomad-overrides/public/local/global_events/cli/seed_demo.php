<?php
// This file is part of Moodle - http://moodle.org/

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognised] = cli_get_params([
    'company' => '',
    'course' => '',
    'help' => false,
], [
    'c' => 'company',
    'h' => 'help',
]);
if ($unrecognised) {
    cli_error('Unknown options: ' . implode(', ', $unrecognised));
}
if ($options['help'] || $options['company'] === '') {
    echo "Usage: php local/global_events/cli/seed_demo.php --company=SHORTNAME [--course=SHORTNAME]\n";
    exit($options['help'] ? 0 : 1);
}

$company = $DB->get_record(
    'local_iomad_companies',
    ['shortname' => clean_param($options['company'], PARAM_ALPHANUMEXT)],
    '*',
    MUST_EXIST,
);
$courseid = 0;
if ($options['course'] !== '') {
    $courseid = (int)$DB->get_field(
        'course',
        'id',
        ['shortname' => clean_param($options['course'], PARAM_TEXT)],
        MUST_EXIST,
    );
}
$scope = \local_global_events\local\tenant_scope::system((int)$company->id);
$levels = new \local_global_events\local\level_repository();
foreach (
    [
        [1, 'Starter', 0],
        [2, 'Explorer', 100],
        [3, 'Achiever', 300],
        [4, 'Mentor', 750],
    ] as [$number, $name, $points]
) {
    $levels->upsert($scope, $number, $name, $points);
}
$event = (new \local_global_events\local\event_repository())->upsert($scope, [
    'idnumber' => 'demo:' . $company->shortname . ':orientation',
    'name' => 'Institution orientation',
    'description' => 'Sanitized demonstration event for local acceptance testing.',
    'courseid' => $courseid,
    'visibility' => 'companies',
    'status' => 'published',
    'timestart' => 0,
    'timeend' => 0,
], [(int)$company->id], (int)get_admin()->id);

echo json_encode([
    'status' => 'ready',
    'company' => $company->shortname,
    'event' => $event->idnumber,
    'levels' => 4,
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
