<?php
// This file is part of Moodle - http://moodle.org/

namespace local_global_events\local;

/**
 * HMAC, timestamp, and replay verification for incoming chat commands.
 *
 * @package local_global_events
 */
final class webhook_verifier {
    /** Maximum clock skew in seconds. */
    private const MAX_SKEW = 300;

    /**
     * Verify and decode a request.
     *
     * @param string $body Raw body.
     * @param string $timestamp Timestamp header.
     * @param string $signature sha256 signature header.
     * @return array
     */
    public function verify(string $body, string $timestamp, string $signature): array {
        $secret = getenv('IOMAD_WHATSAPP_WEBHOOK_SECRET') ?: '';
        if (
            strlen($secret) < 32
            || !ctype_digit($timestamp)
            || abs(time() - (int)$timestamp) > self::MAX_SKEW
            || !str_starts_with($signature, 'sha256=')
        ) {
            throw new \invalid_parameter_exception('Invalid webhook authentication.');
        }
        $expected = hash_hmac('sha256', $timestamp . '.' . $body, $secret);
        $provided = substr($signature, 7);
        if (strlen($provided) !== 64 || !hash_equals($expected, $provided)) {
            throw new \invalid_parameter_exception('Invalid webhook authentication.');
        }
        $payload = json_decode($body, true, 16, JSON_THROW_ON_ERROR);
        if (
            !is_array($payload)
            || (int)($payload['companyid'] ?? 0) <= 0
            || !preg_match('/^[A-Za-z0-9_.:-]{8,100}$/', (string)($payload['eventid'] ?? ''))
            || !is_string($payload['address'] ?? null)
            || !is_string($payload['command'] ?? null)
        ) {
            throw new \invalid_parameter_exception('Invalid webhook payload.');
        }
        return $payload;
    }
}
