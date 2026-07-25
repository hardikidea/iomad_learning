<?php
// This file is part of IOMAD - http://www.iomad.org/

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_institutionpack\cli_support;
use local_institutionpack\tenant_auditor;

[$options, $unrecognised] = cli_get_params(
    [
        'mode' => 'strict-isolation-check',
        'max-references' => 100,
        'no-report' => false,
        'help' => false,
    ],
    ['h' => 'help']
);

if ($unrecognised) {
    cli_error('Unknown options: ' . implode(', ', $unrecognised), 2);
}
if ($options['help']) {
    cli_writeln(
        'Usage: php public/local/institutionpack/cli/tenant_security_audit.php ' .
        '--mode=strict-isolation-check [--max-references=100] [--no-report]'
    );
    exit(0);
}

try {
    if ($options['mode'] !== 'strict-isolation-check') {
        throw new InvalidArgumentException('Only the read-only strict-isolation-check mode is supported.');
    }
    cli_support::require_site_admin();
    $auditor = new tenant_auditor();
    $result = $auditor->run(
        (int)$options['max-references'],
        !$options['no-report']
    );
    cli_support::output($result);
    exit($result['ok'] ? 0 : 2);
} catch (Throwable $exception) {
    cli_support::failure($exception);
    exit(1);
}
