<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\local;

/**
 * Native projection mapping repository.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class mapping_repository {
    /**
     * Find a mapping.
     *
     * @param int $tenantid Tenant.
     * @param string $component Component.
     * @param string $externalkey External key.
     * @return object|null
     */
    public function find(int $tenantid, string $component, string $externalkey): ?object {
        global $DB;

        $record = $DB->get_record('local_tenantmaster_mapping', [
            'tenantid' => $tenantid,
            'component' => $component,
            'externalkey' => $externalkey,
        ]);
        return $record ?: null;
    }

    /**
     * Find the mapping for a master and component.
     *
     * @param int $tenantid Tenant.
     * @param int $masterid Master.
     * @param string $component Component.
     * @return object|null
     */
    public function find_for_master(int $tenantid, int $masterid, string $component): ?object {
        global $DB;
        $record = $DB->get_record('local_tenantmaster_mapping', [
            'tenantid' => $tenantid,
            'masterid' => $masterid,
            'component' => $component,
        ]);
        return $record ?: null;
    }

    /**
     * Upsert a verified mapping.
     *
     * @param int $tenantid Tenant.
     * @param int $masterid Master, or zero.
     * @param projection_result $result Projection result.
     * @return object
     */
    public function save(int $tenantid, int $masterid, projection_result $result): object {
        global $DB;

        $now = time();
        $record = $this->find($tenantid, $result->component, $result->externalkey);
        $values = (object)[
            'tenantid' => $tenantid,
            'masterid' => $masterid,
            'component' => $result->component,
            'externalkey' => $result->externalkey,
            'targetid' => $result->targetid,
            'managedjson' => json::encode($result->native),
            'desiredhash' => json::hash($result->desired),
            'nativehash' => json::hash($result->native),
            'status' => 'synced',
            'lasterror' => null,
            'lastsynced' => $now,
            'timemodified' => $now,
        ];
        if ($record) {
            $values->id = $record->id;
            $DB->update_record('local_tenantmaster_mapping', $values);
            $id = (int)$record->id;
        } else {
            $values->timecreated = $now;
            $id = (int)$DB->insert_record('local_tenantmaster_mapping', $values);
        }
        return $DB->get_record('local_tenantmaster_mapping', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * List mappings for drift or UI.
     *
     * @param int $tenantid Optional tenant.
     * @return array<int, object>
     */
    public function list(int $tenantid = 0): array {
        global $DB;
        $conditions = $tenantid > 0 ? ['tenantid' => $tenantid] : [];
        return $DB->get_records('local_tenantmaster_mapping', $conditions, 'tenantid, component, externalkey');
    }
}
