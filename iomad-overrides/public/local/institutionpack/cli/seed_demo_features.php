<?php
// This file is part of IOMAD - http://www.iomad.org/

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->libdir . '/badgeslib.php');
require_once($CFG->dirroot . '/course/lib.php');

[$options, $unrecognized] = cli_get_params(
    [
        'company' => '',
        'course' => '',
        'learner-prefix' => '',
        'label' => '',
        'limit' => 100,
        'help' => false,
    ],
    [
        'h' => 'help',
    ],
);

if ($unrecognized) {
    cli_error('Unknown options: ' . implode(', ', $unrecognized));
}
if ($options['help']) {
    cli_writeln(
        'Usage: php public/local/institutionpack/cli/seed_demo_features.php '
        . '--company=SHORTNAME --course=SHORTNAME --learner-prefix=PREFIX --label=TEXT [--limit=100]',
    );
    exit(0);
}

$companyshortname = clean_param((string)$options['company'], PARAM_ALPHANUMEXT);
$courseshortname = clean_param((string)$options['course'], PARAM_TEXT);
$learnerprefix = clean_param((string)$options['learner-prefix'], PARAM_ALPHANUMEXT);
$label = trim(clean_param((string)$options['label'], PARAM_TEXT));
$limit = min(100, max(1, (int)$options['limit']));
if ($companyshortname === '' || $courseshortname === '' || $learnerprefix === '' || $label === '') {
    cli_error('--company, --course, --learner-prefix and --label are required.');
}

\core\session\manager::set_user(get_admin());
$admin = get_admin();
$company = $DB->get_record(
    'local_iomad_companies',
    ['shortname' => $companyshortname],
    '*',
    MUST_EXIST,
);
$course = $DB->get_record(
    'course',
    ['shortname' => $courseshortname],
    '*',
    MUST_EXIST,
);
$scope = \local_global_events\local\tenant_scope::system((int)$company->id);
if (!$scope->contains_course((int)$course->id)) {
    cli_error('The requested course is outside the company scope.');
}

$like = $DB->sql_like('u.idnumber', ':learnerprefix', false);
$learners = array_values($DB->get_records_sql(
    "SELECT u.*
       FROM {user} u
       JOIN {local_iomad_company_users} cu ON cu.userid = u.id
      WHERE cu.companyid = :companyid
        AND u.deleted = 0
        AND u.suspended = 0
        AND {$like}
   ORDER BY u.idnumber ASC",
    [
        'companyid' => $company->id,
        'learnerprefix' => $DB->sql_like_escape($learnerprefix) . '%',
    ],
    0,
    $limit,
));
if (count($learners) < $limit) {
    cli_error("Expected {$limit} learners for {$companyshortname}; found " . count($learners) . '.');
}

$gradeitemidnumber = $companyshortname . '-ORIENTATION-GRADE';
$gradeitem = \grade_item::fetch([
    'courseid' => $course->id,
    'idnumber' => $gradeitemidnumber,
]);
if (!$gradeitem) {
    $gradecategory = \grade_category::fetch_course_category((int)$course->id);
    $gradeitem = new \grade_item();
    $gradeitem->courseid = $course->id;
    $gradeitem->categoryid = $gradecategory->id;
    $gradeitem->itemtype = 'manual';
    $gradeitem->itemname = $label . ' readiness score';
    $gradeitem->idnumber = $gradeitemidnumber;
    $gradeitem->gradetype = GRADE_TYPE_VALUE;
    $gradeitem->grademin = 0;
    $gradeitem->grademax = 100;
    $gradeitem->insert('local_institutionpack');
}
$coursescope = new \local_rapidgrader\local\course_scope((int)$company->id);
$gradeservice = new \local_rapidgrader\local\gradebook_service($coursescope, $course);
$gradeupdates = [];
foreach ($learners as $index => $learner) {
    $gradeupdates[$learner->id] = (string)(60 + ($index % 41));
}
$gradeschanged = $gradeservice->update(
    [$gradeitem->id => $gradeupdates],
    (int)$admin->id,
);

$badge = $DB->get_record('badge', [
    'name' => $label . ' Orientation Explorer',
    'courseid' => $course->id,
]);
if (!$badge) {
    $badge = \core_badges\badge::create_badge((object)[
        'name' => $label . ' Orientation Explorer',
        'version' => '1.0',
        'language' => 'en',
        'description' => 'Sanitized demonstration badge for completing orientation activities.',
        'imagecaption' => 'Orientation achievement badge',
        'issuername' => $label,
        'issuerurl' => $CFG->wwwroot,
        'issuercontact' => $admin->email,
        'expiry' => 0,
    ], (int)$course->id);
    if (function_exists('imagecreatetruecolor')) {
        $badgeicon = make_request_directory() . '/orientation-badge.png';
        $image = imagecreatetruecolor(128, 128);
        $background = imagecolorallocate($image, 18, 92, 73);
        $foreground = imagecolorallocate($image, 255, 255, 255);
        imagefilledrectangle($image, 0, 0, 127, 127, $background);
        imagefilledellipse($image, 64, 64, 88, 88, $foreground);
        imagefilledellipse($image, 64, 64, 62, 62, $background);
        imagepng($image, $badgeicon);
        imagedestroy($image);
        badges_process_badge_image($badge, $badgeicon);
    }
    $badge->set_status(BADGE_STATUS_ACTIVE);
} else {
    $badge = new \core_badges\badge((int)$badge->id);
}
$badgeservice = new \local_global_events\local\badge_service();
$badgeservice->upsert_threshold_rule($scope, (int)$badge->id, 10);

$levels = new \local_global_events\local\level_repository();
foreach (
    [
        [1, 'Starter', 0],
        [2, 'Explorer', 25],
        [3, 'Achiever', 50],
        [4, 'Mentor', 75],
    ] as [$level, $name, $points]
) {
    $levels->upsert($scope, $level, $name, $points);
}

$eventrepository = new \local_global_events\local\event_repository();
$events = [
    ['orientation', 'Institution Orientation'],
    ['study-skills', 'Study Skills Workshop'],
    ['digital-safety', 'Digital Safety Clinic'],
    ['community', 'Learning Community Meetup'],
];
$eventids = [];
foreach ($events as $eventindex => [$suffix, $name]) {
    $event = $eventrepository->upsert($scope, [
        'idnumber' => 'demo:' . strtolower($companyshortname) . ':' . $suffix,
        'name' => $name,
        'description' => 'Sanitized demonstration event for feature and tenant acceptance testing.',
        'courseid' => (int)$course->id,
        'visibility' => 'companies',
        'status' => 'published',
        'timestart' => 0,
        'timeend' => 0,
    ], [(int)$company->id], (int)$admin->id);
    $eventids[] = (int)$event->id;
}

$todos = new \block_iomaddashboard\local\todo_repository();
$gamification = new \local_global_events\local\gamification_service();
$messages = new \local_global_events\local\message_queue();
$coursecontext = \context_course::instance((int)$course->id);
$todocreated = 0;
$logscreated = 0;
$awardscreated = 0;
$messagescreated = 0;
foreach ($learners as $index => $learner) {
    $tasktext = 'Complete ' . $label . ' orientation';
    $existingtask = array_filter(
        $todos->list_for_user((int)$learner->id, 100),
        static fn(object $task): bool => $task->tasktext === $tasktext,
    );
    if (!$existingtask) {
        $todos->create(
            (int)$learner->id,
            (int)$company->id,
            $tasktext,
            strtotime('+14 days'),
        );
        $todocreated++;
    }

    $award = $gamification->award(
        $scope,
        (int)$learner->id,
        25 + ($index % 51),
        'local_institutionpack',
        'demo.orientation.completed',
        'demo-feature:' . $companyshortname . ':' . $learner->idnumber . ':orientation-2026',
        (int)$course->id,
        0,
        'xp',
        [
            'activitytype' => 'orientation',
            'completionstate' => 'complete',
            'verb' => 'seed',
        ],
    );
    if ($award['awarded']) {
        $awardscreated++;
    }

    if ($index < 5) {
        $before = $DB->count_records('local_ge_message', ['companyid' => $company->id]);
        $messages->enqueue(
            $scope,
            (int)$learner->id,
            'moodle',
            'achievement',
            ['points' => (int)$award['total']],
            'demo-message:' . $companyshortname . ':' . $learner->idnumber . ':orientation-2026',
        );
        $after = $DB->count_records('local_ge_message', ['companyid' => $company->id]);
        $messagescreated += max(0, $after - $before);
    }

    if (
        !$DB->record_exists('logstore_standard_log', [
            'eventname' => '\\core\\event\\course_viewed',
            'userid' => $learner->id,
            'courseid' => $course->id,
        ])
    ) {
        \core\session\manager::set_user($learner);
        course_view($coursecontext);
        $logscreated++;
    }
}
\core\session\manager::set_user($admin);

cli_writeln(json_encode([
    'ok' => true,
    'company' => $companyshortname,
    'course' => $courseshortname,
    'learners' => count($learners),
    'grade_item' => $gradeitemidnumber,
    'grades_changed' => $gradeschanged,
    'badge_id' => (int)$badge->id,
    'xp_awards_created' => $awardscreated,
    'todos_created' => $todocreated,
    'course_view_logs_created' => $logscreated,
    'messages_created' => $messagescreated,
    'events' => $eventids,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
