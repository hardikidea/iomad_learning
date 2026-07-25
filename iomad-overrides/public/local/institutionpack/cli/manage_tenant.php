<?php
// This file is part of IOMAD - http://www.iomad.org/

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_institutionpack\cli_support;
use local_institutionpack\tenant_manager;

[$options, $unrecognised] = cli_get_params(
    [
        'action' => 'create',
        'name' => '',
        'shortname' => '',
        'city' => '',
        'country' => '',
        'hostname' => '',
        'email-domain' => '',
        'max-users' => 0,
        'parent' => '',
        'theme' => $CFG->theme,
        'external-id' => '',
        'custom-css-file' => '',
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
        'Usage: php public/local/institutionpack/cli/manage_tenant.php ' .
        '--action=create --name="Alpha College" --shortname=alpha --city=Pune --country=IN ' .
        '--hostname=alpha.example.edu --email-domain=example.edu --max-users=250 ' .
        '--theme=iomad_learning [--parent=group] [--external-id=ERP-001] ' .
        '[--custom-css-file=/path/branding.css] [--apply]'
    );
    exit(0);
}

try {
    if ($options['action'] !== 'create') {
        throw new InvalidArgumentException('Only idempotent create is supported; use institution packs for updates.');
    }
    cli_support::require_site_admin();

    $customcss = '';
    if ($options['custom-css-file'] !== '') {
        $csspath = realpath((string)$options['custom-css-file']);
        if ($csspath === false || !is_file($csspath) || !is_readable($csspath)) {
            throw new InvalidArgumentException('Custom CSS file is not readable.');
        }
        $customcss = file_get_contents($csspath);
        if ($customcss === false) {
            throw new moodle_exception('Unable to read custom CSS file.');
        }
    }

    $input = [
        'name' => $options['name'],
        'shortname' => $options['shortname'],
        'city' => $options['city'],
        'country' => $options['country'],
        'hostname' => $options['hostname'],
        'emaildomain' => $options['email-domain'],
        'maxusers' => $options['max-users'],
        'parent' => $options['parent'],
        'theme' => $options['theme'],
        'externalid' => $options['external-id'],
        'customcss' => $customcss,
    ];
    $manager = new tenant_manager();
    $result = $options['apply'] ? $manager->apply($input) : $manager->plan($input);
    cli_support::output($result);
    exit($result['ok'] ? 0 : 1);
} catch (Throwable $exception) {
    cli_support::failure($exception);
    exit(1);
}
