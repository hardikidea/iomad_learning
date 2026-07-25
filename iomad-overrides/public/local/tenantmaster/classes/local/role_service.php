<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\local;

/**
 * Explicit business-role to native-role mapping.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class role_service {
    /**
     * Canonical business-role mappings for the supported IOMAD 5.1 baseline.
     */
    public const DEFAULTS = [
        'principal_registrar' => ['companymanager', 1, 'company'],
        'trustee_management' => ['companyreporter', 4, 'company'],
        'it_coordinator' => ['institutionitcoordinator', 0, 'company'],
        'teacher_faculty' => ['editingteacher', 0, 'course'],
        'student_learner' => ['student', 0, 'course'],
        'parent_guardian' => ['tenantguardian', 0, 'user'],
        'hod_dean' => ['companydepartmentmanager', 2, 'department'],
    ];

    /**
     * Ensure all required role mappings exist without granting site admin.
     *
     * @param int $tenantid Tenant.
     */
    public function ensure_defaults(int $tenantid): void {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/lib/accesslib.php');
        $guardianroleid = $this->ensure_guardian_role();
        $itcoordinatorroleid = $this->ensure_it_coordinator_role();
        foreach (self::DEFAULTS as $rolekey => [$shortname, $managertype, $scope]) {
            if (
                $DB->record_exists('local_tenantmaster_rolemap', [
                    'tenantid' => $tenantid,
                    'rolekey' => $rolekey,
                ])
            ) {
                continue;
            }
            $roleid = $shortname === 'tenantguardian'
                ? $guardianroleid
                : ($shortname === 'institutionitcoordinator'
                    ? $itcoordinatorroleid
                    : (int)$DB->get_field('role', 'id', ['shortname' => $shortname]));
            if ($roleid <= 0) {
                throw new \moodle_exception('missingrequiredrole', 'local_tenantmaster', '', $shortname);
            }
            $DB->insert_record('local_tenantmaster_rolemap', (object)[
                'tenantid' => $tenantid,
                'rolekey' => $rolekey,
                'roleid' => $roleid,
                'managertype' => $managertype,
                'scope' => $scope,
                'departmentid' => 0,
                'capabilityjson' => json::encode($this->role_capabilities($roleid)),
                'active' => 1,
                'timecreated' => time(),
                'timemodified' => time(),
            ]);
        }
    }

    /**
     * Ensure the company-scoped, non-site-admin IT coordinator role.
     *
     * @return int Role ID.
     */
    public function ensure_it_coordinator_role(): int {
        global $DB;

        $roleid = (int)$DB->get_field('role', 'id', ['shortname' => 'institutionitcoordinator']);
        if ($roleid <= 0) {
            $roleid = create_role(
                'IT coordinator',
                'institutionitcoordinator',
                'Scoped IOMAD company administration without site administrator access'
            );
        }
        set_role_contextlevels($roleid, [CONTEXT_COMPANY]);
        $capabilities = [
            'block/iomad_company_admin:company_view_all',
            'block/iomad_company_admin:company_user_create',
            'block/iomad_company_admin:company_user_update',
            'block/iomad_company_admin:company_user_upload',
            'local/tenantmaster:view',
            'local/tenantmaster:manageprofile',
            'local/tenantmaster:manageorganisation',
            'local/tenantmaster:managepeople',
            'local/tenantmaster:manageroles',
        ];
        foreach ($capabilities as $capability) {
            if (get_capability_info($capability)) {
                assign_capability(
                    $capability,
                    CAP_ALLOW,
                    $roleid,
                    \context_system::instance()->id,
                    true
                );
            }
        }
        foreach (
            [
                'local/tenantmaster:sync',
                'local/tenantmaster:import',
                'local/tenantmaster:resolvedrift',
                'local/tenantmaster:destructive',
            ] as $capability
        ) {
            if (get_capability_info($capability)) {
                assign_capability(
                    $capability,
                    CAP_PREVENT,
                    $roleid,
                    \context_system::instance()->id,
                    true
                );
            }
        }
        return $roleid;
    }

    /**
     * List mappings with native role names.
     *
     * @param int $tenantid Tenant.
     * @return array<int, object>
     */
    public function list(int $tenantid): array {
        global $DB;

        return $DB->get_records_sql(
            "SELECT rm.*, r.name AS rolename, r.shortname AS roleshortname
               FROM {local_tenantmaster_rolemap} rm
          LEFT JOIN {role} r ON r.id = rm.roleid
              WHERE rm.tenantid = :tenantid
           ORDER BY rm.rolekey",
            ['tenantid' => $tenantid],
        );
    }

    /**
     * Ensure a mentor-style parent/guardian role.
     *
     * @return int Role ID.
     */
    private function ensure_guardian_role(): int {
        global $DB;

        $existing = $DB->get_field('role', 'id', ['shortname' => 'tenantguardian']);
        if ($existing) {
            return (int)$existing;
        }
        $roleid = create_role('Tenant guardian', 'tenantguardian', 'Scoped learner mentor relationship');
        set_role_contextlevels($roleid, [CONTEXT_USER]);
        assign_capability('moodle/user:viewdetails', CAP_ALLOW, $roleid, \context_system::instance()->id, true);
        assign_capability('moodle/user:viewalldetails', CAP_PREVENT, $roleid, \context_system::instance()->id, true);
        return $roleid;
    }

    /**
     * Snapshot explicitly assigned capabilities.
     *
     * @param int $roleid Role.
     * @return array<string, int>
     */
    private function role_capabilities(int $roleid): array {
        global $DB;

        if ($roleid <= 0) {
            return [];
        }
        return array_map(
            'intval',
            $DB->get_records_menu(
                'role_capabilities',
                ['roleid' => $roleid],
                'capability',
                'capability, permission',
            ),
        );
    }
}
