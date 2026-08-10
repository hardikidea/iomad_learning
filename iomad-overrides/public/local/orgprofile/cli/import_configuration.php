<?php
// This file is part of Moodle - https://moodle.org/

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

$defaultfile = __DIR__ . '/../data/organization_profiles_master.csv';
[$options, $unrecognized] = cli_get_params([
    'help' => false,
    'file' => $defaultfile,
    'apply' => false,
], [
    'h' => 'help',
    'f' => 'file',
    'a' => 'apply',
]);

if ($unrecognized) {
    cli_error('Unrecognized options: ' . implode(', ', $unrecognized));
}
if ($options['help']) {
    $help = <<<HELP
Validate or import the maintained local_orgprofile configuration CSV.

Dry-run (default):
  php public/local/orgprofile/cli/import_configuration.php

Apply atomically:
  php public/local/orgprofile/cli/import_configuration.php --apply

Read a custom CSV or stdin:
  php public/local/orgprofile/cli/import_configuration.php --file=/path/config.csv --apply
  php public/local/orgprofile/cli/import_configuration.php --file=- --apply < config.csv

Options:
  -h, --help       Show this help.
  -f, --file       CSV path, or - for stdin.
  -a, --apply      Store configuration. Without this flag no database writes occur.
HELP;
    cli_writeln($help);
    exit(0);
}

try {
    $summary = (new \local_orgprofile\local\service\configuration_import_service())->import(
        (string) $options['file'],
        !empty($options['apply'])
    );
    cli_writeln('Organization profile configuration ' . $summary['mode'] . ' complete.');
    foreach (['organizations', 'usertypes', 'forms', 'fields', 'placements', 'ownershiprules'] as $key) {
        cli_writeln("  {$key}: {$summary[$key]}");
    }
    if ($summary['mode'] === 'dry-run') {
        cli_writeln('No database changes were made. Re-run with --apply to store the configuration.');
    } else {
        cli_writeln('Company mappings and user assignments were not changed. Configure them with actual IOMAD records.');
    }
} catch (\Throwable $exception) {
    cli_error($exception->getMessage());
}
