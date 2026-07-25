<?php
// This file is part of Moodle - http://moodle.org/

namespace local_iomadconnect\local;

/**
 * Stable external entity link repository.
 *
 * @package    local_iomadconnect
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class link_repository {
    /**
     * Create or update one stable link.
     *
     * @param int $companyid Company.
     * @param string $systemkey Source system.
     * @param string $entitytype Entity type.
     * @param string $externalid External ID.
     * @param int $localid Local record ID.
     * @param int $ownedid Owned subordinate record ID.
     * @return object
     */
    public function upsert(
        int $companyid,
        string $systemkey,
        string $entitytype,
        string $externalid,
        int $localid,
        int $ownedid = 0,
    ): object {
        global $DB;

        if (
            !preg_match('/^[A-Za-z0-9_.-]{2,40}$/', $systemkey)
            || !in_array($entitytype, ['category', 'course', 'user', 'enrolment'], true)
            || !preg_match('/^[A-Za-z0-9_.:@-]{3,100}$/', $externalid)
            || $localid <= 0
            || $ownedid < 0
        ) {
            throw new \invalid_parameter_exception('Invalid synchronization entity link.');
        }
        $conditions = [
            'companyid' => $companyid,
            'systemkey' => $systemkey,
            'entitytype' => $entitytype,
            'externalid' => $externalid,
        ];
        $existing = $DB->get_record('local_iomadconnect_link', $conditions);
        $now = time();
        if ($existing) {
            if ((int)$existing->localid !== $localid) {
                throw new \invalid_parameter_exception('An external ID cannot be remapped to another local record.');
            }
            $existing->localid = $localid;
            $existing->ownedid = $ownedid;
            $existing->timemodified = $now;
            $DB->update_record('local_iomadconnect_link', $existing);
            return $existing;
        }
        $record = (object)($conditions + [
            'localid' => $localid,
            'ownedid' => $ownedid,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $record->id = $DB->insert_record('local_iomadconnect_link', $record);
        return $record;
    }

    /**
     * Find one link.
     *
     * @param int $companyid Company.
     * @param string $systemkey System.
     * @param string $entitytype Type.
     * @param string $externalid External ID.
     * @return object|null
     */
    public function get(
        int $companyid,
        string $systemkey,
        string $entitytype,
        string $externalid,
    ): ?object {
        global $DB;

        return $DB->get_record('local_iomadconnect_link', [
            'companyid' => $companyid,
            'systemkey' => $systemkey,
            'entitytype' => $entitytype,
            'externalid' => $externalid,
        ]) ?: null;
    }
}
