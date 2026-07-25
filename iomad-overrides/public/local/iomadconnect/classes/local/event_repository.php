<?php
// This file is part of Moodle - http://moodle.org/

namespace local_iomadconnect\local;

/**
 * Replay-safe synchronization event audit.
 *
 * @package    local_iomadconnect
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class event_repository {
    /**
     * Claim an event. False means it was already applied unchanged.
     *
     * @param int $companyid Company.
     * @param array $event Canonical event envelope.
     * @param string $payloadhash Payload hash.
     * @return bool
     */
    public function claim(int $companyid, array $event, string $payloadhash): bool {
        global $DB;

        $eventid = trim((string)($event['eventid'] ?? ''));
        $entitytype = trim((string)($event['entitytype'] ?? ''));
        $entityid = trim((string)($event['entityid'] ?? ''));
        $action = trim((string)($event['action'] ?? ''));
        $validactions = [
            'category' => ['upsert'],
            'course' => ['upsert'],
            'user' => ['upsert', 'disable'],
            'enrolment' => ['enrol', 'unenrol'],
        ];
        if (
            !preg_match('/^[A-Za-z0-9_.:-]{6,100}$/', $eventid)
            || !array_key_exists($entitytype, $validactions)
            || !preg_match('/^[A-Za-z0-9_.:@-]{3,100}$/', $entityid)
            || !in_array($action, $validactions[$entitytype] ?? [], true)
            || !preg_match('/^[a-f0-9]{64}$/', $payloadhash)
        ) {
            throw new \invalid_parameter_exception('Invalid synchronization event envelope.');
        }
        $existing = $DB->get_record('local_iomadconnect_event', [
            'companyid' => $companyid,
            'eventid' => $eventid,
        ]);
        if ($existing) {
            if (
                !hash_equals($existing->payloadhash, $payloadhash)
                || $existing->entitytype !== $entitytype
                || $existing->entityid !== $entityid
                || $existing->action !== $action
            ) {
                throw new \invalid_parameter_exception('The event ID was replayed with different data.');
            }
            if ($existing->status === 'applied') {
                return false;
            }
            $existing->attempts = (int)$existing->attempts + 1;
            $existing->status = 'pending';
            $existing->errorcode = '';
            $existing->timemodified = time();
            $DB->update_record('local_iomadconnect_event', $existing);
            return true;
        }
        $now = time();
        $DB->insert_record('local_iomadconnect_event', (object)[
            'companyid' => $companyid,
            'eventid' => $eventid,
            'direction' => 'inbound',
            'entitytype' => $entitytype,
            'entityid' => $entityid,
            'action' => $action,
            'payloadhash' => $payloadhash,
            'status' => 'pending',
            'attempts' => 1,
            'errorcode' => '',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        return true;
    }

    /**
     * Mark an event applied.
     *
     * @param int $companyid Company.
     * @param string $eventid Event.
     */
    public function complete(int $companyid, string $eventid): void {
        global $DB;

        $record = $DB->get_record('local_iomadconnect_event', [
            'companyid' => $companyid,
            'eventid' => $eventid,
        ], '*', MUST_EXIST);
        $record->status = 'applied';
        $record->errorcode = '';
        $record->timemodified = time();
        $DB->update_record('local_iomadconnect_event', $record);
    }

    /**
     * Record a non-sensitive failure code for resume.
     *
     * @param int $companyid Company.
     * @param string $eventid Event.
     * @param string $errorcode Stable code.
     */
    public function fail(int $companyid, string $eventid, string $errorcode): void {
        global $DB;

        $record = $DB->get_record('local_iomadconnect_event', [
            'companyid' => $companyid,
            'eventid' => $eventid,
        ]);
        if (!$record) {
            return;
        }
        $record->status = 'failed';
        $record->errorcode = clean_param($errorcode, PARAM_ALPHANUMEXT);
        $record->timemodified = time();
        $DB->update_record('local_iomadconnect_event', $record);
    }
}
