<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\local;

use context_course;
use context_user;
use local_iomad\company;
use local_iomad\company_user;
use local_iomad\custom_context\context_company;

/**
 * Native IOMAD people, business-role and mentor relationship service.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class people_service {
    /**
     * List current native company memberships.
     *
     * @param object $tenant Tenant.
     * @return array<int, object>
     */
    public function list(object $tenant): array {
        global $DB;

        return $DB->get_records_sql(
            "SELECT u.id, u.username, u.idnumber, u.firstname, u.lastname, u.email,
                    u.suspended, cu.departmentid, cu.managertype, cu.educator,
                    d.name AS departmentname
               FROM {local_iomad_company_users} cu
               JOIN {user} u ON u.id = cu.userid AND u.deleted = 0
          LEFT JOIN {local_iomad_company_departments} d ON d.id = cu.departmentid
              WHERE cu.companyid = :companyid
           ORDER BY u.lastname, u.firstname, u.id",
            ['companyid' => $tenant->companyid],
        );
    }

    /**
     * Assign an existing native user to one explicit business role.
     *
     * @param object $tenant Tenant.
     * @param int $userid User.
     * @param string $rolekey Business role.
     * @param int $departmentid Department.
     * @param int $courseid Optional course for teacher/student assignment.
     */
    public function assign_role(
        object $tenant,
        int $userid,
        string $rolekey,
        int $departmentid = 0,
        int $courseid = 0,
    ): void {
        global $DB;

        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', MUST_EXIST);
        if (
            !$DB->record_exists('local_iomad_company_users', [
                'companyid' => $tenant->companyid,
                'userid' => $userid,
            ])
        ) {
            throw new \invalid_parameter_exception('User must already belong to the selected tenant.');
        }
        $rolemap = $DB->get_record('local_tenantmaster_rolemap', [
            'tenantid' => $tenant->id,
            'rolekey' => $rolekey,
            'active' => 1,
        ], '*', MUST_EXIST);
        if ($departmentid <= 0) {
            $departmentid = (int)company::get_company_parentnode((int)$tenant->companyid)->id;
        }
        if (
            !$DB->record_exists('local_iomad_company_departments', [
                'id' => $departmentid,
                'companyid' => $tenant->companyid,
            ])
        ) {
            throw new \invalid_parameter_exception('Department belongs to another tenant.');
        }
        $educator = $rolekey === 'teacher_faculty';
        company::upsert_company_user(
            $userid,
            (int)$tenant->companyid,
            $departmentid,
            (int)$rolemap->managertype,
            $educator,
        );
        if (in_array($rolemap->scope, ['company', 'department'], true)) {
            role_assign(
                (int)$rolemap->roleid,
                $userid,
                context_company::instance((int)$tenant->companyid)->id
            );
        }
        if (in_array($rolekey, ['teacher_faculty', 'student_learner'], true)) {
            if (
                $courseid > 0 && !$DB->record_exists('local_iomad_company_courses', [
                    'companyid' => $tenant->companyid,
                    'courseid' => $courseid,
                ])
            ) {
                throw new \invalid_parameter_exception('The selected course belongs to another tenant.');
            }
            if ($courseid > 0) {
                company_user::enrol($user, [$courseid], (int)$tenant->companyid, (int)$rolemap->roleid);
                role_assign((int)$rolemap->roleid, $userid, context_course::instance($courseid)->id);
            }
        }
        (new audit_service())->record(
            (int)$tenant->id,
            'people.role.assigned',
            'success',
            ['rolekey' => $rolekey, 'departmentid' => $departmentid, 'courseid' => $courseid],
            ['entitytable' => 'user', 'entityid' => $userid],
        );
    }

    /**
     * Create an explicit native mentor relationship.
     *
     * @param object $tenant Tenant.
     * @param int $guardianid Guardian.
     * @param int $learnerid Learner.
     */
    public function link_guardian(object $tenant, int $guardianid, int $learnerid): void {
        global $DB;

        foreach ([$guardianid, $learnerid] as $userid) {
            if (
                !$DB->record_exists('local_iomad_company_users', [
                    'companyid' => $tenant->companyid,
                    'userid' => $userid,
                ])
            ) {
                throw new \invalid_parameter_exception('Both users must belong to the same tenant.');
            }
        }
        $rolemap = $DB->get_record('local_tenantmaster_rolemap', [
            'tenantid' => $tenant->id,
            'rolekey' => 'parent_guardian',
            'active' => 1,
        ], '*', MUST_EXIST);
        role_assign((int)$rolemap->roleid, $guardianid, context_user::instance($learnerid)->id);
        (new audit_service())->record(
            (int)$tenant->id,
            'people.guardian.linked',
            'success',
            ['relationship' => 'guardian'],
            ['entitytable' => 'user', 'entityid' => $learnerid, 'targetid' => $guardianid],
        );
    }
}
