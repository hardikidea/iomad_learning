<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\local;

/**
 * Read-only tenant views over authoritative IOMAD and Moodle records.
 *
 * This service deliberately does not persist native data. Its purpose is to
 * give Tenant Master screens one tenant-scoped view of the records that remain
 * owned by IOMAD and Moodle.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class native_data_service {
    /**
     * Native IOMAD departments with current parent and membership counts.
     *
     * @param object $tenant Tenant.
     * @return object[]
     */
    public function departments(object $tenant): array {
        global $DB;

        return array_values($DB->get_records_sql(
            "SELECT d.*, parent.name AS parentname,
                    (SELECT COUNT(1)
                       FROM {local_iomad_company_users} cu
                      WHERE cu.companyid = d.companyid
                        AND cu.departmentid = d.id) AS usercount,
                    (SELECT COUNT(1)
                       FROM {local_iomad_company_courses} cc
                      WHERE cc.companyid = d.companyid
                        AND cc.departmentid = d.id) AS coursecount
               FROM {local_iomad_company_departments} d
          LEFT JOIN {local_iomad_company_departments} parent
                 ON parent.id = d.parentid
                AND parent.companyid = d.companyid
              WHERE d.companyid = :companyid
           ORDER BY parent.name, d.name, d.id",
            ['companyid' => $tenant->companyid],
        ));
    }

    /**
     * Native company users with current IOMAD membership attributes.
     *
     * @param object $tenant Tenant.
     * @param string $search Optional display search.
     * @return object[]
     */
    public function users(object $tenant, string $search = ''): array {
        global $DB;

        $records = array_values($DB->get_records_sql(
            "SELECT u.id, u.username, u.idnumber, u.firstname, u.lastname, u.email,
                    u.suspended, cu.suspended AS companysuspended, cu.managertype,
                    cu.educator, cu.departmentid, d.name AS departmentname
               FROM {local_iomad_company_users} cu
               JOIN {user} u
                 ON u.id = cu.userid
                AND u.deleted = 0
          LEFT JOIN {local_iomad_company_departments} d
                 ON d.id = cu.departmentid
                AND d.companyid = cu.companyid
              WHERE cu.companyid = :companyid
           ORDER BY u.lastname, u.firstname, u.id",
            ['companyid' => $tenant->companyid],
            0,
            500,
        ));
        $search = \core_text::strtolower(trim($search));
        if ($search === '') {
            return $records;
        }
        return array_values(array_filter($records, static function (object $record) use ($search): bool {
            $haystack = \core_text::strtolower(implode(' ', [
                $record->firstname,
                $record->lastname,
                $record->username,
                $record->idnumber,
                $record->email,
                $record->departmentname,
            ]));
            return str_contains($haystack, $search);
        }));
    }

    /**
     * Native courses assigned to the company.
     *
     * @param object $tenant Tenant.
     * @param string $search Optional display search.
     * @param string $visibility all, visible or hidden.
     * @return object[]
     */
    public function courses(object $tenant, string $search = '', string $visibility = 'all'): array {
        global $DB;

        if (!in_array($visibility, ['all', 'visible', 'hidden'], true)) {
            throw new \invalid_parameter_exception('Invalid course visibility filter.');
        }
        $records = array_values($DB->get_records_sql(
            "SELECT c.id, c.fullname, c.shortname, c.idnumber, c.visible, c.startdate, c.enddate,
                    c.category, category.name AS categoryname, cc.departmentid,
                    department.name AS departmentname, options.autoenrol, options.mandatory,
                    options.validlength, options.warnexpire, options.warncompletion,
                    mapping.id AS mappingid, mapping.externalkey, mapping.status AS mappingstatus,
                    mapping.lastsynced
               FROM {local_iomad_company_courses} cc
               JOIN {course} c ON c.id = cc.courseid
               JOIN {course_categories} category ON category.id = c.category
          LEFT JOIN {local_iomad_company_departments} department
                 ON department.id = cc.departmentid
                AND department.companyid = cc.companyid
          LEFT JOIN {local_iomad_company_course_options} options
                 ON options.companyid = cc.companyid
                AND options.courseid = cc.courseid
          LEFT JOIN {local_tenantmaster_mapping} mapping
                 ON mapping.tenantid = :tenantid
                AND mapping.component = :component
                AND mapping.targetid = c.id
              WHERE cc.companyid = :companyid
           ORDER BY category.name, c.fullname, c.id",
            [
                'tenantid' => $tenant->id,
                'component' => 'core/course',
                'companyid' => $tenant->companyid,
            ],
            0,
            500,
        ));
        $search = \core_text::strtolower(trim($search));
        return array_values(array_filter($records, static function (object $record) use ($search, $visibility): bool {
            if ($visibility === 'visible' && empty($record->visible)) {
                return false;
            }
            if ($visibility === 'hidden' && !empty($record->visible)) {
                return false;
            }
            if ($search === '') {
                return true;
            }
            $haystack = \core_text::strtolower(implode(' ', [
                $record->fullname,
                $record->shortname,
                $record->idnumber,
                $record->categoryname,
                $record->departmentname,
                $record->externalkey,
            ]));
            return str_contains($haystack, $search);
        }));
    }

    /**
     * Native cohorts explicitly managed for this tenant.
     *
     * @param object $tenant Tenant.
     * @return object[]
     */
    public function cohorts(object $tenant): array {
        global $DB;

        return array_values($DB->get_records_sql(
            "SELECT cohort.id, cohort.name, cohort.idnumber, cohort.visible,
                    mapping.status AS mappingstatus, mapping.lastsynced,
                    (SELECT COUNT(1)
                       FROM {cohort_members} members
                      WHERE members.cohortid = cohort.id) AS membercount
               FROM {local_tenantmaster_mapping} mapping
               JOIN {cohort} cohort ON cohort.id = mapping.targetid
              WHERE mapping.tenantid = :tenantid
                AND mapping.component = :component
           ORDER BY cohort.name, cohort.id",
            ['tenantid' => $tenant->id, 'component' => 'core/cohort'],
        ));
    }

    /**
     * Native groups in courses assigned to this company.
     *
     * @param object $tenant Tenant.
     * @return object[]
     */
    public function groups(object $tenant): array {
        global $DB;

        return array_values($DB->get_records_sql(
            "SELECT groups.id, groups.name, groups.idnumber, groups.courseid,
                    course.fullname AS coursename,
                    mapping.status AS mappingstatus, mapping.lastsynced,
                    (SELECT COUNT(1)
                       FROM {groups_members} members
                      WHERE members.groupid = groups.id) AS membercount
               FROM {local_iomad_company_courses} cc
               JOIN {course} course ON course.id = cc.courseid
               JOIN {groups} groups ON groups.courseid = course.id
          LEFT JOIN {local_tenantmaster_mapping} mapping
                 ON mapping.tenantid = :tenantid
                AND mapping.component = :component
                AND mapping.targetid = groups.id
              WHERE cc.companyid = :companyid
           ORDER BY course.fullname, groups.name, groups.id",
            [
                'tenantid' => $tenant->id,
                'component' => 'core/group',
                'companyid' => $tenant->companyid,
            ],
        ));
    }

    /**
     * Native enrolment instances on company courses.
     *
     * @param object $tenant Tenant.
     * @return object[]
     */
    public function enrolments(object $tenant): array {
        global $DB;

        return array_values($DB->get_records_sql(
            "SELECT enrol.id, enrol.enrol, enrol.name, enrol.status, enrol.courseid,
                    course.fullname AS coursename, role.name AS rolename, role.shortname AS roleshortname,
                    (SELECT COUNT(1)
                       FROM {user_enrolments} ue
                      WHERE ue.enrolid = enrol.id
                        AND ue.status = 0) AS activecount
               FROM {local_iomad_company_courses} cc
               JOIN {course} course ON course.id = cc.courseid
               JOIN {enrol} enrol ON enrol.courseid = course.id
          LEFT JOIN {role} role ON role.id = enrol.roleid
              WHERE cc.companyid = :companyid
           ORDER BY course.fullname, enrol.sortorder, enrol.id",
            ['companyid' => $tenant->companyid],
        ));
    }

    /**
     * Native user profile fields enabled for the company.
     *
     * @param object $tenant Tenant.
     * @return object[]
     */
    public function user_profile_fields(object $tenant): array {
        global $DB;

        $categoryid = (int)$DB->get_field(
            'local_iomad_companies',
            'profilecategoryid',
            ['id' => $tenant->companyid],
            MUST_EXIST,
        );
        if ($categoryid <= 0) {
            return [];
        }
        return array_values($DB->get_records(
            'user_info_field',
            ['categoryid' => $categoryid],
            'sortorder, name, id',
            'id,name,shortname,datatype,required,locked,visible',
        ));
    }

    /**
     * Native Moodle course custom fields created by Tenant Master.
     *
     * @return object[]
     */
    public function course_profile_fields(): array {
        global $DB;

        [$insql, $params] = $DB->get_in_or_equal(
            array_keys(course_metadata_service::FIELDS),
            SQL_PARAMS_NAMED,
            'tmcoursefield',
        );
        return array_values($DB->get_records_select(
            'customfield_field',
            "shortname $insql",
            $params,
            'sortorder, name, id',
            'id,name,shortname,type',
        ));
    }
}
