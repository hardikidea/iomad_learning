<?php
// This file is part of IOMAD - http://www.iomad.org/

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_institutionpack\cli_support;
use local_institutionpack\license_manager;

[$options, $unrecognised] = cli_get_params(
    [
        'action' => 'allocate',
        'company' => '',
        'course-idnumber' => '',
        'course-shortname' => '',
        'seats' => 0,
        'reference' => '',
        'name' => '',
        'start' => '',
        'expiry' => '',
        'valid-days' => 0,
        'type' => 0,
        'instant' => false,
        'clear-on-expire' => false,
        'apply' => false,
        'help' => false,
    ],
    ['h' => 'help']
);

if ($unrecognised) {
    cli_error('Unknown options: ' . implode(', ', $unrecognised), 2);
}
if ($options['help']) {
    cli_writeln(
        'Usage: php public/local/institutionpack/cli/manage_licenses.php ' .
        '--action=allocate --company=alpha --course-idnumber=COURSE-001 ' .
        '(or --course-shortname=COURSE-001) --seats=50 ' .
        '--reference=ORDER-998231 --start=2026-07-24 --expiry=2027-07-24 ' .
        '[--valid-days=365] [--type=0] [--instant] [--clear-on-expire] [--apply]'
    );
    exit(0);
}

try {
    if ($options['action'] !== 'allocate') {
        throw new InvalidArgumentException('Only additive, reference-addressed allocation is supported.');
    }
    cli_support::require_site_admin();
    $input = [
        'company' => $options['company'],
        'courseidnumber' => $options['course-idnumber'],
        'courseshortname' => $options['course-shortname'],
        'allocation' => $options['seats'],
        'reference' => $options['reference'],
        'name' => $options['name'],
        'startdate' => $options['start'],
        'expirydate' => $options['expiry'],
        'validlength' => $options['valid-days'],
        'type' => $options['type'],
        'instant' => $options['instant'],
        'clearonexpire' => $options['clear-on-expire'],
    ];
    $manager = new license_manager();
    $result = $options['apply'] ? $manager->apply($input) : $manager->plan($input);
    cli_support::output($result);
    exit($result['ok'] ? 0 : 1);
} catch (Throwable $exception) {
    cli_support::failure($exception);
    exit(1);
}
