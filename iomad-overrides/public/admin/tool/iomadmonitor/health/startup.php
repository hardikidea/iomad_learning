<?php
// This file is part of Moodle - http://moodle.org/

// Bootstrap-safe health endpoint must control output before loading Moodle.
// phpcs:disable moodle.Files.MoodleInternal.MoodleInternalGlobalState
define('NO_DEBUG_DISPLAY', true);

header('Content-Type: application/json');
header('Cache-Control: no-store');

try {
    require(__DIR__ . '/../../../../../config.php');
    $requestid = \tool_iomadmonitor\local\correlation_id::get();
    header('X-Request-ID: ' . $requestid);
    $report = (new \tool_iomadmonitor\local\health_service())->startup();
    http_response_code($report['ok'] ? 200 : 503);
    echo json_encode([
        'status' => $report['status'],
        'contract' => $report['contract'],
        'request_id' => $requestid,
    ], JSON_THROW_ON_ERROR);
} catch (\Throwable $exception) {
    $requestid = class_exists('\tool_iomadmonitor\local\correlation_id')
        ? \tool_iomadmonitor\local\correlation_id::get()
        : bin2hex(random_bytes(16));
    header('X-Request-ID: ' . $requestid);
    http_response_code(503);
    echo json_encode([
        'status' => 'unhealthy',
        'contract' => 'startup',
        'request_id' => $requestid,
    ]);
}
