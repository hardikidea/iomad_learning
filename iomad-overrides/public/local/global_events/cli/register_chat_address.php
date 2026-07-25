<?php
// This file is part of Moodle - http://moodle.org/

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognised] = cli_get_params([
    'company' => '',
    'userid' => 0,
    'address-file' => '',
    'help' => false,
], [
    'c' => 'company',
    'u' => 'userid',
    'h' => 'help',
]);
if ($unrecognised) {
    cli_error('Unknown options: ' . implode(', ', $unrecognised));
}
if (
    $options['help']
    || $options['company'] === ''
    || (int)$options['userid'] <= 0
    || $options['address-file'] === ''
) {
    echo "Usage: php local/global_events/cli/register_chat_address.php "
        . "--company=SHORTNAME --userid=ID --address-file=/secure/path\n";
    exit($options['help'] ? 0 : 1);
}
$path = realpath($options['address-file']);
if (!$path || !is_readable($path)) {
    cli_error('The address file is not readable.');
}
$company = $DB->get_record(
    'local_iomad_companies',
    ['shortname' => clean_param($options['company'], PARAM_ALPHANUMEXT)],
    '*',
    MUST_EXIST,
);
(new \local_global_events\local\chat_address_repository())->register(
    \local_global_events\local\tenant_scope::system((int)$company->id),
    (int)$options['userid'],
    trim((string)file_get_contents($path)),
);
echo "Chat address registered without storing or printing the address.\n";
