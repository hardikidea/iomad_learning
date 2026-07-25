<?php
// This file is part of Moodle - http://moodle.org/

namespace local_global_events\local;

/**
 * Durable webhook replay claims.
 *
 * @package local_global_events
 */
final class webhook_claim_repository {
    /**
     * Claim an event once.
     *
     * @param int $companyid Company.
     * @param string $eventkey Provider event ID.
     * @param string $body Signed body.
     * @return bool True for a new claim.
     */
    public function claim(int $companyid, string $eventkey, string $body): bool {
        global $DB;

        $hash = hash('sha256', $body);
        $conditions = ['companyid' => $companyid, 'eventkey' => $eventkey];
        $existing = $DB->get_record('local_ge_webhook', $conditions);
        if ($existing) {
            if (!hash_equals((string)$existing->payloadhash, $hash)) {
                throw new \invalid_parameter_exception('Webhook event identity was reused.');
            }
            return false;
        }
        try {
            $DB->insert_record('local_ge_webhook', (object)($conditions + [
                'payloadhash' => $hash,
                'timecreated' => time(),
            ]));
        } catch (\dml_write_exception $exception) {
            $existing = $DB->get_record('local_ge_webhook', $conditions);
            if (!$existing) {
                throw $exception;
            }
            if (!hash_equals((string)$existing->payloadhash, $hash)) {
                throw new \invalid_parameter_exception('Webhook event identity was reused.');
            }
            return false;
        }
        return true;
    }
}
