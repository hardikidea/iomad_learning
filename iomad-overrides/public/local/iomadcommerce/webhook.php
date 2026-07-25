<?php
// This file is part of Moodle - http://moodle.org/

define('NO_DEBUG_DISPLAY', true);

require_once(__DIR__ . '/../../config.php');

header('Content-Type: application/json; charset=utf-8');

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        throw new invalid_parameter_exception('POST is required.');
    }
    $rawbody = (string)file_get_contents('php://input');
    $payload = json_decode($rawbody, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($payload)) {
        throw new invalid_parameter_exception('A JSON object is required.');
    }
    $companyshortname = trim((string)($payload['company'] ?? ''));
    $timestamp = clean_param($_SERVER['HTTP_X_IOMAD_TIMESTAMP'] ?? '', PARAM_INT);
    $nonce = clean_param($_SERVER['HTTP_X_IOMAD_NONCE'] ?? '', PARAM_ALPHANUMEXT);
    $signature = clean_param($_SERVER['HTTP_X_IOMAD_SIGNATURE'] ?? '', PARAM_ALPHANUM);
    $verifier = new \local_iomadcommerce\local\webhook_verifier();
    $verifier->verify(
        $rawbody,
        (int)$timestamp,
        $nonce,
        $signature,
        $verifier->secret_for($companyshortname),
    );
    $company = $DB->get_record(
        'local_iomad_companies',
        ['shortname' => $companyshortname],
        '*',
        MUST_EXIST,
    );
    $scope = new \local_iomadcommerce\local\tenant_scope((int)$company->id);
    $orders = new \local_iomadcommerce\local\order_service();
    $orderexternalid = trim((string)($payload['order'] ?? ''));
    $eventid = trim((string)($payload['event_id'] ?? ''));
    if (!preg_match('/^[A-Za-z0-9_.:-]{6,92}$/', $eventid)) {
        throw new invalid_parameter_exception('A stable event_id is required.');
    }
    $status = trim((string)($payload['status'] ?? ''));
    $payloadhash = hash('sha256', $rawbody);
    if ($status === 'paid') {
        $product = (new \local_iomadcommerce\local\product_repository())->get(
            $scope->companyid(),
            trim((string)($payload['product'] ?? '')),
        );
        if ($product->status !== 'paid') {
            throw new invalid_parameter_exception('Payment callbacks require a paid product.');
        }
        $user = $DB->get_record('user', [
            'idnumber' => trim((string)($payload['user_idnumber'] ?? '')),
            'deleted' => 0,
            'suspended' => 0,
        ], 'id', MUST_EXIST);
        [$order] = $orders->create(
            $scope,
            $product,
            (int)$user->id,
            $orderexternalid,
            (int)($payload['quantity'] ?? 1),
            'webhook',
        );
        [$order, $action] = $orders->transition(
            $scope,
            $orderexternalid,
            'paid',
            'webhook:' . $eventid,
            $payloadhash,
        );
        if ((int)($payload['quantity'] ?? 1) === 1) {
            $orders->assign($scope, $orderexternalid, (int)$user->id, 0);
        }
    } else if (in_array($status, ['refunded', 'cancelled'], true)) {
        [$order, $action] = $orders->transition(
            $scope,
            $orderexternalid,
            $status,
            'webhook:' . $eventid,
            $payloadhash,
        );
    } else {
        throw new invalid_parameter_exception('Unsupported webhook status.');
    }
    echo json_encode([
        'ok' => true,
        'action' => $action,
        'order' => $order->externalid,
        'status' => $order->status,
    ], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    $clienterror = $exception instanceof moodle_exception || $exception instanceof JsonException;
    http_response_code($clienterror ? 400 : 500);
    echo json_encode([
        'ok' => false,
        'error' => $exception instanceof moodle_exception ? $exception->errorcode
            : ($exception instanceof JsonException ? 'invalid_json' : 'internal_error'),
    ], JSON_THROW_ON_ERROR);
}
