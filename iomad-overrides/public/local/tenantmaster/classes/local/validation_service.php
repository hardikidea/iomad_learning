<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\local;

/**
 * Tenant validation and isolation checks.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class validation_service {
    /**
     * Validate a complete tenant.
     *
     * @param int $tenantid Tenant.
     * @return array{errors: int, warnings: int, blocking: int}
     */
    public function validate(int $tenantid): array {
        global $DB;

        $tenant = $DB->get_record('local_tenantmaster_tenant', ['id' => $tenantid], '*', MUST_EXIST);
        $DB->set_field_select(
            'local_tenantmaster_valissue',
            'status',
            'superseded',
            'tenantid = :tenantid AND status = :status',
            ['tenantid' => $tenantid, 'status' => 'open'],
        );
        $counts = ['errors' => 0, 'warnings' => 0, 'blocking' => 0];

        if (!$DB->record_exists('local_iomad_companies', ['id' => $tenant->companyid])) {
            $this->issue(
                $tenantid,
                'tenant',
                'local_tenantmaster_tenant',
                $tenantid,
                'companyid',
                'error',
                'missing_company',
                'The linked IOMAD company does not exist.',
                'Restore or remap the native company before synchronization.',
                true,
                $counts
            );
        }
        if (!catalog::valid_external_key((string)$tenant->trustcode)) {
            $this->issue(
                $tenantid,
                'tenant',
                'local_tenantmaster_tenant',
                $tenantid,
                'trustcode',
                'error',
                'invalid_trust_code',
                'The trust code is not a valid stable external key.',
                'Use letters, numbers, dot, underscore, colon or hyphen.',
                true,
                $counts
            );
        }
        if (!array_key_exists((string)$tenant->tenanttype, catalog::TENANT_TYPES)) {
            $this->issue(
                $tenantid,
                'tenant',
                'local_tenantmaster_tenant',
                $tenantid,
                'tenanttype',
                'error',
                'invalid_tenant_type',
                'The tenant type is unsupported.',
                'Select school, university, college or training.',
                true,
                $counts
            );
        }

        $currentyears = $DB->count_records('local_tenantmaster_acadyear', [
            'tenantid' => $tenantid,
            'iscurrent' => 1,
        ]);
        if ($currentyears > 1) {
            $this->issue(
                $tenantid,
                'academic',
                'local_tenantmaster_acadyear',
                0,
                'iscurrent',
                'error',
                'multiple_current_years',
                'More than one academic year is current.',
                'Choose exactly one current academic year.',
                true,
                $counts
            );
        } else if ($currentyears === 0) {
            $this->issue(
                $tenantid,
                'academic',
                'local_tenantmaster_acadyear',
                0,
                'iscurrent',
                'warning',
                'missing_current_year',
                'No academic year is current.',
                'Create or select the current academic year.',
                false,
                $counts
            );
        }

        $masters = $DB->get_records('local_tenantmaster_master', ['tenantid' => $tenantid]);
        foreach ($masters as $master) {
            if (!array_key_exists((string)$master->mastertype, catalog::MASTER_TYPES)) {
                $this->issue(
                    $tenantid,
                    'academic',
                    'local_tenantmaster_master',
                    (int)$master->id,
                    'mastertype',
                    'error',
                    'invalid_master_type',
                    'The academic master type is unsupported.',
                    'Select a supported academic master type.',
                    true,
                    $counts
                );
            }
            if (
                !catalog::valid_external_key((string)$master->externalid)
                    || !catalog::valid_external_key((string)$master->code)
            ) {
                $this->issue(
                    $tenantid,
                    'academic',
                    'local_tenantmaster_master',
                    (int)$master->id,
                    'externalid',
                    'error',
                    'invalid_stable_key',
                    'The external ID or code is invalid.',
                    'Use a stable machine-readable key.',
                    true,
                    $counts
                );
            }
            if (
                (int)$master->parentid > 0 && !$DB->record_exists('local_tenantmaster_master', [
                    'id' => $master->parentid,
                    'tenantid' => $tenantid,
                ])
            ) {
                $this->issue(
                    $tenantid,
                    'academic',
                    'local_tenantmaster_master',
                    (int)$master->id,
                    'parentid',
                    'error',
                    'cross_tenant_parent',
                    'The parent does not belong to this tenant.',
                    'Select a parent from the same tenant.',
                    true,
                    $counts
                );
            }
            try {
                json::decode_object((string)$master->payloadjson);
            } catch (\Throwable) {
                $this->issue(
                    $tenantid,
                    'academic',
                    'local_tenantmaster_master',
                    (int)$master->id,
                    'payloadjson',
                    'error',
                    'invalid_configuration_json',
                    'The master configuration is not valid JSON.',
                    'Correct the configuration JSON before synchronization.',
                    true,
                    $counts
                );
            }
            if ($this->has_parent_cycle($tenantid, $master)) {
                $this->issue(
                    $tenantid,
                    'academic',
                    'local_tenantmaster_master',
                    (int)$master->id,
                    'parentid',
                    'error',
                    'academic_parent_cycle',
                    'The academic hierarchy contains a parent cycle.',
                    'Choose a parent that is not a descendant of this record.',
                    true,
                    $counts
                );
            }
        }

        $this->validate_native_mappings($tenant, $counts);
        $this->validate_role_mappings($tenantid, $counts);
        $this->validate_native_access($tenant, $counts);
        $this->validate_course_configuration($tenant, $counts);
        return $counts;
    }

    /**
     * Validate mapped native records remain inside the tenant.
     *
     * @param object $tenant Tenant.
     * @param array<string, int> $counts Counts.
     */
    private function validate_native_mappings(object $tenant, array &$counts): void {
        global $DB;

        $mappings = $DB->get_records('local_tenantmaster_mapping', ['tenantid' => $tenant->id]);
        foreach ($mappings as $mapping) {
            if ($mapping->component === 'core/course') {
                $assigned = $DB->record_exists('local_iomad_company_courses', [
                    'companyid' => $tenant->companyid,
                    'courseid' => $mapping->targetid,
                ]);
                if (!$assigned) {
                    $this->issue(
                        (int)$tenant->id,
                        'courses',
                        'local_tenantmaster_mapping',
                        (int)$mapping->id,
                        'targetid',
                        'error',
                        'course_company_leakage',
                        'A mapped course is not assigned to the tenant company.',
                        'Restore the IOMAD company-course assignment or resolve the mapping.',
                        true,
                        $counts
                    );
                }
                $idnumber = (string)$DB->get_field('course', 'idnumber', ['id' => $mapping->targetid]);
                if (!str_starts_with($idnumber, 'TM:' . $tenant->trustcode . ':')) {
                    $this->issue(
                        (int)$tenant->id,
                        'courses',
                        'local_tenantmaster_mapping',
                        (int)$mapping->id,
                        'externalkey',
                        'error',
                        'course_external_key_mismatch',
                        'A mapped course does not use the tenant stable-key prefix.',
                        'Review the mapping and restore the managed course idnumber.',
                        true,
                        $counts
                    );
                }
            } else if ($mapping->component === 'core_course/category') {
                $idnumber = (string)$DB->get_field('course_categories', 'idnumber', ['id' => $mapping->targetid]);
                if (!str_starts_with($idnumber, 'TM:' . $tenant->trustcode . ':')) {
                    $this->issue(
                        (int)$tenant->id,
                        'categories',
                        'local_tenantmaster_mapping',
                        (int)$mapping->id,
                        'externalkey',
                        'error',
                        'category_external_key_mismatch',
                        'A mapped category is missing or does not use the tenant stable-key prefix.',
                        'Review the mapping and restore the managed category idnumber.',
                        true,
                        $counts
                    );
                }
            } else if ($mapping->component === 'local_iomad/department') {
                if (
                    !$DB->record_exists('local_iomad_company_departments', [
                        'id' => $mapping->targetid,
                        'companyid' => $tenant->companyid,
                    ])
                ) {
                    $this->issue(
                        (int)$tenant->id,
                        'organisation',
                        'local_tenantmaster_mapping',
                        (int)$mapping->id,
                        'targetid',
                        'error',
                        'department_company_leakage',
                        'A mapped department belongs to another company or is missing.',
                        'Remap the department within the tenant company.',
                        true,
                        $counts
                    );
                }
            }
        }
    }

    /**
     * Ensure every business role has an explicit tenant mapping.
     *
     * @param int $tenantid Tenant.
     * @param array<string, int> $counts Counts.
     */
    private function validate_role_mappings(int $tenantid, array &$counts): void {
        global $DB;

        foreach (role_service::DEFAULTS as $rolekey => [$roleshortname, $managertype, $scope]) {
            $mapping = $DB->get_record('local_tenantmaster_rolemap', [
                    'tenantid' => $tenantid,
                    'rolekey' => $rolekey,
                    'active' => 1,
                ]);
            if (!$mapping) {
                $this->issue(
                    $tenantid,
                    'roles',
                    'local_tenantmaster_rolemap',
                    0,
                    'rolekey',
                    'warning',
                    'missing_role_mapping',
                    'No active native mapping exists for role: ' . $rolekey . '.',
                    'Review and activate the tenant role mapping.',
                    false,
                    $counts
                );
            } else if ((int)$mapping->roleid <= 0 || !$DB->record_exists('role', ['id' => $mapping->roleid])) {
                $this->issue(
                    $tenantid,
                    'roles',
                    'local_tenantmaster_rolemap',
                    (int)$mapping->id,
                    'roleid',
                    'error',
                    'missing_native_role',
                    'The business-role mapping does not resolve to an installed native role.',
                    'Restore the required IOMAD role before assigning users.',
                    true,
                    $counts
                );
            } else {
                $actualshortname = (string)$DB->get_field('role', 'shortname', ['id' => $mapping->roleid]);
                if (
                    $actualshortname !== $roleshortname
                    || (int)$mapping->managertype !== $managertype
                    || (string)$mapping->scope !== $scope
                ) {
                    $this->issue(
                        $tenantid,
                        'roles',
                        'local_tenantmaster_rolemap',
                        (int)$mapping->id,
                        'roleid',
                        'error',
                        'unsafe_role_mapping',
                        'The business role does not match the reviewed IOMAD role, manager type, and scope.',
                        'Restore the canonical mapping before assigning users.',
                        true,
                        $counts
                    );
                }
            }
        }
    }

    /**
     * Detect tenant-owned cohort and group leakage.
     *
     * @param object $tenant Tenant.
     * @param array<string, int> $counts Counts.
     */
    private function validate_native_access(object $tenant, array &$counts): void {
        global $DB;

        $cohorts = $DB->get_records_select(
            'cohort',
            'idnumber LIKE :prefix',
            ['prefix' => 'TM:' . $tenant->trustcode . ':COHORT:%'],
        );
        foreach ($cohorts as $cohort) {
            $leaks = $DB->count_records_sql(
                "SELECT COUNT(cm.id)
                   FROM {cohort_members} cm
              LEFT JOIN {local_iomad_company_users} cu
                     ON cu.userid = cm.userid AND cu.companyid = :companyid
                  WHERE cm.cohortid = :cohortid
                    AND cu.id IS NULL",
                ['companyid' => $tenant->companyid, 'cohortid' => $cohort->id],
            );
            if ($leaks > 0) {
                $this->issue(
                    (int)$tenant->id,
                    'cohorts',
                    'cohort',
                    (int)$cohort->id,
                    'members',
                    'error',
                    'cross_tenant_cohort_member',
                    'A tenant-managed cohort contains users outside the company.',
                    'Remove or reassign the invalid memberships through supported cohort APIs.',
                    true,
                    $counts
                );
            }
        }

        $groups = $DB->get_records_sql(
            "SELECT g.*
               FROM {groups} g
               JOIN {local_iomad_company_courses} cc ON cc.courseid = g.courseid
              WHERE cc.companyid = :companyid
                AND g.idnumber LIKE :prefix",
            [
                'companyid' => $tenant->companyid,
                'prefix' => 'TM:' . $tenant->trustcode . ':GROUP:%',
            ],
        );
        foreach ($groups as $group) {
            $leaks = $DB->count_records_sql(
                "SELECT COUNT(gm.id)
                   FROM {groups_members} gm
              LEFT JOIN {local_iomad_company_users} cu
                     ON cu.userid = gm.userid AND cu.companyid = :companyid
                  WHERE gm.groupid = :groupid
                    AND cu.id IS NULL",
                ['companyid' => $tenant->companyid, 'groupid' => $group->id],
            );
            if ($leaks > 0) {
                $this->issue(
                    (int)$tenant->id,
                    'groups',
                    'groups',
                    (int)$group->id,
                    'members',
                    'error',
                    'cross_tenant_group_member',
                    'A tenant-managed course group contains users outside the company.',
                    'Remove or reassign the invalid memberships through supported group APIs.',
                    true,
                    $counts
                );
            }
        }
    }

    /**
     * Verify native gradebook, completion and certificate projections.
     *
     * @param object $tenant Tenant.
     * @param array<string, int> $counts Counts.
     */
    private function validate_course_configuration(object $tenant, array &$counts): void {
        global $DB;

        $hascertificate = $DB->record_exists('local_tenantmaster_master', [
            'tenantid' => $tenant->id,
            'mastertype' => 'certificate_rule',
            'active' => 1,
        ]);
        $courses = $DB->get_records_sql(
            "SELECT c.id, c.enablecompletion
               FROM {course} c
               JOIN {local_iomad_company_courses} cc ON cc.courseid = c.id
              WHERE cc.companyid = :companyid
                AND c.idnumber LIKE :prefix",
            ['companyid' => $tenant->companyid, 'prefix' => 'TM:' . $tenant->trustcode . ':%'],
        );
        foreach ($courses as $course) {
            if (!$course->enablecompletion) {
                $this->issue(
                    (int)$tenant->id,
                    'courses',
                    'course',
                    (int)$course->id,
                    'enablecompletion',
                    'warning',
                    'completion_not_enabled',
                    'Completion is not enabled on a Tenant Master course.',
                    'Retry course configuration synchronization.',
                    false,
                    $counts
                );
            }
            if (
                !$DB->record_exists('grade_items', [
                    'courseid' => $course->id,
                    'itemtype' => 'local',
                    'itemmodule' => 'tenantmaster',
                    'idnumber' => 'TM_ATTENDANCE',
                ])
            ) {
                $this->issue(
                    (int)$tenant->id,
                    'attendance',
                    'course',
                    (int)$course->id,
                    'gradeitem',
                    'warning',
                    'attendance_grade_missing',
                    'The native attendance grade item has not been projected.',
                    'Retry attendance synchronization for this course.',
                    false,
                    $counts
                );
            }
            if ($hascertificate && !$DB->record_exists('iomadcertificate', ['course' => $course->id])) {
                $this->issue(
                    (int)$tenant->id,
                    'certificates',
                    'course',
                    (int)$course->id,
                    'activity',
                    'warning',
                    'certificate_missing',
                    'The configured native IOMAD certificate activity is missing.',
                    'Retry certificate synchronization for this course.',
                    false,
                    $counts
                );
            }
        }
    }

    /**
     * Detect an academic parent cycle without trusting database constraints.
     *
     * @param int $tenantid Tenant.
     * @param object $master Master.
     * @return bool
     */
    private function has_parent_cycle(int $tenantid, object $master): bool {
        global $DB;

        $seen = [(int)$master->id => true];
        $parentid = (int)$master->parentid;
        while ($parentid > 0) {
            if (isset($seen[$parentid])) {
                return true;
            }
            $seen[$parentid] = true;
            $parentid = (int)$DB->get_field('local_tenantmaster_master', 'parentid', [
                'id' => $parentid,
                'tenantid' => $tenantid,
            ]);
        }
        return false;
    }

    /**
     * Store one issue and update counts.
     *
     * @param int $tenantid Tenant.
     * @param string $module Module.
     * @param string $entitytable Entity table.
     * @param int $entityid Entity ID.
     * @param string $fieldname Field.
     * @param string $severity Severity.
     * @param string $issuecode Code.
     * @param string $message Message.
     * @param string $correction Correction.
     * @param bool $blocking Blocking.
     * @param array<string, int> $counts Counts.
     */
    private function issue(
        int $tenantid,
        string $module,
        string $entitytable,
        int $entityid,
        string $fieldname,
        string $severity,
        string $issuecode,
        string $message,
        string $correction,
        bool $blocking,
        array &$counts,
    ): void {
        global $DB;

        $DB->insert_record('local_tenantmaster_valissue', (object)[
            'tenantid' => $tenantid,
            'jobid' => 0,
            'module' => $module,
            'entitytable' => $entitytable,
            'entityid' => $entityid,
            'fieldname' => $fieldname,
            'severity' => $severity,
            'issuecode' => $issuecode,
            'message' => $message,
            'correction' => $correction,
            'blocking' => (int)$blocking,
            'status' => 'open',
            'timecreated' => time(),
            'timeresolved' => 0,
        ]);
        $counts[$severity === 'error' ? 'errors' : 'warnings']++;
        if ($blocking) {
            $counts['blocking']++;
        }
    }
}
