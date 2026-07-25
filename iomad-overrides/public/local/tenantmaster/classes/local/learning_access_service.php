<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\local;

use context_system;
use local_iomad\company_user;

/**
 * Native cohort, group and enrolment service with tenant isolation guards.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class learning_access_service {
    /**
     * Ensure a native system cohort.
     *
     * @param object $tenant Tenant.
     * @param string $externalid Stable external ID.
     * @param string $name Name.
     * @param string $description Description.
     * @return int Cohort ID.
     */
    public function ensure_cohort(
        object $tenant,
        string $externalid,
        string $name,
        string $description = '',
    ): int {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/cohort/lib.php');
        $idnumber = $this->key($tenant, 'COHORT', $externalid);
        $existing = $DB->get_record('cohort', ['idnumber' => $idnumber]);
        $data = (object)[
            'contextid' => context_system::instance()->id,
            'name' => $name,
            'idnumber' => $idnumber,
            'description' => $description,
            'descriptionformat' => FORMAT_PLAIN,
            'visible' => 1,
        ];
        if ($existing) {
            $data->id = $existing->id;
            cohort_update_cohort($data);
            $cohortid = (int)$existing->id;
        } else {
            $cohortid = (int)cohort_add_cohort($data);
        }
        $this->record_mapping($tenant, 'core/cohort', $idnumber, $cohortid, 'cohort');
        return $cohortid;
    }

    /**
     * Add a same-tenant user to a cohort.
     *
     * @param object $tenant Tenant.
     * @param int $cohortid Cohort.
     * @param int $userid User.
     */
    public function add_cohort_member(object $tenant, int $cohortid, int $userid): void {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/cohort/lib.php');
        $this->require_company_user($tenant, $userid);
        $cohort = $DB->get_record('cohort', ['id' => $cohortid], '*', MUST_EXIST);
        if (!str_starts_with((string)$cohort->idnumber, 'TM:' . $tenant->trustcode . ':COHORT:')) {
            throw new \invalid_parameter_exception('Cohort belongs to another tenant.');
        }
        if (!$DB->record_exists('cohort_members', ['cohortid' => $cohortid, 'userid' => $userid])) {
            cohort_add_member($cohortid, $userid);
        }
        (new audit_service())->record(
            (int)$tenant->id,
            'access.cohort_member.assigned',
            'success',
            ['cohortid' => $cohortid],
            ['entitytable' => 'user', 'entityid' => $userid, 'targetcomponent' => 'core/cohort', 'targetid' => $cohortid],
        );
    }

    /**
     * Ensure a native course group.
     *
     * @param object $tenant Tenant.
     * @param int $courseid Tenant course.
     * @param string $externalid External ID.
     * @param string $name Name.
     * @return int Group ID.
     */
    public function ensure_group(object $tenant, int $courseid, string $externalid, string $name): int {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/group/lib.php');
        $this->require_company_course($tenant, $courseid);
        $idnumber = $this->key($tenant, 'GROUP', $externalid);
        $existing = $DB->get_record('groups', ['courseid' => $courseid, 'idnumber' => $idnumber]);
        $data = (object)[
            'courseid' => $courseid,
            'name' => $name,
            'idnumber' => $idnumber,
            'description' => '',
            'descriptionformat' => FORMAT_PLAIN,
        ];
        if ($existing) {
            $data->id = $existing->id;
            groups_update_group($data);
            $groupid = (int)$existing->id;
        } else {
            $groupid = (int)groups_create_group($data);
        }
        $this->record_mapping($tenant, 'core/group', $idnumber, $groupid, 'groups');
        return $groupid;
    }

    /**
     * Add a same-tenant user to a native group.
     *
     * @param object $tenant Tenant.
     * @param int $groupid Group.
     * @param int $userid User.
     */
    public function add_group_member(object $tenant, int $groupid, int $userid): void {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/group/lib.php');
        $this->require_company_user($tenant, $userid);
        $group = $DB->get_record('groups', ['id' => $groupid], '*', MUST_EXIST);
        $this->require_company_course($tenant, (int)$group->courseid);
        if (!$DB->record_exists('groups_members', ['groupid' => $groupid, 'userid' => $userid])) {
            groups_add_member($groupid, $userid);
        }
        (new audit_service())->record(
            (int)$tenant->id,
            'access.group_member.assigned',
            'success',
            ['groupid' => $groupid],
            ['entitytable' => 'user', 'entityid' => $userid, 'targetcomponent' => 'core/group', 'targetid' => $groupid],
        );
    }

    /**
     * Enrol a same-tenant user through the supported IOMAD wrapper.
     *
     * @param object $tenant Tenant.
     * @param int $courseid Course.
     * @param int $userid User.
     * @param int $roleid Course role.
     * @param int $groupid Optional group.
     */
    public function enrol_user(
        object $tenant,
        int $courseid,
        int $userid,
        int $roleid,
        int $groupid = 0,
    ): void {
        global $DB;

        $this->require_company_user($tenant, $userid);
        $this->require_company_course($tenant, $courseid);
        if ($groupid > 0 && !$DB->record_exists('groups', ['id' => $groupid, 'courseid' => $courseid])) {
            throw new \invalid_parameter_exception('The selected group belongs to another course.');
        }
        company_user::enrol($userid, [$courseid], (int)$tenant->companyid, $roleid, $groupid);
        (new audit_service())->record(
            (int)$tenant->id,
            'access.user.enrolled',
            'success',
            ['courseid' => $courseid, 'roleid' => $roleid, 'groupid' => $groupid],
            ['entitytable' => 'user', 'entityid' => $userid, 'targetcomponent' => 'core/course', 'targetid' => $courseid],
        );
    }

    /**
     * Require native company membership.
     *
     * @param object $tenant Tenant.
     * @param int $userid User.
     */
    private function require_company_user(object $tenant, int $userid): void {
        global $DB;
        if (
            !$DB->record_exists('local_iomad_company_users', [
                'companyid' => $tenant->companyid,
                'userid' => $userid,
            ])
        ) {
            throw new \invalid_parameter_exception('User belongs to another tenant.');
        }
    }

    /**
     * Require native company-course assignment.
     *
     * @param object $tenant Tenant.
     * @param int $courseid Course.
     */
    private function require_company_course(object $tenant, int $courseid): void {
        global $DB;
        if (
            !$DB->record_exists('local_iomad_company_courses', [
                'companyid' => $tenant->companyid,
                'courseid' => $courseid,
            ])
        ) {
            throw new \invalid_parameter_exception('Course belongs to another tenant.');
        }
    }

    /**
     * Stable bounded native ID.
     *
     * @param object $tenant Tenant.
     * @param string $type Type.
     * @param string $externalid External ID.
     * @return string
     */
    private function key(object $tenant, string $type, string $externalid): string {
        if (!catalog::valid_external_key($externalid)) {
            throw new \invalid_parameter_exception('Invalid stable external ID.');
        }
        $key = 'TM:' . $tenant->trustcode . ':' . $type . ':' . $externalid;
        return strlen($key) <= 100
            ? $key
            : substr($key, 0, 67) . ':' . substr(hash('sha256', $key), 0, 32);
    }

    /**
     * Read back and map a directly managed native access object.
     *
     * @param object $tenant Tenant.
     * @param string $component Component.
     * @param string $externalkey External key.
     * @param int $targetid Native ID.
     * @param string $table Native table.
     */
    private function record_mapping(
        object $tenant,
        string $component,
        string $externalkey,
        int $targetid,
        string $table,
    ): void {
        global $DB;

        $native = $DB->get_record($table, ['id' => $targetid], '*', MUST_EXIST);
        $managed = field_ownership::select($component, $native);
        (new mapping_repository())->save(
            (int)$tenant->id,
            0,
            new projection_result(
                $component,
                $externalkey,
                $targetid,
                field_ownership::for_component($component),
                $managed,
                $managed,
            ),
        );
        (new audit_service())->record(
            (int)$tenant->id,
            'access.native.saved',
            'success',
            ['component' => $component],
            ['targetcomponent' => $component, 'targetid' => $targetid],
        );
    }
}
