<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\local;

/**
 * Three-way native drift detection and explicit resolution.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class drift_service {
    /**
     * Constructor.
     *
     * @param projection_adapter $adapter Adapter.
     * @param mapping_repository $mappings Mappings.
     * @param queue_service $queue Queue.
     * @param audit_service $audit Audit.
     */
    public function __construct(
        private readonly projection_adapter $adapter = new iomad_501_adapter(),
        private readonly mapping_repository $mappings = new mapping_repository(),
        private readonly queue_service $queue = new queue_service(),
        private readonly audit_service $audit = new audit_service(),
    ) {
    }

    /**
     * Detect drift across every mapping.
     *
     * @return int Open drift count.
     */
    public function detect_all(): int {
        $count = 0;
        foreach ($this->mappings->list() as $mapping) {
            $count += $this->detect_mapping($mapping);
        }
        return $count;
    }

    /**
     * Detect field-level drift for one mapping.
     *
     * @param object $mapping Mapping.
     * @return int Open drift count.
     */
    public function detect_mapping(object $mapping): int {
        global $DB;

        if ($mapping->status === 'ignored') {
            return 0;
        }
        $current = $this->adapter->read_mapping($mapping);
        $base = json::decode_object((string)$mapping->managedjson);
        $missing = $current === null;
        if ($missing) {
            $current = [];
        }
        $dirty = $mapping->masterid > 0 && $DB->record_exists_select(
            'local_tenantmaster_dirty',
            'tenantid = :tenantid AND entitytable = :entitytable AND entityid = :entityid
                 AND state <> :synced',
            [
                'tenantid' => $mapping->tenantid,
                'entitytable' => 'local_tenantmaster_master',
                'entityid' => $mapping->masterid,
                'synced' => 'synced',
            ],
        );
        $drifttype = $dirty ? 'conflict' : 'platform_only';
        $fields = array_unique(array_merge(array_keys($base), array_keys($current)));
        $open = 0;
        foreach ($fields as $field) {
            $basevalue = $base[$field] ?? null;
            $nativevalue = $current[$field] ?? null;
            if (!$missing && $basevalue == $nativevalue) {
                $DB->set_field_select(
                    'local_tenantmaster_drift',
                    'status',
                    'resolved',
                    'mappingid = :mappingid AND fieldpath = :fieldpath AND status = :status',
                    ['mappingid' => $mapping->id, 'fieldpath' => $field, 'status' => 'open'],
                );
                continue;
            }
            $open++;
            $existing = $DB->get_record('local_tenantmaster_drift', [
                'mappingid' => $mapping->id,
                'fieldpath' => $field,
                'status' => 'open',
            ]);
            $record = (object)[
                'tenantid' => $mapping->tenantid,
                'mappingid' => $mapping->id,
                'drifttype' => $missing ? 'missing_native' : $drifttype,
                'fieldpath' => $field,
                'basehash' => json::hash($basevalue),
                'desiredhash' => $mapping->desiredhash,
                'nativehash' => json::hash($nativevalue),
                'status' => 'open',
                'resolution' => null,
                'detailjson' => json::encode([
                    'base' => $basevalue,
                    'native' => $nativevalue,
                ]),
                'timeresolved' => 0,
                'resolvedby' => 0,
            ];
            if ($existing) {
                $record->id = $existing->id;
                $DB->update_record('local_tenantmaster_drift', $record);
            } else {
                $record->timecreated = time();
                $DB->insert_record('local_tenantmaster_drift', $record);
            }
        }
        if ($open > 0) {
            $DB->set_field('local_tenantmaster_mapping', 'status', 'drifted', ['id' => $mapping->id]);
        } else {
            $DB->set_field('local_tenantmaster_mapping', 'status', 'synced', ['id' => $mapping->id]);
        }
        return $open;
    }

    /**
     * Resolve drift without silently selecting a winner.
     *
     * @param int $tenantid Tenant.
     * @param int $driftid Drift.
     * @param string $resolution import_native, restore_managed or ignore.
     */
    public function resolve(int $tenantid, int $driftid, string $resolution): void {
        global $DB, $USER;

        if (!in_array($resolution, ['import_native', 'restore_managed', 'ignore'], true)) {
            throw new \invalid_parameter_exception('Invalid drift resolution.');
        }
        $drift = $DB->get_record(
            'local_tenantmaster_drift',
            ['id' => $driftid, 'tenantid' => $tenantid, 'status' => 'open'],
            '*',
            MUST_EXIST,
        );
        $mapping = $DB->get_record(
            'local_tenantmaster_mapping',
            ['id' => $drift->mappingid, 'tenantid' => $tenantid],
            '*',
            MUST_EXIST,
        );
        if ($resolution === 'restore_managed') {
            $queued = false;
            if ($mapping->component === 'mod_iomadcertificate/certificate') {
                $courseid = (int)$DB->get_field(
                    'iomadcertificate',
                    'course',
                    ['id' => $mapping->targetid],
                    MUST_EXIST,
                );
                $this->queue->mark_dirty(
                    $tenantid,
                    'certificates',
                    'course',
                    $courseid,
                    'restore_managed_drift',
                    true,
                );
                $queued = true;
            } else if ((int)$mapping->masterid > 0 || $mapping->component === 'local_iomad/company') {
                $entitytable = $mapping->masterid > 0
                    ? 'local_tenantmaster_master'
                    : 'local_tenantmaster_tenant';
                $entityid = $mapping->masterid > 0 ? (int)$mapping->masterid : $tenantid;
                $module = $mapping->component === 'core/course' ? 'courses'
                    : ($mapping->component === 'core_course/category' ? 'categories' : 'tenant');
                $this->queue->mark_dirty($tenantid, $module, $entitytable, $entityid, 'restore_managed_drift');
                $queued = true;
            } else {
                $this->restore_direct_mapping($mapping);
            }
            if ($queued) {
                $DB->set_field('local_tenantmaster_mapping', 'status', 'pending', ['id' => $mapping->id]);
            }
        } else if ($resolution === 'import_native') {
            // Native authority is accepted as the new baseline. Academic source
            // changes still require an explicit master edit; no source fields are
            // silently overwritten here.
            $current = $this->adapter->read_mapping($mapping);
            if ($current === null) {
                throw new \moodle_exception('missingnativerecord', 'local_tenantmaster');
            }
            $mapping->managedjson = json::encode($current);
            $mapping->nativehash = json::hash($current);
            $mapping->desiredhash = json::hash($current);
            $mapping->status = 'synced';
            $mapping->lastsynced = time();
            $mapping->timemodified = time();
            $DB->update_record('local_tenantmaster_mapping', $mapping);
        } else {
            $current = $this->adapter->read_mapping($mapping);
            if ($current === null) {
                throw new \moodle_exception('missingnativerecord', 'local_tenantmaster');
            }
            $mapping->managedjson = json::encode($current);
            $mapping->nativehash = json::hash($current);
            $mapping->status = 'ignored';
            $mapping->lasterror = null;
            $mapping->lastsynced = time();
            $mapping->timemodified = time();
            $DB->update_record('local_tenantmaster_mapping', $mapping);
        }
        $drift->status = 'resolved';
        $drift->resolution = $resolution;
        $drift->timeresolved = time();
        $drift->resolvedby = (int)($USER->id ?? 0);
        $DB->update_record('local_tenantmaster_drift', $drift);
        $this->audit->record(
            $tenantid,
            'drift.resolved',
            'success',
            ['resolution' => $resolution, 'fieldpath' => $drift->fieldpath],
            ['entitytable' => 'local_tenantmaster_mapping', 'entityid' => (int)$mapping->id],
        );
    }

    /**
     * Restore a directly managed native object through its supported API.
     *
     * @param object $mapping Mapping.
     */
    private function restore_direct_mapping(object $mapping): void {
        global $CFG, $DB;

        $tenant = (new tenant_repository())->get((int)$mapping->tenantid);
        $managed = json::decode_object((string)$mapping->managedjson);
        if ($mapping->component === 'local_iomad/department') {
            (new organisation_service())->save($tenant, (object)([
                'id' => (int)$mapping->targetid,
            ] + $managed));
        } else if ($mapping->component === 'core/cohort') {
            require_once($CFG->dirroot . '/cohort/lib.php');
            $cohort = $DB->get_record('cohort', ['id' => $mapping->targetid], '*', MUST_EXIST);
            cohort_update_cohort((object)(array_merge((array)$cohort, $managed)));
        } else if ($mapping->component === 'core/group') {
            require_once($CFG->dirroot . '/group/lib.php');
            $group = $DB->get_record('groups', ['id' => $mapping->targetid], '*', MUST_EXIST);
            if (
                !$DB->record_exists('local_iomad_company_courses', [
                    'companyid' => $tenant->companyid,
                    'courseid' => $group->courseid,
                ])
            ) {
                throw new \invalid_parameter_exception('Mapped group is outside the tenant course boundary.');
            }
            groups_update_group((object)(array_merge((array)$group, $managed)));
        } else if ($mapping->component === 'core_course/category') {
            $category = \core_course_category::get((int)$mapping->targetid, MUST_EXIST, true);
            if (!str_starts_with((string)($managed['idnumber'] ?? ''), 'TM:' . $tenant->trustcode . ':')) {
                throw new \invalid_parameter_exception('Mapped category is outside the tenant stable-key boundary.');
            }
            $category->update((object)$managed);
        } else {
            throw new \invalid_parameter_exception('Managed restoration is unsupported for this component.');
        }
        $mapping->nativehash = json::hash($managed);
        $mapping->status = 'synced';
        $mapping->lasterror = null;
        $mapping->lastsynced = time();
        $mapping->timemodified = time();
        $DB->update_record('local_tenantmaster_mapping', $mapping);
    }
}
