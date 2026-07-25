<?php
// This file is part of Moodle - http://moodle.org/

// Webhook size enforcement must happen before Moodle bootstrap.
// phpcs:disable moodle.Files.MoodleInternal.MoodleInternalGlobalState
define('NO_DEBUG_DISPLAY', true);

if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 32768) {
    http_response_code(413);
    exit;
}

require(__DIR__ . '/../../../config.php');

header('Content-Type: application/json');
header('Cache-Control: no-store');

try {
    $body = (string)file_get_contents('php://input');
    $payload = (new \local_global_events\local\webhook_verifier())->verify(
        $body,
        (string)($_SERVER['HTTP_X_IOMAD_TIMESTAMP'] ?? ''),
        (string)($_SERVER['HTTP_X_IOMAD_SIGNATURE'] ?? ''),
    );
    $companyid = (int)$payload['companyid'];
    $eventkey = (string)$payload['eventid'];
    $chat = (new \local_global_events\local\chat_address_repository())->resolve(
        $companyid,
        (string)$payload['address'],
    );
    $scope = \local_global_events\local\tenant_scope::verified_membership(
        $companyid,
        (int)$chat->userid,
    );
    $plan = (new \local_global_events\communication\chatbot())->plan(
        $scope,
        (int)$chat->userid,
        (string)$payload['command'],
    );
    (new \local_global_events\local\message_queue())->enqueue(
        $scope,
        (int)$chat->userid,
        'whatsapp',
        $plan['template'],
        $plan['variables'],
        'webhook-response:' . $eventkey,
    );
    (new \local_global_events\local\webhook_claim_repository())->claim($companyid, $eventkey, $body);
    http_response_code(202);
    echo json_encode(['status' => 'accepted']);
} catch (\Throwable) {
    http_response_code(400);
    echo json_encode(['status' => 'rejected']);
}
