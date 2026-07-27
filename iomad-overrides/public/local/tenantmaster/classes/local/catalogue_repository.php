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
     * @return array<int, object>
     */
    public function list(string $scope = '', string $mastertype = '', ?bool $active = null): array {
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
            'active = :active AND (scope = :sharedscope OR scope = :typescope)',
            ['active' => 1, 'sharedscope' => 'shared', 'typescope' => $tenanttype],
            'scope ASC, mastertype ASC, sortorder ASC, name ASC',
        );
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
