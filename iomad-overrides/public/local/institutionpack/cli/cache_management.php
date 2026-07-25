<?php
// This file is part of IOMAD - http://www.iomad.org/

define('CLI_SCRIPT', true);
define('IGNORE_COMPONENT_CACHE', true);

require(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_institutionpack\cache_manager;
use local_institutionpack\cli_support;

[$options, $unrecognised] = cli_get_params(
    [
        'scope' => 'all',
        'theme' => 'iomad_learning',
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
        'Usage: php public/local/institutionpack/cli/cache_management.php ' .
        '--scope=all|theme --theme=iomad_learning [--apply]'
    );
    exit(0);
}

try {
    cli_support::require_site_admin();
    $manager = new cache_manager();
    $result = $options['apply']
        ? $manager->apply((string)$options['scope'], (string)$options['theme'])
        : $manager->plan((string)$options['scope'], (string)$options['theme']);
    cli_support::output($result);
    exit(0);
} catch (Throwable $exception) {
    cli_support::failure($exception);
    exit(1);
}
