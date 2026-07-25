<?php
// This file is part of Moodle - http://moodle.org/

declare(strict_types=1);

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_tenantmaster\local\ecosystem_verifier;

[$options, $unrecognized] = cli_get_params([
    'company' => '',
    'format' => 'table',
    'max-report-ms' => 5000,
    'floci-url' => '',
    'fail-on-warning' => false,
    'help' => false,
], [
    'h' => 'help',
]);

if ($unrecognized) {
    cli_error('Unknown options: ' . implode(', ', $unrecognized));
}
if ($options['help']) {
    echo <<<HELP
Read-only IOMAD product ecosystem verification.

Options:
  --company=SHORTNAME[,SHORTNAME]  Exact IOMAD company filters; default all active tenants
  --format=table|json              Output format
  --max-report-ms=5000             Per-report performance budget, 100-60000 ms
  --floci-url=URL                  Optional live Floci health endpoint
  --fail-on-warning                Return exit 2 when warnings exist
  -h, --help

Exit codes: 0 green, 1 failed checks, 2 warnings with --fail-on-warning.

HELP;
    exit(0);
}

$format = strtolower(trim((string)$options['format']));
if (!in_array($format, ['table', 'json'], true)) {
    cli_error('--format must be table or json.');
}
$companies = preg_split('/\s*,\s*/', trim((string)$options['company']), -1, PREG_SPLIT_NO_EMPTY) ?: [];
$flociurl = trim((string)$options['floci-url']);
if ($flociurl !== '' && !filter_var($flociurl, FILTER_VALIDATE_URL)) {
    cli_error('--floci-url must be a valid URL.');
}

\core\session\manager::set_user(get_admin());
$report = (new ecosystem_verifier())->run(
    $companies,
    (int)$options['max-report-ms'],
    $flociurl
);

if ($format === 'json') {
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
} else {
    echo "IOMAD ecosystem verification (read-only)\n";
    echo str_repeat('=', 118) . "\n";
    printf(
        "%-6s %-20s %-18s %-31s %9s %11s %s\n",
        'STATE',
        'COMPANY',
        'COMPONENT',
        'CHECK',
        'TIME MS',
        'MEM BYTES',
        'METRIC'
    );
    echo str_repeat('-', 118) . "\n";
    foreach ($report['results'] as $result) {
        printf(
            "%-6s %-20.20s %-18.18s %-31.31s %9.2f %11d %s\n",
            strtoupper((string)$result['status']),
            (string)$result['company'],
            (string)$result['component'],
            (string)$result['check'],
            (float)$result['durationms'],
            (int)$result['memorybytes'],
            (string)$result['metric']
        );
        if ($result['status'] !== 'pass') {
            echo '       remediation: ' . $result['remediation'] . "\n";
        }
    }
    echo str_repeat('-', 118) . "\n";
    printf(
        "Total %d | Pass %d | Warn %d | Fail %d\n",
        $report['summary']['total'],
        $report['summary']['pass'],
        $report['summary']['warn'],
        $report['summary']['fail']
    );
}

if ($report['summary']['fail'] > 0) {
    exit(1);
}
if ($options['fail-on-warning'] && $report['summary']['warn'] > 0) {
    exit(2);
}
exit(0);
