<?php
// This file is part of Moodle - http://moodle.org/

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognised] = cli_get_params([
    'input' => '',
    'output' => '',
    'help' => false,
], [
    'i' => 'input',
    'o' => 'output',
    'h' => 'help',
]);
if ($unrecognised) {
    cli_error('Unknown options: ' . implode(', ', $unrecognised));
}
if ($options['help'] || $options['input'] === '' || $options['output'] === '') {
    echo "Usage: php local/iomad_scorm_gen/cli/build.php --input=definition.json --output=package.zip\n";
    exit($options['help'] ? 0 : 1);
}
$inputpath = realpath($options['input']);
if (!$inputpath || !is_readable($inputpath)) {
    cli_error('The definition file is not readable.');
}
$definition = json_decode((string)file_get_contents($inputpath), true, 32, JSON_THROW_ON_ERROR);
$result = (new \local_iomad_scorm_gen\package_builder())->build($definition, $options['output']);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
