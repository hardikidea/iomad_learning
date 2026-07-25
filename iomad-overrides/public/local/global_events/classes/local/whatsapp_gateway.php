<?php
// This file is part of Moodle - http://moodle.org/

namespace local_global_events\local;

/**
 * Optional WhatsApp-compatible template gateway.
 *
 * @package local_global_events
 */
final class whatsapp_gateway implements gateway_interface {
    /**
     * Whether a secure gateway is configured.
     *
     * @return bool
     */
    public function enabled(): bool {
        $endpoint = getenv('IOMAD_WHATSAPP_GATEWAY_URL') ?: '';
        $token = getenv('IOMAD_WHATSAPP_GATEWAY_TOKEN') ?: '';
        return str_starts_with($endpoint, 'https://') && strlen($token) >= 24;
    }

    /**
     * Deliver a provider template without logging the address or body.
     *
     * @param object $message Queue record.
     * @param array $variables Template variables.
     */
    public function deliver(object $message, array $variables): void {
        global $CFG, $DB;

        if (!$this->enabled()) {
            throw new \moodle_exception('gatewaydisabled', 'local_global_events');
        }
        $user = $DB->get_record('user', [
            'id' => $message->userid,
            'deleted' => 0,
            'suspended' => 0,
        ], 'id,phone2', MUST_EXIST);
        $address = preg_replace('/[^0-9+]/', '', (string)$user->phone2);
        if (!preg_match('/^\+[1-9][0-9]{7,14}$/', $address)) {
            throw new \moodle_exception('gatewayaddressinvalid', 'local_global_events');
        }
        require_once($CFG->libdir . '/filelib.php');
        $client = new \curl();
        $requestid = class_exists('\tool_iomadmonitor\local\correlation_id')
            ? \tool_iomadmonitor\local\correlation_id::get()
            : bin2hex(random_bytes(16));
        $spanid = bin2hex(random_bytes(8));
        $traceparent = class_exists('\tool_iomadmonitor\local\trace_context')
            ? \tool_iomadmonitor\local\trace_context::resolve()->header($spanid)
            : '00-' . bin2hex(random_bytes(16)) . '-' . $spanid . '-01';
        $client->setHeader([
            'Authorization: Bearer ' . getenv('IOMAD_WHATSAPP_GATEWAY_TOKEN'),
            'Content-Type: application/json',
            'Accept: application/json',
            'X-Request-ID: ' . $requestid,
            'traceparent: ' . $traceparent,
        ]);
        $client->setopt([
            'CURLOPT_TIMEOUT' => 5,
            'CURLOPT_CONNECTTIMEOUT' => 2,
            'CURLOPT_FOLLOWLOCATION' => false,
            'CURLOPT_MAXREDIRS' => 0,
            'CURLOPT_SSL_VERIFYPEER' => true,
            'CURLOPT_SSL_VERIFYHOST' => 2,
            'CURLOPT_PROTOCOLS' => CURLPROTO_HTTPS,
            'CURLOPT_REDIR_PROTOCOLS' => CURLPROTO_HTTPS,
            'CURLOPT_MAXFILESIZE' => 65536,
        ]);
        $response = $client->post(
            getenv('IOMAD_WHATSAPP_GATEWAY_URL'),
            json_encode([
                'to' => $address,
                'template' => $message->templatekey,
                'parameters' => $variables,
            ], JSON_THROW_ON_ERROR),
        );
        $info = $client->get_info();
        $status = (int)($info['http_code'] ?? 0);
        if ($status < 200 || $status >= 300 || strlen((string)$response) > 65536) {
            unset($response);
            throw new \moodle_exception('gatewaydeliveryfailed', 'local_global_events');
        }
        unset($response);
    }
}
