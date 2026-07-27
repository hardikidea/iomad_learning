<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\local;

/**
 * Repository for the global, versioned academic master catalogue.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class catalogue_repository {
    /**
     * Return one catalogue item.
     *
     * @param int $id Item ID.
     * @return object
     */
    public function get(int $id): object {
        global $DB;
        return $DB->get_record('local_tenantmaster_catitem', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Find one item by its immutable business key.
     *
     * @param string $scope Scope.
     * @param string $mastertype Master type.
     * @param string $externalid External key.
     * @return object|null
     */
    public function find(string $scope, string $mastertype, string $externalid): ?object {
        global $DB;
        $record = $DB->get_record('local_tenantmaster_catitem', [
            'scope' => $scope,
            'mastertype' => $mastertype,
            'externalid' => $externalid,
        ]);
        return $record ?: null;
    }

    /**
     * List catalogue items.
     *
     * @param string $scope Optional scope.
     * @param string $mastertype Optional master type.
     * @param bool|null $active Optional active state.
     * @param bool $includeremoved Include removed audit tombstones.
     * @return array<int, object>
     */
    public function list(
        string $scope = '',
        string $mastertype = '',
        ?bool $active = null,
        bool $includeremoved = false,
    ): array {
        global $DB;

        $conditions = [];
        if ($scope !== '') {
            $conditions['scope'] = $scope;
        }
        if ($mastertype !== '') {
            $conditions['mastertype'] = $mastertype;
        }
        if ($active !== null) {
            $conditions['active'] = (int)$active;
        }
        if (!$includeremoved) {
            $conditions['deleted'] = 0;
        }
        return $DB->get_records(
            'local_tenantmaster_catitem',
            $conditions,
            'scope ASC, mastertype ASC, sortorder ASC, name ASC',
        );
    }

    /**
     * Return active shared and institution-specific items.
     *
     * @param string $tenanttype Tenant type.
     * @return array<int, object>
     */
    public function applicable(string $tenanttype): array {
        global $DB;

        return $DB->get_records_select(
            'local_tenantmaster_catitem',
            'active = :active AND deleted = :deleted'
                . ' AND (scope = :sharedscope OR scope = :typescope)',
            ['active' => 1, 'deleted' => 0, 'sharedscope' => 'shared', 'typescope' => $tenanttype],
            'scope ASC, mastertype ASC, sortorder ASC, name ASC',
        );
    }

    /**
     * Mark or restore a catalogue audit tombstone.
     *
     * @param int $id Item ID.
     * @param bool $deleted Removed state.
     * @param bool|null $activebeforedelete Active state to restore later.
     * @return object
     */
    public function set_deleted(int $id, bool $deleted, ?bool $activebeforedelete = null): object {
        global $DB, $USER;

        $record = $this->get($id);
        if ($deleted && $activebeforedelete !== null) {
            $record->activebeforedelete = $activebeforedelete ? 1 : 0;
        }
        $record->deleted = $deleted ? 1 : 0;
        $record->timedeleted = $deleted ? time() : 0;
        $record->deletedby = $deleted ? (int)($USER->id ?? 0) : 0;
        $record->timemodified = time();
        $record->modifiedby = (int)($USER->id ?? 0);
        $DB->update_record('local_tenantmaster_catitem', $record);
        return $this->get($id);
    }

    /**
     * Save and version a catalogue item.
     *
     * @param object $record Item.
     * @return object
     */
    public function save(object $record): object {
        global $DB, $USER;

        $now = time();
        $record->timemodified = $now;
        $record->modifiedby = (int)($USER->id ?? 0);
        if (!empty($record->id)) {
            $current = $this->get((int)$record->id);
            $record->version = (int)$current->version + 1;
            $DB->update_record('local_tenantmaster_catitem', $record);
        } else {
            $record->version = (int)($record->version ?? 1);
            $record->timecreated = $now;
            $record->createdby = (int)($USER->id ?? 0);
            $record->id = $DB->insert_record('local_tenantmaster_catitem', $record);
        }
        return $this->get((int)$record->id);
    }
}
