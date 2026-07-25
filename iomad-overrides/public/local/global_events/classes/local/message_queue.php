<?php
// This file is part of Moodle - http://moodle.org/

namespace local_global_events\local;

/**
 * Durable notification queue with non-personal template variables.
 *
 * @package local_global_events
 */
final class message_queue {
    /** @var string[] Allowed integer template variables. */
    private const VARIABLE_KEYS = [
        'badgeid', 'courseid', 'eventid', 'level', 'points', 'deadline', 'count',
    ];

    /**
     * Enqueue once.
     *
     * @param tenant_scope $scope Scope.
     * @param int $userid Recipient.
     * @param string $channel Channel.
     * @param string $templatekey Template.
     * @param array $variables Stable non-personal variables.
     * @param string $idempotencykey Stable source key.
     * @return object
     */
    public function enqueue(
        tenant_scope $scope,
        int $userid,
        string $channel,
        string $templatekey,
        array $variables,
        string $idempotencykey,
    ): object {
        global $DB;

        if (
            !$scope->contains_user($userid)
            || !in_array($channel, ['moodle', 'whatsapp'], true)
            || !preg_match('/^[a-z][a-z0-9_]{2,63}$/', $templatekey)
            || strlen($idempotencykey) < 8
        ) {
            throw new \invalid_parameter_exception('Invalid queued notification.');
        }
        $payload = [];
        foreach ($variables as $key => $value) {
            if (!in_array($key, self::VARIABLE_KEYS, true) || !is_int($value)) {
                throw new \invalid_parameter_exception('Unsupported notification variable.');
            }
            $payload[$key] = $value;
        }
        ksort($payload);
        $hash = hash('sha256', $idempotencykey);
        $conditions = ['companyid' => $scope->companyid(), 'idempotencykey' => $hash];
        $existing = $DB->get_record('local_ge_message', $conditions);
        if ($existing) {
            $this->require_same_payload($existing, $userid, $channel, $templatekey, $payload);
            return $existing;
        }
        $now = time();
        $record = (object)($conditions + [
            'userid' => $userid,
            'channel' => $channel,
            'templatekey' => $templatekey,
            'payloadjson' => json_encode($payload, JSON_THROW_ON_ERROR),
            'status' => 'pending',
            'attempts' => 0,
            'nextattempt' => $now,
            'lasterrorcode' => '',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        try {
            $record->id = $DB->insert_record('local_ge_message', $record);
        } catch (\dml_write_exception $exception) {
            $existing = $DB->get_record('local_ge_message', $conditions);
            if (!$existing) {
                throw $exception;
            }
            $this->require_same_payload($existing, $userid, $channel, $templatekey, $payload);
            return $existing;
        }
        return $record;
    }

    /**
     * Reject an idempotency key reused with different message content.
     *
     * @param object $existing Existing message.
     * @param int $userid Recipient.
     * @param string $channel Channel.
     * @param string $templatekey Template.
     * @param array $payload Payload.
     */
    private function require_same_payload(
        object $existing,
        int $userid,
        string $channel,
        string $templatekey,
        array $payload,
    ): void {
        if (
            (int)$existing->userid !== $userid
            || $existing->channel !== $channel
            || $existing->templatekey !== $templatekey
            || $existing->payloadjson !== json_encode($payload, JSON_THROW_ON_ERROR)
        ) {
            throw new \invalid_parameter_exception(
                'A notification key cannot be reused with different content.',
            );
        }
    }

    /**
     * Fetch ready queue records.
     *
     * @param int $limit Limit.
     * @return object[]
     */
    public function ready(int $limit = 50): array {
        global $DB;

        return array_values($DB->get_records_select(
            'local_ge_message',
            'status = :status AND nextattempt <= :now',
            ['status' => 'pending', 'now' => time()],
            'nextattempt ASC, id ASC',
            '*',
            0,
            max(1, min(100, $limit)),
        ));
    }

    /**
     * Mark sent.
     *
     * @param object $message Message.
     */
    public function sent(object $message): void {
        global $DB;

        $DB->update_record('local_ge_message', (object)[
            'id' => $message->id,
            'status' => 'sent',
            'attempts' => (int)$message->attempts + 1,
            'nextattempt' => 0,
            'lasterrorcode' => '',
            'timemodified' => time(),
        ]);
    }

    /**
     * Retry with bounded exponential backoff.
     *
     * @param object $message Message.
     * @param string $errorcode Stable non-personal code.
     */
    public function failed(object $message, string $errorcode): void {
        global $DB;

        $attempts = (int)$message->attempts + 1;
        $terminal = $attempts >= 5;
        $DB->update_record('local_ge_message', (object)[
            'id' => $message->id,
            'status' => $terminal ? 'failed' : 'pending',
            'attempts' => $attempts,
            'nextattempt' => $terminal ? 0 : time() + min(3600, 30 * (2 ** $attempts)),
            'lasterrorcode' => preg_replace('/[^a-z0-9_]/', '', strtolower($errorcode)) ?: 'delivery_failed',
            'timemodified' => time(),
        ]);
    }
}
