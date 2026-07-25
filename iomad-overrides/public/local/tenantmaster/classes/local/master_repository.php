<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\local;

/**
 * Tenant-owned academic master repository.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class master_repository {
    /**
     * Get a tenant-scoped master record.
     *
     * @param int $tenantid Tenant ID.
     * @param int $id Record ID.
     * @return object
     */
    public function get(int $tenantid, int $id): object {
        global $DB;
        return $DB->get_record(
            'local_tenantmaster_master',
            ['id' => $id, 'tenantid' => $tenantid],
            '*',
            MUST_EXIST,
        );
    }

    /**
     * List masters.
     *
     * @param int $tenantid Tenant ID.
     * @param string $mastertype Optional type.
     * @param bool|null $active Optional active state.
     * @return array<int, object>
     */
    public function list(int $tenantid, string $mastertype = '', ?bool $active = null): array {
        global $DB;

        $conditions = ['tenantid' => $tenantid];
        if ($mastertype !== '') {
            $conditions['mastertype'] = $mastertype;
        }
        if ($active !== null) {
            $conditions['active'] = (int)$active;
        }
        return $DB->get_records(
            'local_tenantmaster_master',
            $conditions,
            'mastertype ASC, sortorder ASC, name ASC',
        );
    }

    /**
     * Save a master record.
     *
     * @param object $record Record.
     * @return object
     */
    public function save(object $record): object {
        global $DB, $USER;

        $now = time();
        $record->timemodified = $now;
        $record->modifiedby = (int)($USER->id ?? 0);
        if (!empty($record->id)) {
            $current = $this->get((int)$record->tenantid, (int)$record->id);
            $record->version = (int)$current->version + 1;
            $DB->update_record('local_tenantmaster_master', $record);
        } else {
            $record->timecreated = $now;
            $record->createdby = (int)($USER->id ?? 0);
            $record->version = 1;
            $record->id = $DB->insert_record('local_tenantmaster_master', $record);
        }
        return $this->get((int)$record->tenantid, (int)$record->id);
    }
}
