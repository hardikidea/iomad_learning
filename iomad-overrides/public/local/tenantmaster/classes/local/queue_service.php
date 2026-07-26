<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\local;

use local_tenantmaster\task\sync_entity;

/**
 * Debounced automatic projection queue.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class queue_service {
    /**
     * Mark an entity dirty and enqueue an ad-hoc worker.
     *
     * @param int $tenantid Tenant.
     * @param string $module Dependency module.
     * @param string $entitytable Source table.
     * @param int $entityid Source ID.
     * @param string $reason Reason.
     * @param bool $force Schedule even while automatic synchronization is paused.
     * @return int Dirty record ID.
     */
    public function mark_dirty(
        int $tenantid,
        string $module,
        string $entitytable,
        int $entityid,
        string $reason,
        bool $force = false,
    ): int {
        global $DB;

        if (!array_key_exists($module, catalog::MODULES)) {
            throw new \invalid_parameter_exception('Unknown Tenant Master module.');
        }
        $conditions = [
            'tenantid' => $tenantid,
            'module' => $module,
            'entitytable' => $entitytable,
            'entityid' => $entityid,
        ];
        $now = time();
        $record = $DB->get_record('local_tenantmaster_dirty', $conditions);
        if ($record) {
            $record->reason = $reason;
            $record->state = 'dirty';
            $record->attempts = 0;
            $record->availabletime = $now;
            $record->locktoken = null;
            $record->lasterror = null;
            $record->timemodified = $now;
            $DB->update_record('local_tenantmaster_dirty', $record);
            $dirtyid = (int)$record->id;
        } else {
            $dirtyid = (int)$DB->insert_record('local_tenantmaster_dirty', (object)($conditions + [
                'reason' => $reason,
                'state' => 'dirty',
                'attempts' => 0,
                'availabletime' => $now,
                'locktoken' => null,
                'lasterror' => null,
                'timecreated' => $now,
                'timemodified' => $now,
            ]));
        }

        if ($force || get_config('local_tenantmaster', 'autosync') !== '0') {
            $task = new sync_entity();
            $task->set_custom_data((object)[
                'tenantid' => $tenantid,
                'module' => $module,
                'force' => $force,
            ]);
            $task->set_next_run_time($now + 2);
            \core\task\manager::queue_adhoc_task($task, true);
        }
        return $dirtyid;
    }

    /**
     * Mark every dependency module dirty for a tenant.
     *
     * @param int $tenantid Tenant.
     * @param string $reason Reason.
     */
    public function sync_all(int $tenantid, string $reason = 'sync_all'): void {
        global $DB;

        foreach ($DB->get_records('local_tenantmaster_acadyear', ['tenantid' => $tenantid]) as $academicyear) {
            $this->mark_dirty(
                $tenantid,
                'categories',
                'local_tenantmaster_acadyear',
                (int)$academicyear->id,
                $reason,
                true,
            );
        }
        foreach ($DB->get_records('local_tenantmaster_master', ['tenantid' => $tenantid]) as $master) {
            foreach ($this->modules_for_master((string)$master->mastertype) as $module) {
                $this->mark_dirty(
                    $tenantid,
                    $module,
                    'local_tenantmaster_master',
                    (int)$master->id,
                    $reason,
                    true,
                );
            }
        }
        foreach (['assessments', 'attendance', 'certificates'] as $module) {
            $this->queue_company_courses($tenantid, $module, $reason, true);
        }
    }

    /**
     * Queue one background configuration item per native company course.
     *
     * @param int $tenantid Tenant.
     * @param string $module assessments, attendance or certificates.
     * @param string $reason Reason.
     * @param bool $force Schedule while automatic synchronization is paused.
     */
    public function queue_company_courses(int $tenantid, string $module, string $reason, bool $force = false): void {
        global $DB;

        $companyid = (int)$DB->get_field('local_tenantmaster_tenant', 'companyid', ['id' => $tenantid], MUST_EXIST);
        $courseids = $DB->get_fieldset_select(
            'local_iomad_company_courses',
            'courseid',
            'companyid = :companyid',
            ['companyid' => $companyid],
        );
        foreach (array_unique(array_map('intval', $courseids)) as $courseid) {
            $this->mark_dirty($tenantid, $module, 'course', $courseid, $reason, $force);
        }
    }

    /**
     * Native projection modules owned by one academic master type.
     *
     * @param string $mastertype Master type.
     * @return string[]
     */
    private function modules_for_master(string $mastertype): array {
        return match ($mastertype) {
            'assessment_policy' => ['assessments'],
            'attendance_policy' => ['attendance'],
            'certificate_rule' => ['certificates'],
            'progression_rule' => ['progression'],
            'subject', 'course_template' => ['courses'],
            'board', 'medium', 'grade', 'programme', 'semester', 'stream',
                'specialisation', 'division' => ['categories'],
            default => ['academic'],
        };
    }
}
