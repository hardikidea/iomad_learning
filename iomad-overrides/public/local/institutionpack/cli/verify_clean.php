<?php
// This file is part of IOMAD - http://www.iomad.org/

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');

$checks = [];
foreach (
    [
        'local_iomad_companies' => 'companies',
        'local_iomad_company_users' => 'company user mappings',
        'local_iomad_company_courses' => 'company course mappings',
        'local_iomad_company_departments' => 'company departments',
    ] as $table => $label
) {
    $actual = (int)$DB->count_records($table);
    $checks[] = [
        'key' => $table,
        'label' => $label,
        'actual' => $actual,
        'expected' => 0,
        'ok' => $actual === 0,
    ];
}

$ok = !array_filter($checks, static fn(array $check): bool => !$check['ok']);
cli_writeln(json_encode(
    [
        'ok' => $ok,
        'state' => 'clean_iomad_defaults',
        'checks' => $checks,
    ],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
));
exit($ok ? 0 : 1);
