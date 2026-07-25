<?php
// This file is part of IOMAD - http://www.iomad.org/

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');

\core\session\manager::set_user(get_admin());

$specifications = [
    'GV_SCHOOL' => [
        'label' => 'School',
        'learnerprefix' => 'SCH_STUDENT_',
        'categoryprefix' => 'GV_',
        'cohortprefix' => 'GV-',
        'orientationcourse' => 'GV-ORIENTATION-2026',
        'minimumusers' => 219,
        'minimumcourses' => 37,
        'minimumdepartments' => 11,
        'minimumcategories' => 52,
        'minimumgroups' => 37,
        'minimumenrolments' => 437,
        'minimumcohorts' => 12,
        'minimumlicenses' => 6,
    ],
    'NBU_ENGINEERING' => [
        'label' => 'University',
        'learnerprefix' => 'UNI_STU_',
        'categoryprefix' => 'NBU_',
        'cohortprefix' => 'NBU-',
        'orientationcourse' => 'NBU-ORIENTATION-2026',
        'minimumusers' => 174,
        'minimumcourses' => 33,
        'minimumdepartments' => 10,
        'minimumcategories' => 54,
        'minimumgroups' => 33,
        'minimumenrolments' => 533,
        'minimumcohorts' => 8,
        'minimumlicenses' => 8,
    ],
];

$checks = [];
$addcheck = static function (
    string $key,
    string $company,
    int $actual,
    int $minimum,
    string $comparison = 'minimum',
) use (&$checks): void {
    $ok = $comparison === 'exact' ? $actual === $minimum : $actual >= $minimum;
    $checks[] = [
        'key' => $key,
        'company' => $company,
        'actual' => $actual,
        $comparison => $minimum,
        'ok' => $ok,
    ];
};

$companies = array_values($DB->get_records(
    'local_iomad_companies',
    null,
    'shortname ASC',
    'id,shortname,name',
));
$addcheck('companies', 'platform', count($companies), 2, 'exact');
$actualshortnames = array_column($companies, 'shortname');
$expectedshortnames = array_keys($specifications);
sort($actualshortnames);
sort($expectedshortnames);
$checks[] = [
    'key' => 'company_shortnames',
    'company' => 'platform',
    'actual' => $actualshortnames,
    'expected' => $expectedshortnames,
    'ok' => $actualshortnames === $expectedshortnames,
];

foreach ($specifications as $shortname => $specification) {
    $company = $DB->get_record(
        'local_iomad_companies',
        ['shortname' => $shortname],
        '*',
    );
    if (!$company) {
        $checks[] = [
            'key' => 'company_exists',
            'company' => $shortname,
            'actual' => 0,
            'minimum' => 1,
            'ok' => false,
        ];
        continue;
    }
    $companyid = (int)$company->id;
    $course = $DB->get_record(
        'course',
        ['shortname' => $specification['orientationcourse']],
        'id,shortname',
    );
    $courseid = (int)($course->id ?? 0);
    $learnerlike = $DB->sql_like('u.idnumber', ':learnerprefix', false);
    $categorylike = $DB->sql_like('idnumber', ':categoryprefix', false);
    $cohortlike = $DB->sql_like('idnumber', ':cohortprefix', false);

    $usermappings = (int)$DB->count_records('local_iomad_company_users', ['companyid' => $companyid]);
    $users = (int)$DB->count_records_sql(
        "SELECT COUNT(DISTINCT userid)
           FROM {local_iomad_company_users}
          WHERE companyid = :companyid",
        ['companyid' => $companyid],
    );
    $rootdepartments = (int)$DB->count_records('local_iomad_company_departments', [
        'companyid' => $companyid,
        'parentid' => 0,
        'shortname' => $shortname,
    ]);
    $learners = (int)$DB->count_records_sql(
        "SELECT COUNT(DISTINCT u.id)
           FROM {user} u
           JOIN {local_iomad_company_users} cu ON cu.userid = u.id
          WHERE cu.companyid = :companyid
            AND u.deleted = 0
            AND {$learnerlike}",
        [
            'companyid' => $companyid,
            'learnerprefix' => $DB->sql_like_escape($specification['learnerprefix']) . '%',
        ],
    );
    $courses = (int)$DB->count_records('local_iomad_company_courses', ['companyid' => $companyid]);
    $departments = (int)$DB->count_records('local_iomad_company_departments', ['companyid' => $companyid]);
    $categories = (int)$DB->count_records_select(
        'course_categories',
        $categorylike,
        ['categoryprefix' => $DB->sql_like_escape($specification['categoryprefix']) . '%'],
    );
    $cohorts = (int)$DB->count_records_select(
        'cohort',
        $cohortlike,
        ['cohortprefix' => $DB->sql_like_escape($specification['cohortprefix']) . '%'],
    );
    $groups = (int)$DB->count_records_sql(
        "SELECT COUNT(g.id)
           FROM {groups} g
           JOIN {local_iomad_company_courses} cc ON cc.courseid = g.courseid
          WHERE cc.companyid = :companyid",
        ['companyid' => $companyid],
    );
    $enrolments = (int)$DB->count_records_sql(
        "SELECT COUNT(DISTINCT ue.id)
           FROM {user_enrolments} ue
           JOIN {enrol} e ON e.id = ue.enrolid
           JOIN {local_iomad_company_courses} cc ON cc.courseid = e.courseid
           JOIN {local_iomad_company_users} cu
             ON cu.userid = ue.userid
            AND cu.companyid = cc.companyid
          WHERE cc.companyid = :companyid",
        ['companyid' => $companyid],
    );
    $parentlinks = (int)$DB->count_records_sql(
        "SELECT COUNT(ra.id)
           FROM {role_assignments} ra
           JOIN {role} r ON r.id = ra.roleid
           JOIN {context} ctx
             ON ctx.id = ra.contextid
            AND ctx.contextlevel = :usercontext
           JOIN {local_iomad_company_users} guardian
             ON guardian.userid = ra.userid
            AND guardian.companyid = :companyid
           JOIN {local_iomad_company_users} learner
             ON learner.userid = ctx.instanceid
            AND learner.companyid = :learnercompanyid
          WHERE r.shortname = :roleshortname",
        [
            'usercontext' => CONTEXT_USER,
            'companyid' => $companyid,
            'learnercompanyid' => $companyid,
            'roleshortname' => 'tenantguardian',
        ],
    );
    $legacyparentlinks = (int)$DB->count_records_sql(
        "SELECT COUNT(ra.id)
           FROM {role_assignments} ra
           JOIN {role} r ON r.id = ra.roleid
           JOIN {context} ctx
             ON ctx.id = ra.contextid
            AND ctx.contextlevel = :usercontext
           JOIN {local_iomad_company_users} guardian
             ON guardian.userid = ra.userid
            AND guardian.companyid = :companyid
           JOIN {local_iomad_company_users} learner
             ON learner.userid = ctx.instanceid
            AND learner.companyid = :learnercompanyid
          WHERE r.shortname = :roleshortname",
        [
            'usercontext' => CONTEXT_USER,
            'companyid' => $companyid,
            'learnercompanyid' => $companyid,
            'roleshortname' => 'parentguardian',
        ],
    );
    $policies = (int)$DB->count_records('tool_iomadpolicy', ['companyid' => $companyid]);
    $licenses = (int)$DB->count_records('local_iomad_company_licenses', ['companyid' => $companyid]);
    $videocourses = (int)$DB->count_records_sql(
        "SELECT COUNT(c.id)
           FROM {course} c
           JOIN {local_iomad_company_courses} cc ON cc.courseid = c.id
          WHERE cc.companyid = :companyid
            AND c.format = :format",
        ['companyid' => $companyid, 'format' => 'iomadvideo'],
    );
    $orientationlearners = $courseid > 0
        ? (int)$DB->count_records_sql(
            "SELECT COUNT(DISTINCT ue.userid)
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
               JOIN {user} u ON u.id = ue.userid
              WHERE e.courseid = :courseid
                AND " . $DB->sql_like('u.idnumber', ':learnerprefix', false),
            [
                'courseid' => $courseid,
                'learnerprefix' => $DB->sql_like_escape($specification['learnerprefix']) . '%',
            ],
        )
        : 0;
    $grades = $courseid > 0
        ? (int)$DB->count_records_sql(
            "SELECT COUNT(gg.id)
               FROM {grade_grades} gg
               JOIN {grade_items} gi ON gi.id = gg.itemid
              WHERE gi.courseid = :courseid
                AND gi.idnumber = :idnumber
                AND gg.finalgrade IS NOT NULL",
            [
                'courseid' => $courseid,
                'idnumber' => $shortname . '-ORIENTATION-GRADE',
            ],
        )
        : 0;
    $badges = $courseid > 0
        ? (int)$DB->count_records_sql(
            "SELECT COUNT(bi.id)
               FROM {badge_issued} bi
               JOIN {badge} b ON b.id = bi.badgeid
              WHERE b.courseid = :courseid",
            ['courseid' => $courseid],
        )
        : 0;
    $logs = $courseid > 0
        ? (int)$DB->count_records('logstore_standard_log', [
            'eventname' => '\\core\\event\\course_viewed',
            'courseid' => $courseid,
        ])
        : 0;
    $ledger = (int)$DB->count_records('local_ge_ledger', ['companyid' => $companyid]);
    $todos = (int)$DB->count_records('block_iomaddashboard_todo', ['companyid' => $companyid]);
    $events = (int)$DB->count_records('local_ge_event', ['ownercompanyid' => $companyid]);
    $messages = (int)$DB->count_records('local_ge_message', ['companyid' => $companyid]);
    $pages = (int)$DB->count_records('local_iomadpagebuilder_page', [
        'companyid' => $companyid,
        'status' => 'published',
    ]);
    $aidrafts = (int)$DB->count_records('local_aicoursecreator_draft', [
        'companyid' => $companyid,
        'status' => 'published',
    ]);
    $aiactivities = (int)$DB->count_records_sql(
        "SELECT COUNT(cm.id)
           FROM {course_modules} cm
           JOIN {local_aicoursecreator_draft} d ON d.courseid = cm.course
          WHERE d.companyid = :companyid
            AND d.status = :status
            AND cm.deletioninprogress = 0",
        ['companyid' => $companyid, 'status' => 'published'],
    );
    $products = (int)$DB->count_records('local_iomadcommerce_product', ['companyid' => $companyid]);
    $orders = (int)$DB->count_records('local_iomadcommerce_order', ['companyid' => $companyid]);
    $forms = (int)$DB->count_records('tenantform', ['companyid' => $companyid]);
    $formentries = (int)$DB->count_records('tenantform_entry', ['companyid' => $companyid]);

    $addcheck('users', $shortname, $users, $specification['minimumusers']);
    $addcheck('company_user_mappings', $shortname, $usermappings, $specification['minimumusers'], 'exact');
    $addcheck('company_root_department', $shortname, $rootdepartments, 1, 'exact');
    $addcheck('learners', $shortname, $learners, 100, 'exact');
    $addcheck('courses', $shortname, $courses, $specification['minimumcourses']);
    $addcheck('departments', $shortname, $departments, $specification['minimumdepartments']);
    $addcheck('categories', $shortname, $categories, $specification['minimumcategories']);
    $addcheck('cohorts', $shortname, $cohorts, $specification['minimumcohorts']);
    $addcheck('groups', $shortname, $groups, $specification['minimumgroups']);
    $addcheck('enrolments', $shortname, $enrolments, $specification['minimumenrolments']);
    $addcheck('parent_links', $shortname, $parentlinks, 100, 'exact');
    $addcheck('legacy_parent_links', $shortname, $legacyparentlinks, 0, 'exact');
    $addcheck('policies', $shortname, $policies, 4);
    $addcheck('licenses', $shortname, $licenses, $specification['minimumlicenses']);
    $addcheck('video_courses', $shortname, $videocourses, 1);
    $addcheck('orientation_learners', $shortname, $orientationlearners, 100, 'exact');
    $addcheck('orientation_grades', $shortname, $grades, 100, 'exact');
    $addcheck('badge_awards', $shortname, $badges, 100, 'exact');
    $addcheck('course_view_logs', $shortname, $logs, 100);
    $addcheck('gamification_ledger', $shortname, $ledger, 100);
    $addcheck('dashboard_todos', $shortname, $todos, 100);
    $addcheck('global_events', $shortname, $events, 4);
    $addcheck('notification_messages', $shortname, $messages, 5);
    $addcheck('published_pages', $shortname, $pages, 1);
    $addcheck('published_ai_drafts', $shortname, $aidrafts, 1);
    $addcheck('ai_course_activities', $shortname, $aiactivities, 4);
    $addcheck('commerce_products', $shortname, $products, 2);
    $addcheck('commerce_orders', $shortname, $orders, 2);
    $addcheck('forms', $shortname, $forms, 1);
    $addcheck('form_entries', $shortname, $formentries, 1);
}

$addcheck(
    'dashboard_blocks',
    'platform',
    (int)$DB->count_records_select(
        'block_instances',
        "blockname IN ('iomadpagebuilder', 'iomaddashboard', 'gamification_telemetry')",
    ),
    3,
);

$ok = !array_filter($checks, static fn(array $check): bool => !$check['ok']);
$result = [
    'ok' => $ok,
    'companies' => array_map(
        static fn(object $company): array => [
            'shortname' => $company->shortname,
            'name' => $company->name,
        ],
        $companies,
    ),
    'checks' => $checks,
];
cli_writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
exit($ok ? 0 : 1);
