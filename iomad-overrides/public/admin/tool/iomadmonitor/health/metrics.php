<?php
// This file is part of Moodle - http://moodle.org/

// Metrics authentication must happen before Moodle bootstrap.
// phpcs:disable moodle.Files.MoodleInternal.MoodleInternalGlobalState
define('NO_DEBUG_DISPLAY', true);

$tokenfile = getenv('IOMAD_MONITOR_METRICS_TOKEN_FILE') ?: '';
$expected = getenv('IOMAD_MONITOR_METRICS_TOKEN') ?: '';
if ($expected === '' && $tokenfile !== '' && is_readable($tokenfile)) {
    $expected = trim((string)file_get_contents($tokenfile));
}
$authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$provided = str_starts_with($authorization, 'Bearer ') ? substr($authorization, 7) : '';
if (
    strlen($expected) < 24
    || strlen($provided) !== strlen($expected)
    || !hash_equals($expected, $provided)
) {
    http_response_code(404);
    exit;
}

try {
    require(__DIR__ . '/../../../../../config.php');
    header('Content-Type: text/plain; version=0.0.4; charset=utf-8');
    header('Cache-Control: no-store');
    echo (new \tool_iomadmonitor\local\metrics_renderer())->render(
        (new \tool_iomadmonitor\local\health_service())->run(false),
    );
} catch (\Throwable $exception) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo "# monitor unavailable\n";
}
