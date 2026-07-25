<?php
// This file is part of IOMAD - http://www.iomad.org/

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_institutionpack\block_manager;
use local_institutionpack\cli_support;

[$options, $unrecognised] = cli_get_params(
    [
        'action' => 'list',
        'blockname' => '',
        'page' => 'site-index',
        'region' => 'content',
        'weight' => 0,
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
        'Usage: php public/local/institutionpack/cli/manage_blocks.php ' .
        '--action=list | --action=inject --blockname=iomad_html ' .
        '--page=site-index --region=content --weight=-10 [--apply]'
    );
    exit(0);
}

try {
    cli_support::require_site_admin();
    $manager = new block_manager();
    if ($options['action'] === 'list') {
        $result = $manager->listing();
    } else if ($options['action'] === 'inject') {
        $input = [
            'blockname' => $options['blockname'],
            'page' => $options['page'],
            'region' => $options['region'],
            'weight' => $options['weight'],
        ];
        $result = $options['apply'] ? $manager->apply($input) : $manager->plan($input);
    } else {
        throw new InvalidArgumentException('Block action must be list or inject.');
    }
    cli_support::output($result);
    exit($result['ok'] ? 0 : 1);
} catch (Throwable $exception) {
    cli_support::failure($exception);
    exit(1);
}
