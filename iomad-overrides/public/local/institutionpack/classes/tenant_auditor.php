<?php
// This file is part of IOMAD - http://www.iomad.org/

namespace local_institutionpack;

defined('MOODLE_INTERNAL') || die();

/**
 * Read-only cross-company integrity audit.
 */
final class tenant_auditor {
    /** @var int Maximum references accepted in a report. */
    private const MAX_REFERENCE_LIMIT = 1000;

    /**
     * Run strict, read-only tenant integrity checks.
     *
     * @param int $maxreferences Maximum hashed references per check.
     * @param bool $writereport Persist the report below dataroot.
     * @return array
     */
    public function run(int $maxreferences = 100, bool $writereport = true): array {
        if ($maxreferences < 0 || $maxreferences > self::MAX_REFERENCE_LIMIT) {
            throw new \InvalidArgumentException('Reference limit must be between 0 and 1000.');
        }

        $checks = [];
        foreach ($this->definitions() as $name => $definition) {
            $checks[$name] = $this->run_check(
                $name,
                $definition['sql'],
                $definition['params'],
                $maxreferences
            );
        }

        $anomalies = array_sum(array_column($checks, 'anomalies'));
        $report = [
            'ok' => $anomalies === 0,
            'mode' => 'strict-isolation-check',
            'read_only' => true,
            'generated_at' => gmdate(DATE_ATOM),
            'anomalies' => $anomalies,
            'checks' => $checks,
            'repair_performed' => false,
        ];
        if ($writereport) {
            $report['audit_report'] = audit_log::write('tenant-isolation', $report);
        }
        return $report;
    }

    /**
     * Execute one check without exposing database identifiers.
     *
     * @param string $name Check name.
     * @param string $sql Portable Moodle SQL.
     * @param array $params Query parameters.
     * @param int $maxreferences Maximum hashes.
     * @return array
     */
    private function run_check(
        string $name,
        string $sql,
        array $params,
        int $maxreferences
    ): array {
        global $CFG, $DB;

        $salt = (string)($CFG->passwordsaltmain ?? $CFG->wwwroot);
        $count = 0;
        $references = [];
        $recordset = $DB->get_recordset_sql($sql, $params);
        foreach ($recordset as $record) {
            $count++;
            if (count($references) >= $maxreferences) {
                continue;
            }
            $values = (array)$record;
            ksort($values);
            $references[] = hash_hmac(
                'sha256',
                $name . ':' . json_encode($values, JSON_UNESCAPED_SLASHES),
                $salt
            );
        }
        $recordset->close();

        return [
            'status' => $count === 0 ? 'pass' : 'fail',
            'anomalies' => $count,
            'references' => $references,
            'references_truncated' => $count > count($references),
        ];
    }

    /**
     * Define integrity checks using reads only.
     *
     * @return array
     */
    private function definitions(): array {
        $enrolmentaccess = $this->course_access_predicate('ue.userid', 'e.courseid');
        $gradeaccess = $this->course_access_predicate('gg.userid', 'gi.courseid');
        $baseparams = [
            'siteid' => SITEID,
            'sharedall' => 1,
            'sharedselected' => 2,
        ];

        return [
            'course_enrolment_scope' => [
                'sql' => "SELECT DISTINCT ue.userid AS scopeuser, e.courseid AS scopecourse
                            FROM {user_enrolments} ue
                            JOIN {enrol} e ON e.id = ue.enrolid
                           WHERE ue.status = 0
                             AND e.status = 0
                             AND e.courseid <> :siteid
                             AND EXISTS (
                                 SELECT 1
                                   FROM {local_iomad_company_courses} assigned
                                  WHERE assigned.courseid = e.courseid
                             )
                             AND NOT ({$enrolmentaccess})",
                'params' => $baseparams,
            ],
            'grade_scope' => [
                'sql' => "SELECT DISTINCT gg.userid AS scopeuser, gi.courseid AS scopecourse
                            FROM {grade_grades} gg
                            JOIN {grade_items} gi ON gi.id = gg.itemid
                           WHERE gi.courseid IS NOT NULL
                             AND gi.courseid <> :siteid
                             AND EXISTS (
                                 SELECT 1
                                   FROM {local_iomad_company_courses} assigned
                                  WHERE assigned.courseid = gi.courseid
                             )
                             AND NOT ({$gradeaccess})",
                'params' => $baseparams,
            ],
            'company_group_membership_scope' => [
                'sql' => "SELECT DISTINCT gm.userid AS scopeuser,
                                          ccg.companyid AS scopecompany,
                                          ccg.courseid AS scopecourse
                            FROM {groups_members} gm
                            JOIN {local_iomad_company_course_groups} ccg ON ccg.groupid = gm.groupid
                           WHERE NOT EXISTS (
                                 SELECT 1
                                   FROM {local_iomad_company_users} cu
                                  WHERE cu.userid = gm.userid
                                    AND cu.companyid = ccg.companyid
                                    AND cu.suspended = 0
                             )",
                'params' => [],
            ],
            'license_assignment_scope' => [
                'sql' => "SELECT DISTINCT clu.userid AS scopeuser,
                                          cl.companyid AS scopecompany,
                                          clu.courseid AS scopecourse
                            FROM {local_iomad_company_license_users} clu
                            JOIN {local_iomad_company_licenses} cl ON cl.id = clu.licenseid
                           WHERE cl.companyid IS NOT NULL
                             AND NOT EXISTS (
                                 SELECT 1
                                   FROM {local_iomad_company_users} cu
                                  WHERE cu.userid = clu.userid
                                    AND cu.companyid = cl.companyid
                                    AND cu.suspended = 0
                             )",
                'params' => [],
            ],
            'company_context_role_scope' => [
                'sql' => "SELECT DISTINCT ra.userid AS scopeuser,
                                          ctx.instanceid AS scopecompany,
                                          ra.roleid AS scoperole
                            FROM {role_assignments} ra
                            JOIN {context} ctx ON ctx.id = ra.contextid
                           WHERE ctx.contextlevel = :companycontext
                             AND NOT EXISTS (
                                 SELECT 1
                                   FROM {local_iomad_company_users} cu
                                  WHERE cu.userid = ra.userid
                                    AND cu.companyid = ctx.instanceid
                                    AND cu.suspended = 0
                             )",
                'params' => ['companycontext' => 13],
            ],
            'company_user_department_scope' => [
                'sql' => "SELECT cu.userid AS scopeuser,
                                 cu.companyid AS scopecompany,
                                 cu.departmentid AS scopedepartment
                            FROM {local_iomad_company_users} cu
                            JOIN {local_iomad_company_departments} department
                              ON department.id = cu.departmentid
                           WHERE department.companyid <> cu.companyid",
                'params' => [],
            ],
            'company_course_department_scope' => [
                'sql' => "SELECT cc.courseid AS scopecourse,
                                 cc.companyid AS scopecompany,
                                 cc.departmentid AS scopedepartment
                            FROM {local_iomad_company_courses} cc
                            JOIN {local_iomad_company_departments} department
                              ON department.id = cc.departmentid
                           WHERE department.companyid <> cc.companyid",
                'params' => [],
            ],
        ];
    }

    /**
     * Build the valid company access predicate for an aliased user and course.
     *
     * @param string $userfield SQL field containing a user ID.
     * @param string $coursefield SQL field containing a course ID.
     * @return string
     */
    private function course_access_predicate(string $userfield, string $coursefield): string {
        return "EXISTS (
                    SELECT 1
                      FROM {local_iomad_courses} globalcourse
                     WHERE globalcourse.courseid = {$coursefield}
                       AND globalcourse.shared = :sharedall
                )
                OR EXISTS (
                    SELECT 1
                      FROM {local_iomad_company_users} accessuser
                     WHERE accessuser.userid = {$userfield}
                       AND accessuser.suspended = 0
                       AND (
                           EXISTS (
                               SELECT 1
                                 FROM {local_iomad_company_courses} directcourse
                                WHERE directcourse.courseid = {$coursefield}
                                  AND directcourse.companyid = accessuser.companyid
                           )
                           OR EXISTS (
                               SELECT 1
                                 FROM {local_iomad_courses} selectedcourse
                                 JOIN {local_iomad_company_shared_courses} selectedshare
                                   ON selectedshare.courseid = selectedcourse.courseid
                                WHERE selectedcourse.courseid = {$coursefield}
                                  AND selectedcourse.shared = :sharedselected
                                  AND selectedshare.companyid = accessuser.companyid
                           )
                           OR EXISTS (
                               SELECT 1
                                 FROM {local_iomad_company_license_users} accesslicense
                                 JOIN {local_iomad_company_licenses} companylicense
                                   ON companylicense.id = accesslicense.licenseid
                                WHERE accesslicense.userid = {$userfield}
                                  AND accesslicense.courseid = {$coursefield}
                                  AND companylicense.companyid = accessuser.companyid
                           )
                       )
                )";
    }
}
