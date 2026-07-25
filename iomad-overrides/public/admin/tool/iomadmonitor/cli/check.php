<?php
// This file is part of Moodle - http://moodle.org/

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognised] = cli_get_params([
    'deep' => false,
    'output' => 'json',
    'help' => false,
], [
    'd' => 'deep',
    'h' => 'help',
]);
if ($unrecognised) {
    cli_error('Unknown options: ' . implode(', ', $unrecognised));
}
if ($options['help']) {
    echo "Usage: php admin/tool/iomadmonitor/cli/check.php [--deep] [--output=json|text]\n";
    exit(0);
}
$report = (new \tool_iomadmonitor\local\health_service())->run((bool)$options['deep']);
if ($options['output'] === 'text') {
    foreach ($report['checks'] as $check) {
        echo strtoupper($check['status']) . ' ' . $check['label'] . ': ' . $check['summary'];
        if ($check['metric'] !== null) {
            echo ' (' . $check['metric'] . ')';
        }
        echo PHP_EOL;
    }
} else if ($options['output'] === 'json') {
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
} else {
    cli_error('--output must be json or text.');
}
exit($report['ok'] ? 0 : 1);
