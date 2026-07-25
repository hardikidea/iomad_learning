<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_tenantform\local;

/**
 * Persistence operations for immutable entries and their audit trail.
 *
 * @package    mod_tenantform
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class entry_repository {
    /** @var array Supported review states. */
    public const STATUSES = ['submitted', 'reviewing', 'approved', 'rejected'];

    /**
     * Find an idempotent submission.
     *
     * @param int $formid Form.
     * @param string $token Token.
     * @return object|null
     */
    public function find_by_token(int $formid, string $token): ?object {
        global $DB;

        $record = $DB->get_record('tenantform_entry', [
            'tenantformid' => $formid,
            'submissiontoken' => $token,
        ]);
        return $record ?: null;
    }

    /**
     * Insert an entry. Caller owns the delegated transaction.
     *
     * @param object $entry Entry.
     * @return object
     */
    public function insert(object $entry): object {
        global $DB;

        $entry->id = $DB->insert_record('tenantform_entry', $entry);
        $this->audit($entry, 'submitted', (int)$entry->userid);
        return $entry;
    }

    /**
     * Return paginated entries, newest first.
     *
     * @param int $formid Form.
     * @param int $offset Offset.
     * @param int $limit Limit.
     * @return array
     */
    public function list(int $formid, int $offset = 0, int $limit = 50): array {
        global $DB;

        return array_values($DB->get_records(
            'tenantform_entry',
            ['tenantformid' => $formid],
            'timecreated DESC, id DESC',
            '*',
            $offset,
            min(200, max(1, $limit)),
        ));
    }

    /**
     * Return all entries for export.
     *
     * @param int $formid Form.
     * @return array
     */
    public function all(int $formid): array {
        global $DB;

        return array_values($DB->get_records(
            'tenantform_entry',
            ['tenantformid' => $formid],
            'timecreated ASC, id ASC',
        ));
    }

    /**
     * Fetch an entry owned by a form.
     *
     * @param int $formid Form.
     * @param int $entryid Entry.
     * @return object
     */
    public function get(int $formid, int $entryid): object {
        global $DB;

        return $DB->get_record(
            'tenantform_entry',
            ['id' => $entryid, 'tenantformid' => $formid],
            '*',
            MUST_EXIST,
        );
    }

    /**
     * Change review state and record a non-content audit event.
     *
     * @param object $entry Entry.
     * @param string $status Status.
     * @param int $actorid Actor.
     */
    public function update_status(object $entry, string $status, int $actorid): void {
        global $DB;

        if (!in_array($status, self::STATUSES, true)) {
            throw new \invalid_parameter_exception('Unsupported entry status.');
        }
        $transaction = $DB->start_delegated_transaction();
        $DB->set_field('tenantform_entry', 'status', $status, ['id' => $entry->id]);
        $entry->status = $status;
        $this->audit($entry, 'status_' . $status, $actorid);
        $transaction->allow_commit();
    }

    /**
     * Record an audit event without entry content.
     *
     * @param object $entry Entry.
     * @param string $action Action.
     * @param int $actorid Actor.
     */
    public function audit(object $entry, string $action, int $actorid): void {
        global $DB;

        $DB->insert_record('tenantform_audit', (object)[
            'tenantformid' => $entry->tenantformid,
            'entryid' => $entry->id,
            'companyid' => $entry->companyid,
            'userid' => $actorid,
            'action' => $action,
            'timecreated' => time(),
        ]);
    }
}
