<?php
// This file is part of IOMAD - http://www.iomad.org/

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_institutionpack\importer;
use local_institutionpack\pack;
use local_institutionpack\planner;
use local_institutionpack\validator;

[$options, $unrecognized] = cli_get_params(
    [
        'mode' => 'doctor',
        'pack' => '',
        'format' => 'pretty',
        'help' => false,
    ],
    [
        'm' => 'mode',
        'p' => 'pack',
        'h' => 'help',
    ]
);

if ($options['help']) {
    cli_writeln('Usage: php public/local/institutionpack/cli/institutionpack.php --mode=doctor|validate|plan|dry-run|apply|resume|report --pack=/path/to/pack');
    exit(0);
}

$mode = (string)$options['mode'];
$packpath = (string)$options['pack'];

try {
    if ($mode === 'report') {
        output_result(importer::latest_report(), $options['format']);
        exit(0);
    }

    if ($packpath === '') {
        $packpath = getenv('INSTITUTIONPACK_DEFAULT_PACK') ?: '';
    }
    if ($packpath === '') {
        throw new moodle_exception('Pass --pack=/path/to/institution-pack or set INSTITUTIONPACK_DEFAULT_PACK.');
    }
    if (!str_starts_with($packpath, DIRECTORY_SEPARATOR)) {
        $candidate = $CFG->dirroot . DIRECTORY_SEPARATOR . $packpath;
        if (!is_dir($candidate)) {
            $candidate = dirname($CFG->dirroot) . DIRECTORY_SEPARATOR . $packpath;
        }
        $packpath = $candidate;
    }

    $pack = new pack($packpath);

    $result = match ($mode) {
        'doctor' => (new importer($pack))->doctor(),
        'validate' => (new validator($pack))->validate(),
        'plan' => (new planner($pack))->plan(),
        'dry-run' => (new importer($pack))->apply(true),
        'apply', 'resume' => (new importer($pack))->apply(false),
        default => throw new moodle_exception('Unknown institution pack mode: ' . $mode),
    };

    output_result($result, $options['format']);
    if (isset($result['ok']) && !$result['ok']) {
        exit(1);
    }
} catch (Throwable $exception) {
    output_result([
        'ok' => false,
        'error' => $exception->getMessage(),
    ], $options['format']);
    exit(1);
}

function output_result($result, string $format): void {
    if ($format === 'json') {
        cli_writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return;
    }
    if ($result === null) {
        cli_writeln('No report found.');
        return;
    }
    cli_writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}
