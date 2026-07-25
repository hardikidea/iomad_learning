<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantanalytics\local;

/**
 * Tenant-scoped analytics engine backed by IOMAD tracking and Moodle logs.
 *
 * @package    local_tenantanalytics
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class report_engine {
    /** Maximum standard-log events processed by one interactive report. */
    private const MAX_EVENTS = 100000;

    /** Maximum tenant users processed by one interactive report. */
    private const MAX_USERS = 5000;

    /**
     * Create report engine.
     *
     * @param sessionizer|null $sessionizer Active-time estimator.
     */
    public function __construct(private readonly ?sessionizer $sessionizer = null) {
    }

    /**
     * Generate a maintained report.
     *
     * @param string $reportkey Stable report key.
     * @param tenant_scope $scope Authorised tenant or own-data scope.
     * @param array $filters Input filters.
     * @return report_result
     */
    public function generate(string $reportkey, tenant_scope $scope, array $filters = []): report_result {
        if (!report_catalog::exists($reportkey)) {
            throw new \invalid_parameter_exception('Unknown tenant analytics report.');
        }
        $filters = $this->normalise_filters($filters);
        return match ($reportkey) {
            'course_engagement' => $this->course_engagement($scope, $filters),
            'student_engagement' => $this->student_engagement($scope, $filters),
            'learner' => $this->learner_report($scope, $filters),
            'time_site' => $this->time_site($scope, $filters),
            'time_course' => $this->time_course($scope, $filters),
            'time_activity' => $this->time_activity($scope, $filters),
            'visits' => $this->visits($scope, $filters),
            'completion' => $this->completion($scope, $filters),
            'license_usage' => $this->license_usage($scope, $filters),
            'cohort_group' => $this->cohort_group($scope, $filters),
        };
    }

    /**
     * Normalize and bound report filters.
     *
     * @param array $filters Raw filters.
     * @return array
     */
    public function normalise_filters(array $filters): array {
        $now = time();
        $until = min($now, max(1, (int)($filters['until'] ?? $now)));
        $since = max(0, (int)($filters['since'] ?? ($until - (30 * DAYSECS))));
        if ($since > $until) {
            throw new \invalid_parameter_exception('Report start date must not follow the end date.');
        }
        if (($until - $since) > (366 * DAYSECS)) {
            throw new \invalid_parameter_exception('Report date range cannot exceed 366 days.');
        }
        return [
            'since' => $since,
            'until' => $until,
            'courseid' => max(0, (int)($filters['courseid'] ?? 0)),
            'cohortid' => max(0, (int)($filters['cohortid'] ?? 0)),
            'groupid' => max(0, (int)($filters['groupid'] ?? 0)),
        ];
    }

    /**
     * Course-level engagement and completion summary.
     *
     * @param tenant_scope $scope Scope.
     * @param array $filters Filters.
     * @return report_result
     */
    private function course_engagement(tenant_scope $scope, array $filters): report_result {
        $events = $this->events($scope, $filters);
        $aggregates = [];
        foreach ($events as $event) {
            $courseid = (int)$event['courseid'];
            if ($courseid <= 0) {
                continue;
            }
            $aggregates[$courseid] ??= ['events' => [], 'users' => []];
            $aggregates[$courseid]['events'][] = $event;
            $aggregates[$courseid]['users'][(int)$event['userid']] = true;
        }
        $tracks = $this->tracks($scope, $filters);
        foreach ($tracks as $track) {
            $courseid = (int)$track->courseid;
            $aggregates[$courseid] ??= ['events' => [], 'users' => []];
            $aggregates[$courseid]['users'][(int)$track->userid] = true;
        }

        $courses = $this->courses(array_keys($aggregates));
        $rows = [];
        foreach ($aggregates as $courseid => $aggregate) {
            $session = $this->get_sessionizer()->aggregate($aggregate['events'], ['courseid']);
            $time = $session[(string)$courseid]['seconds'] ?? 0;
            $coursetracks = array_filter(
                $tracks,
                static fn(object $track): bool => (int)$track->courseid === (int)$courseid
            );
            $completed = count(array_filter(
                $coursetracks,
                static fn(object $track): bool => !empty($track->timecompleted)
            ));
            $learners = count($aggregate['users']);
            $rows[] = [
                'course' => $courses[$courseid] ?? get_string('unknowncourse', 'local_tenantanalytics'),
                'learners' => $learners,
                'events' => count($aggregate['events']),
                'estimatedtime' => format_time($time),
                'completions' => $completed,
                'completionrate' => $learners > 0 ? round(($completed / $learners) * 100, 1) . '%' : '0%',
            ];
        }
        usort($rows, static fn(array $a, array $b): int => strcasecmp($a['course'], $b['course']));
        return $this->result('course_engagement', [
            'course' => get_string('course'),
            'learners' => get_string('learners', 'local_tenantanalytics'),
            'events' => get_string('events', 'local_tenantanalytics'),
            'estimatedtime' => get_string('estimatedtime', 'local_tenantanalytics'),
            'completions' => get_string('completions', 'local_tenantanalytics'),
            'completionrate' => get_string('completionrate', 'local_tenantanalytics'),
        ], $rows, $filters);
    }

    /**
     * Learner engagement with deterministic risk flags.
     *
     * @param tenant_scope $scope Scope.
     * @param array $filters Filters.
     * @return report_result
     */
    private function student_engagement(tenant_scope $scope, array $filters): report_result {
        $users = $this->users($scope, $filters);
        $events = $this->events($scope, $filters);
        $tracks = $this->tracks($scope, $filters);
        $userevents = [];
        foreach ($events as $event) {
            $userevents[(int)$event['userid']][] = $event;
        }
        $usertracks = [];
        foreach ($tracks as $track) {
            $usertracks[(int)$track->userid][] = $track;
        }

        $rows = [];
        foreach ($users as $user) {
            $userid = (int)$user->id;
            $identity = $this->user_identity($scope, $user, $userid);
            $eventsforuser = $userevents[$userid] ?? [];
            $tracksforuser = $usertracks[$userid] ?? [];
            $session = $this->get_sessionizer()->aggregate($eventsforuser, ['userid']);
            $activity = $session[(string)$userid] ?? ['seconds' => 0, 'last' => 0];
            $completed = count(array_filter(
                $tracksforuser,
                static fn(object $track): bool => !empty($track->timecompleted)
            ));
            $courses = [];
            foreach ($eventsforuser as $event) {
                if ((int)$event['courseid'] > 0) {
                    $courses[(int)$event['courseid']] = true;
                }
            }
            foreach ($tracksforuser as $track) {
                $courses[(int)$track->courseid] = true;
            }
            $rows[] = [
                'learner' => $identity['learner'],
                'email' => $identity['email'],
                'courses' => count($courses),
                'events' => count($eventsforuser),
                'estimatedtime' => format_time((int)$activity['seconds']),
                'completions' => $completed,
                'lastactivity' => $activity['last'] ? userdate((int)$activity['last']) : get_string('never'),
                'risk' => $this->risk_label(count($eventsforuser), (int)$activity['last'], $completed, $filters['until']),
            ];
        }
        usort($rows, static fn(array $a, array $b): int => strcasecmp($a['learner'], $b['learner']));
        return $this->result('student_engagement', [
            'learner' => get_string('learner', 'local_tenantanalytics'),
            'email' => get_string('email'),
            'courses' => get_string('courses'),
            'events' => get_string('events', 'local_tenantanalytics'),
            'estimatedtime' => get_string('estimatedtime', 'local_tenantanalytics'),
            'completions' => get_string('completions', 'local_tenantanalytics'),
            'lastactivity' => get_string('lastactivity', 'local_tenantanalytics'),
            'risk' => get_string('riskflag', 'local_tenantanalytics'),
        ], $rows, $filters, ['riskmodel' => 'deterministic-v1']);
    }

    /**
     * User-course learning record report.
     *
     * @param tenant_scope $scope Scope.
     * @param array $filters Filters.
     * @return report_result
     */
    private function learner_report(tenant_scope $scope, array $filters): report_result {
        $tracks = $this->tracks($scope, $filters);
        $users = $this->users_by_id(
            array_map(static fn(object $track): int => (int)$track->userid, $tracks),
            $scope
        );
        $courses = $this->courses(array_map(static fn(object $track): int => (int)$track->courseid, $tracks));
        $rows = [];
        foreach ($tracks as $track) {
            $user = $users[(int)$track->userid] ?? null;
            $identity = $this->user_identity($scope, $user, (int)$track->userid);
            $rows[] = [
                'learner' => $identity['learner'],
                'email' => $identity['email'],
                'course' => $courses[(int)$track->courseid] ?? (string)$track->coursename,
                'status' => $this->track_status($track, $filters['until']),
                'enrolled' => $this->date_or_dash($track->timeenrolled),
                'started' => $this->date_or_dash($track->timestarted),
                'completed' => $this->date_or_dash($track->timecompleted),
                'expires' => $this->date_or_dash($track->timeexpires),
                'score' => round((float)$track->finalscore, 2),
            ];
        }
        return $this->result('learner', [
            'learner' => get_string('learner', 'local_tenantanalytics'),
            'email' => get_string('email'),
            'course' => get_string('course'),
            'status' => get_string('status'),
            'enrolled' => get_string('enrolled', 'local_tenantanalytics'),
            'started' => get_string('started', 'local_tenantanalytics'),
            'completed' => get_string('completed', 'local_tenantanalytics'),
            'expires' => get_string('expires', 'local_tenantanalytics'),
            'score' => get_string('score', 'local_tenantanalytics'),
        ], $rows, $filters);
    }

    /**
     * Estimated active time per learner across the site.
     *
     * @param tenant_scope $scope Scope.
     * @param array $filters Filters.
     * @return report_result
     */
    private function time_site(tenant_scope $scope, array $filters): report_result {
        $events = $this->events($scope, $filters);
        $aggregates = $this->get_sessionizer()->aggregate($events, ['userid']);
        $users = $this->users_by_id(array_map(
            static fn(array $event): int => (int)$event['userid'],
            $events
        ), $scope);
        $rows = [];
        foreach ($aggregates as $key => $aggregate) {
            $userid = (int)$key;
            $user = $users[$userid] ?? null;
            $identity = $this->user_identity($scope, $user, $userid);
            $rows[] = [
                'learner' => $identity['learner'],
                'email' => $identity['email'],
                'events' => $aggregate['events'],
                'estimatedtime' => format_time($aggregate['seconds']),
                'firstactivity' => userdate($aggregate['first']),
                'lastactivity' => userdate($aggregate['last']),
            ];
        }
        usort($rows, static fn(array $a, array $b): int => strcasecmp($a['learner'], $b['learner']));
        return $this->result('time_site', $this->time_columns(false, false), $rows, $filters);
    }

    /**
     * Estimated active time per learner and course.
     *
     * @param tenant_scope $scope Scope.
     * @param array $filters Filters.
     * @return report_result
     */
    private function time_course(tenant_scope $scope, array $filters): report_result {
        $events = array_values(array_filter(
            $this->events($scope, $filters),
            static fn(array $event): bool => (int)$event['courseid'] > 0
        ));
        $aggregates = $this->get_sessionizer()->aggregate($events, ['userid', 'courseid']);
        $users = $this->users_by_id(array_column($events, 'userid'), $scope);
        $courses = $this->courses(array_column($events, 'courseid'));
        $rows = [];
        foreach ($aggregates as $key => $aggregate) {
            [$userid, $courseid] = array_map('intval', explode(':', $key));
            $user = $users[$userid] ?? null;
            $identity = $this->user_identity($scope, $user, $userid);
            $rows[] = [
                'learner' => $identity['learner'],
                'email' => $identity['email'],
                'course' => $courses[$courseid] ?? get_string('unknowncourse', 'local_tenantanalytics'),
                'events' => $aggregate['events'],
                'estimatedtime' => format_time($aggregate['seconds']),
                'firstactivity' => userdate($aggregate['first']),
                'lastactivity' => userdate($aggregate['last']),
            ];
        }
        return $this->result('time_course', $this->time_columns(true, false), $rows, $filters);
    }

    /**
     * Estimated active time per learner and course-module context.
     *
     * @param tenant_scope $scope Scope.
     * @param array $filters Filters.
     * @return report_result
     */
    private function time_activity(tenant_scope $scope, array $filters): report_result {
        $events = array_values(array_filter(
            $this->events($scope, $filters),
            static fn(array $event): bool => (int)$event['contextlevel'] === CONTEXT_MODULE
                && (int)$event['contextinstanceid'] > 0
        ));
        $aggregates = $this->get_sessionizer()->aggregate(
            $events,
            ['userid', 'courseid', 'contextinstanceid']
        );
        $users = $this->users_by_id(array_column($events, 'userid'), $scope);
        $courses = $this->courses(array_column($events, 'courseid'));
        $modules = $this->modules(array_column($events, 'contextinstanceid'));
        $rows = [];
        foreach ($aggregates as $key => $aggregate) {
            [$userid, $courseid, $cmid] = array_map('intval', explode(':', $key));
            $user = $users[$userid] ?? null;
            $identity = $this->user_identity($scope, $user, $userid);
            $rows[] = [
                'learner' => $identity['learner'],
                'email' => $identity['email'],
                'course' => $courses[$courseid] ?? get_string('unknowncourse', 'local_tenantanalytics'),
                'activity' => $modules[$cmid] ?? get_string('unknownactivity', 'local_tenantanalytics'),
                'events' => $aggregate['events'],
                'estimatedtime' => format_time($aggregate['seconds']),
                'firstactivity' => userdate($aggregate['first']),
                'lastactivity' => userdate($aggregate['last']),
            ];
        }
        return $this->result('time_activity', $this->time_columns(true, true), $rows, $filters);
    }

    /**
     * Daily visits and event volume.
     *
     * @param tenant_scope $scope Scope.
     * @param array $filters Filters.
     * @return report_result
     */
    private function visits(tenant_scope $scope, array $filters): report_result {
        $days = [];
        foreach ($this->events($scope, $filters) as $event) {
            $day = userdate((int)$event['timecreated'], '%Y-%m-%d');
            $days[$day] ??= ['events' => 0, 'users' => []];
            $days[$day]['events']++;
            $days[$day]['users'][(int)$event['userid']] = true;
        }
        ksort($days);
        $rows = [];
        foreach ($days as $day => $aggregate) {
            $rows[] = [
                'date' => $day,
                'uniquelearners' => count($aggregate['users']),
                'events' => $aggregate['events'],
            ];
        }
        return $this->result('visits', [
            'date' => get_string('date'),
            'uniquelearners' => get_string('uniquelearners', 'local_tenantanalytics'),
            'events' => get_string('events', 'local_tenantanalytics'),
        ], $rows, $filters);
    }

    /**
     * Completion and recertification status.
     *
     * @param tenant_scope $scope Scope.
     * @param array $filters Filters.
     * @return report_result
     */
    private function completion(tenant_scope $scope, array $filters): report_result {
        $tracks = $this->tracks($scope, $filters);
        $users = $this->users_by_id(
            array_map(static fn(object $track): int => (int)$track->userid, $tracks),
            $scope
        );
        $courses = $this->courses(array_map(static fn(object $track): int => (int)$track->courseid, $tracks));
        $rows = [];
        foreach ($tracks as $track) {
            $user = $users[(int)$track->userid] ?? null;
            $identity = $this->user_identity($scope, $user, (int)$track->userid);
            $rows[] = [
                'learner' => $identity['learner'],
                'course' => $courses[(int)$track->courseid] ?? (string)$track->coursename,
                'status' => $this->track_status($track, $filters['until']),
                'completed' => $this->date_or_dash($track->timecompleted),
                'expires' => $this->date_or_dash($track->timeexpires),
                'score' => round((float)$track->finalscore, 2),
            ];
        }
        return $this->result('completion', [
            'learner' => get_string('learner', 'local_tenantanalytics'),
            'course' => get_string('course'),
            'status' => get_string('status'),
            'completed' => get_string('completed', 'local_tenantanalytics'),
            'expires' => get_string('expires', 'local_tenantanalytics'),
            'score' => get_string('score', 'local_tenantanalytics'),
        ], $rows, $filters);
    }

    /**
     * Tenant license inventory or learner allocations.
     *
     * @param tenant_scope $scope Scope.
     * @param array $filters Filters.
     * @return report_result
     */
    private function license_usage(tenant_scope $scope, array $filters): report_result {
        global $DB;

        if ($scope->has_department_restriction()) {
            [$usersql, $params] = $scope->user_predicate('clu.userid');
            [$companysql, $params] = $scope->company_predicate('cl.companyid', $params);
            $sql = "SELECT cl.id, cl.name, company.name AS companyname,
                           COUNT(DISTINCT clu.userid) AS allocatedusers,
                           SUM(CASE WHEN clu.isusing = 1 THEN 1 ELSE 0 END) AS inuse
                      FROM {local_iomad_company_license_users} clu
                      JOIN {local_iomad_company_licenses} cl ON cl.id = clu.licenseid
                      JOIN {local_iomad_companies} company ON company.id = cl.companyid
                     WHERE {$usersql}
                       AND {$companysql}
                  GROUP BY cl.id, cl.name, company.name
                  ORDER BY company.name, cl.name";
            $rows = array_map(static fn(object $license): array => [
                'company' => $license->companyname,
                'license' => $license->name,
                'allocatedusers' => (int)$license->allocatedusers,
                'inuse' => (int)$license->inuse,
            ], array_values($DB->get_records_sql($sql, $params)));
            return $this->result('license_usage', [
                'company' => get_string('company', 'local_tenantanalytics'),
                'license' => get_string('license', 'local_tenantanalytics'),
                'allocatedusers' => get_string('allocatedusers', 'local_tenantanalytics'),
                'inuse' => get_string('inuse', 'local_tenantanalytics'),
            ], $rows, $filters);
        }

        if ($scope->is_ownonly()) {
            $sql = "SELECT clu.id, cl.name, c.fullname AS coursename, clu.issuedate,
                           clu.timecompleted, clu.isusing
                      FROM {local_iomad_company_license_users} clu
                      JOIN {local_iomad_company_licenses} cl ON cl.id = clu.licenseid
                 LEFT JOIN {course} c ON c.id = clu.courseid
                     WHERE clu.userid = :userid";
            $params = ['userid' => $scope->get_requesterid()];
            if ($filters['courseid']) {
                $sql .= " AND clu.courseid = :licensecourseid";
                $params['licensecourseid'] = $filters['courseid'];
            }
            $records = $DB->get_records_sql($sql . ' ORDER BY cl.name, c.fullname', $params);
            $rows = array_map(static fn(object $record): array => [
                'license' => $record->name,
                'course' => $record->coursename ?? get_string('allcourses', 'local_tenantanalytics'),
                'issued' => $record->issuedate ? userdate((int)$record->issuedate) : '-',
                'completed' => $record->timecompleted ? userdate((int)$record->timecompleted) : '-',
                'inuse' => $record->isusing ? get_string('yes') : get_string('no'),
            ], array_values($records));
            return $this->result('license_usage', [
                'license' => get_string('license', 'local_tenantanalytics'),
                'course' => get_string('course'),
                'issued' => get_string('issued', 'local_tenantanalytics'),
                'completed' => get_string('completed', 'local_tenantanalytics'),
                'inuse' => get_string('inuse', 'local_tenantanalytics'),
            ], $rows, $filters);
        }

        [$companysql, $params] = $scope->company_predicate('cl.companyid');
        $sql = "SELECT cl.id, cl.name, cl.allocation, cl.used, cl.startdate, cl.expirydate,
                       company.name AS companyname
                  FROM {local_iomad_company_licenses} cl
                  JOIN {local_iomad_companies} company ON company.id = cl.companyid
                 WHERE {$companysql}
              ORDER BY company.name, cl.name";
        $rows = [];
        foreach ($DB->get_records_sql($sql, $params) as $license) {
            $remaining = max(0, (int)$license->allocation - (int)$license->used);
            $status = ((int)$license->expirydate > 0 && (int)$license->expirydate < $filters['until'])
                ? get_string('expired', 'local_tenantanalytics')
                : get_string('active');
            $rows[] = [
                'company' => $license->companyname,
                'license' => $license->name,
                'allocated' => (int)$license->allocation,
                'used' => (int)$license->used,
                'remaining' => $remaining,
                'startdate' => $this->date_or_dash($license->startdate),
                'expirydate' => $this->date_or_dash($license->expirydate),
                'status' => $status,
            ];
        }
        return $this->result('license_usage', [
            'company' => get_string('company', 'local_tenantanalytics'),
            'license' => get_string('license', 'local_tenantanalytics'),
            'allocated' => get_string('allocated', 'local_tenantanalytics'),
            'used' => get_string('used', 'local_tenantanalytics'),
            'remaining' => get_string('remaining', 'local_tenantanalytics'),
            'startdate' => get_string('startdate', 'local_tenantanalytics'),
            'expirydate' => get_string('expirydate', 'local_tenantanalytics'),
            'status' => get_string('status'),
        ], $rows, $filters);
    }

    /**
     * Cohort and group membership summary.
     *
     * @param tenant_scope $scope Scope.
     * @param array $filters Filters.
     * @return report_result
     */
    private function cohort_group(tenant_scope $scope, array $filters): report_result {
        global $DB;

        [$cohortscope, $cohortparams] = $scope->user_predicate('cm.userid');
        $cohortsql = "SELECT ch.id, ch.name, COUNT(DISTINCT cm.userid) AS members
                        FROM {cohort} ch
                        JOIN {cohort_members} cm ON cm.cohortid = ch.id
                       WHERE {$cohortscope}";
        if ($filters['cohortid']) {
            $cohortsql .= ' AND ch.id = :selectedcohort';
            $cohortparams['selectedcohort'] = $filters['cohortid'];
        }
        $cohortsql .= ' GROUP BY ch.id, ch.name ORDER BY ch.name';

        [$groupscope, $groupparams] = $scope->user_predicate('gm.userid');
        [$coursepredicate, $groupparams] = $scope->course_predicate('g.courseid', $groupparams);
        $groupsql = "SELECT g.id, g.name, g.courseid, c.fullname AS coursename,
                            COUNT(DISTINCT gm.userid) AS members
                       FROM {groups} g
                       JOIN {groups_members} gm ON gm.groupid = g.id
                       JOIN {course} c ON c.id = g.courseid
                      WHERE {$groupscope} AND {$coursepredicate}";
        if ($filters['groupid']) {
            $groupsql .= ' AND g.id = :selectedgroup';
            $groupparams['selectedgroup'] = $filters['groupid'];
        }
        if ($filters['courseid']) {
            $groupsql .= ' AND g.courseid = :selectedgroupcourse';
            $groupparams['selectedgroupcourse'] = $filters['courseid'];
        }
        $groupsql .= ' GROUP BY g.id, g.name, g.courseid, c.fullname ORDER BY c.fullname, g.name';

        $rows = [];
        foreach ($DB->get_records_sql($cohortsql, $cohortparams) as $cohort) {
            $rows[] = [
                'type' => get_string('cohort', 'cohort'),
                'name' => $cohort->name,
                'course' => '-',
                'members' => (int)$cohort->members,
            ];
        }
        foreach ($DB->get_records_sql($groupsql, $groupparams) as $group) {
            $rows[] = [
                'type' => get_string('group'),
                'name' => $group->name,
                'course' => $group->coursename,
                'members' => (int)$group->members,
            ];
        }
        return $this->result('cohort_group', [
            'type' => get_string('type', 'local_tenantanalytics'),
            'name' => get_string('name'),
            'course' => get_string('course'),
            'members' => get_string('members', 'local_tenantanalytics'),
        ], $rows, $filters);
    }

    /**
     * Load bounded standard-log events for the scope.
     *
     * @param tenant_scope $scope Scope.
     * @param array $filters Filters.
     * @return array
     */
    private function events(tenant_scope $scope, array $filters): array {
        global $DB;

        $params = ['since' => $filters['since'], 'until' => $filters['until']];
        [$usersql, $params] = $scope->user_predicate('l.userid', $params);
        [$coursesql, $params] = $scope->course_predicate('l.courseid', $params);
        $sql = "SELECT l.id, l.userid, l.courseid, l.contextlevel, l.contextinstanceid,
                       l.component, l.action, l.timecreated
                  FROM {logstore_standard_log} l
                 WHERE l.timecreated >= :since
                   AND l.timecreated <= :until
                   AND {$usersql}
                   AND {$coursesql}";
        if ($filters['courseid']) {
            $sql .= ' AND l.courseid = :eventcourseid';
            $params['eventcourseid'] = $filters['courseid'];
        }
        $sql .= $this->membership_predicates('l.userid', $filters, $params);
        $sql .= ' ORDER BY l.userid, l.timecreated, l.id';
        $records = $DB->get_records_sql($sql, $params, 0, self::MAX_EVENTS + 1);
        if (count($records) > self::MAX_EVENTS) {
            throw new \moodle_exception('eventlimit', 'local_tenantanalytics', '', self::MAX_EVENTS);
        }
        return array_map(static fn(object $record): array => (array)$record, array_values($records));
    }

    /**
     * Load IOMAD learning tracks for the scope.
     *
     * @param tenant_scope $scope Scope.
     * @param array $filters Filters.
     * @return object[]
     */
    private function tracks(tenant_scope $scope, array $filters): array {
        global $DB;

        [$usersql, $params] = $scope->user_predicate('lit.userid');
        [$companysql, $params] = $scope->company_predicate('lit.companyid', $params);
        $sql = "SELECT lit.*
                  FROM {local_iomad_tracks} lit
                 WHERE {$usersql}
                   AND {$companysql}";
        if ($filters['courseid']) {
            $sql .= ' AND lit.courseid = :trackcourseid';
            $params['trackcourseid'] = $filters['courseid'];
        }
        $sql .= $this->membership_predicates('lit.userid', $filters, $params);
        $sql .= ' ORDER BY lit.userid, lit.courseid, lit.id';
        return array_values($DB->get_records_sql($sql, $params));
    }

    /**
     * Load active tenant users.
     *
     * @param tenant_scope $scope Scope.
     * @param array $filters Filters.
     * @return object[]
     */
    private function users(tenant_scope $scope, array $filters): array {
        global $DB;

        [$usersql, $params] = $scope->user_predicate('u.id');
        $fields = $scope->can_view_pii()
            ? 'u.id, u.firstname, u.lastname, u.firstnamephonetic,
               u.lastnamephonetic, u.middlename, u.alternatename, u.email'
            : 'u.id';
        $sql = "SELECT {$fields}
                  FROM {user} u
                 WHERE u.deleted = 0
                   AND u.suspended = 0
                   AND {$usersql}";
        $sql .= $this->membership_predicates('u.id', $filters, $params);
        $sql .= ' ORDER BY u.lastname, u.firstname, u.id';
        $users = $DB->get_records_sql($sql, $params, 0, self::MAX_USERS + 1);
        if (count($users) > self::MAX_USERS) {
            throw new \moodle_exception('userlimit', 'local_tenantanalytics', '', self::MAX_USERS);
        }
        return array_values($users);
    }

    /**
     * Append cohort and group predicates while mutating named parameters.
     *
     * @param string $userfield User field.
     * @param array $filters Filters.
     * @param array $params SQL parameters.
     * @return string
     */
    private function membership_predicates(string $userfield, array $filters, array &$params): string {
        $sql = '';
        if ($filters['cohortid']) {
            $sql .= " AND EXISTS (
                SELECT 1
                  FROM {cohort_members} filtercm
                 WHERE filtercm.userid = {$userfield}
                   AND filtercm.cohortid = :filtercohortid
            )";
            $params['filtercohortid'] = $filters['cohortid'];
        }
        if ($filters['groupid']) {
            $sql .= " AND EXISTS (
                SELECT 1
                  FROM {groups_members} filtergm
                 WHERE filtergm.userid = {$userfield}
                   AND filtergm.groupid = :filtergroupid
            )";
            $params['filtergroupid'] = $filters['groupid'];
        }
        return $sql;
    }

    /**
     * Resolve user display records without returning secret profile fields.
     *
     * @param array $ids User IDs.
     * @param tenant_scope $scope Scope.
     * @return array<int,object>
     */
    private function users_by_id(array $ids, tenant_scope $scope): array {
        global $DB;

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) {
            return [];
        }
        $fields = $scope->can_view_pii()
            ? 'id,firstname,lastname,firstnamephonetic,lastnamephonetic,middlename,alternatename,email'
            : 'id';
        return $DB->get_records_list('user', 'id', $ids, '', $fields);
    }

    /**
     * Resolve course names.
     *
     * @param array $ids Course IDs.
     * @return array<int,string>
     */
    private function courses(array $ids): array {
        global $DB;

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) {
            return [];
        }
        $records = $DB->get_records_list('course', 'id', $ids, '', 'id,fullname');
        return array_map(static fn(object $course): string => format_string($course->fullname), $records);
    }

    /**
     * Resolve course-module type labels.
     *
     * @param array $ids Course-module IDs.
     * @return array<int,string>
     */
    private function modules(array $ids): array {
        global $DB;

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'module');
        $sql = "SELECT cm.id, m.name
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module
                 WHERE cm.id {$insql}";
        $records = $DB->get_records_sql($sql, $params);
        return array_map(
            static fn(object $record): string => get_string('modulename', 'mod_' . $record->name),
            $records
        );
    }

    /**
     * Common estimated-time columns.
     *
     * @param bool $course Include course.
     * @param bool $activity Include activity.
     * @return array
     */
    private function time_columns(bool $course, bool $activity): array {
        $columns = [
            'learner' => get_string('learner', 'local_tenantanalytics'),
            'email' => get_string('email'),
        ];
        if ($course) {
            $columns['course'] = get_string('course');
        }
        if ($activity) {
            $columns['activity'] = get_string('activity');
        }
        return array_merge($columns, [
            'events' => get_string('events', 'local_tenantanalytics'),
            'estimatedtime' => get_string('estimatedtime', 'local_tenantanalytics'),
            'firstactivity' => get_string('firstactivity', 'local_tenantanalytics'),
            'lastactivity' => get_string('lastactivity', 'local_tenantanalytics'),
        ]);
    }

    /**
     * Return a visible or stable pseudonymized learner identity for this scope.
     *
     * @param tenant_scope $scope Scope.
     * @param object|null $user User record when it still exists.
     * @param int $userid User ID used only as HMAC input.
     * @return array{learner:string,email:string}
     */
    private function user_identity(tenant_scope $scope, ?object $user, int $userid): array {
        if ($scope->can_view_pii()) {
            return [
                'learner' => $user
                    ? fullname($user)
                    : get_string('deleteduser', 'local_tenantanalytics'),
                'email' => $user->email ?? '',
            ];
        }
        $token = strtoupper(substr(hash_hmac(
            'sha256',
            $scope->get_companyid() . ':' . $userid,
            $this->pseudonym_key()
        ), 0, 12));
        return [
            'learner' => get_string('maskedlearner', 'local_tenantanalytics', $token),
            'email' => '',
        ];
    }

    /**
     * Return the per-install pseudonym key without exposing it to report output.
     *
     * @return string
     */
    private function pseudonym_key(): string {
        $key = (string)get_config('local_tenantanalytics', 'pseudonymkey');
        if (strlen($key) < 64) {
            throw new \moodle_exception('pseudonymkeymissing', 'local_tenantanalytics');
        }
        return $key;
    }

    /**
     * Create a normalized result with shared metric metadata.
     *
     * @param string $reportkey Report key.
     * @param array $columns Columns.
     * @param array $rows Rows.
     * @param array $filters Filters.
     * @param array $metadata Additional metadata.
     * @return report_result
     */
    private function result(
        string $reportkey,
        array $columns,
        array $rows,
        array $filters,
        array $metadata = []
    ): report_result {
        $metadata = array_merge([
            'reportkey' => $reportkey,
            'since' => $filters['since'],
            'until' => $filters['until'],
            'timeestimator' => 'consecutive-event-gap-capped-at-1800-seconds',
        ], $metadata);
        return new report_result($columns, array_values($rows), $metadata);
    }

    /**
     * Deterministic risk label, not a predictive or AI score.
     *
     * @param int $events Events in range.
     * @param int $lastactivity Last event.
     * @param int $completions Completion count.
     * @param int $until Range end.
     * @return string
     */
    private function risk_label(int $events, int $lastactivity, int $completions, int $until): string {
        if ($events === 0) {
            return get_string('riskcritical', 'local_tenantanalytics');
        }
        if ($lastactivity < ($until - (14 * DAYSECS))) {
            return get_string('riskhigh', 'local_tenantanalytics');
        }
        if ($events < 5 && $completions === 0) {
            return get_string('riskmedium', 'local_tenantanalytics');
        }
        return get_string('risklow', 'local_tenantanalytics');
    }

    /**
     * Derive stable IOMAD track status.
     *
     * @param object $track Track.
     * @param int $now Reference time.
     * @return string
     */
    private function track_status(object $track, int $now): string {
        if (!empty($track->timeexpires) && (int)$track->timeexpires < $now) {
            return get_string('expired', 'local_tenantanalytics');
        }
        if (!empty($track->timecompleted)) {
            return get_string('completed', 'local_tenantanalytics');
        }
        if (!empty($track->timestarted)) {
            return get_string('inprogress', 'local_tenantanalytics');
        }
        return get_string('notstarted', 'local_tenantanalytics');
    }

    /**
     * Format a nullable timestamp.
     *
     * @param mixed $timestamp Timestamp.
     * @return string
     */
    private function date_or_dash(mixed $timestamp): string {
        return empty($timestamp) ? '-' : userdate((int)$timestamp);
    }

    /**
     * Return configured sessionizer.
     *
     * @return sessionizer
     */
    private function get_sessionizer(): sessionizer {
        return $this->sessionizer ?? new sessionizer();
    }
}
