<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantanalytics\local;

/**
 * Tenant-safe selector options for report forms.
 *
 * @package    local_tenantanalytics
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class filter_options {
    /**
     * Create tenant-safe options.
     *
     * @param tenant_scope $scope Scope.
     */
    public function __construct(private readonly tenant_scope $scope) {
    }

    /**
     * Return courses visible in scope.
     *
     * @return array<int,string>
     */
    public function courses(): array {
        global $DB;

        if ($this->scope->is_ownonly()) {
            $sql = "SELECT DISTINCT c.id, c.fullname
                      FROM {course} c
                      JOIN {local_iomad_tracks} lit ON lit.courseid = c.id
                     WHERE lit.userid = :userid
                  ORDER BY c.fullname";
            $records = $DB->get_records_sql($sql, ['userid' => $this->scope->get_requesterid()]);
        } else {
            [$companysql, $params] = $this->scope->company_predicate('cc.companyid');
            $sql = "SELECT DISTINCT c.id, c.fullname
                      FROM {course} c
                      JOIN {local_iomad_company_courses} cc ON cc.courseid = c.id
                     WHERE {$companysql}
                  ORDER BY c.fullname";
            $records = $DB->get_records_sql($sql, $params);
        }
        $options = [0 => get_string('all')];
        foreach ($records as $record) {
            $options[(int)$record->id] = format_string($record->fullname);
        }
        return $options;
    }

    /**
     * Return cohorts containing scoped users.
     *
     * @return array<int,string>
     */
    public function cohorts(): array {
        global $DB;

        [$usersql, $params] = $this->scope->user_predicate('cm.userid');
        $sql = "SELECT DISTINCT ch.id, ch.name
                  FROM {cohort} ch
                  JOIN {cohort_members} cm ON cm.cohortid = ch.id
                 WHERE {$usersql}
              ORDER BY ch.name";
        $options = [0 => get_string('all')];
        foreach ($DB->get_records_sql($sql, $params) as $record) {
            $options[(int)$record->id] = format_string($record->name);
        }
        return $options;
    }

    /**
     * Return groups containing scoped users.
     *
     * @return array<int,string>
     */
    public function groups(): array {
        global $DB;

        [$usersql, $params] = $this->scope->user_predicate('gm.userid');
        [$coursesql, $params] = $this->scope->course_predicate('g.courseid', $params);
        $sql = "SELECT DISTINCT g.id, g.name, c.fullname AS coursename
                  FROM {groups} g
                  JOIN {groups_members} gm ON gm.groupid = g.id
                  JOIN {course} c ON c.id = g.courseid
                 WHERE {$usersql}
                   AND {$coursesql}
              ORDER BY c.fullname, g.name";
        $options = [0 => get_string('all')];
        foreach ($DB->get_records_sql($sql, $params) as $record) {
            $options[(int)$record->id] = format_string($record->coursename)
                . ': ' . format_string($record->name);
        }
        return $options;
    }
}
