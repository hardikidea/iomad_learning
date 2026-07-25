<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Tenant-aware rapid grading workspace.
 *
 * @package    local_rapidgrader
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
$companyid = optional_param('companyid', 0, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);
$mode = optional_param('mode', 'matrix', PARAM_ALPHA);
$itemid = optional_param('itemid', 0, PARAM_INT);
$search = trim(optional_param('search', '', PARAM_TEXT));
$page = optional_param('page', 0, PARAM_INT);
$perpage = 50;
if (!in_array($mode, ['matrix', 'item', 'quiz'], true)) {
    $mode = 'matrix';
}

$scope = \local_rapidgrader\local\course_scope::resolve($companyid);
$courses = $scope->courses();
if (!$courseid && $courses) {
    $courseid = (int)array_key_first($courses);
}

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/local/rapidgrader/index.php', [
    'companyid' => $scope->companyid(),
    'courseid' => $courseid,
    'mode' => $mode,
    'itemid' => $itemid,
    'search' => $search,
]);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('pluginname', 'local_rapidgrader'));
$PAGE->set_heading(get_string('pluginname', 'local_rapidgrader'));
$PAGE->requires->css('/local/rapidgrader/styles.css');

if ($courses) {
    $course = $scope->require_course($courseid);
    $context = context_course::instance($course->id);
    $PAGE->set_context($context);
    if (!has_capability('local/rapidgrader:view', $context)) {
        require_capability('moodle/grade:viewall', $context);
    }
    $service = new \local_rapidgrader\local\gradebook_service($scope, $course);
}

echo $OUTPUT->header();
if (!$courses) {
    echo $OUTPUT->notification(get_string('nocourses', 'local_rapidgrader'), 'info');
    echo $OUTPUT->footer();
    exit;
}

if (data_submitted() && optional_param('action', '', PARAM_ALPHA) === 'save') {
    require_sesskey();
    $updates = $_POST['grades'] ?? [];
    if (!is_array($updates)) {
        throw new invalid_parameter_exception('Invalid grade update payload.');
    }
    $changed = $service->update($updates, (int)$USER->id);
    redirect(
        $PAGE->url,
        get_string('gradesupdated', 'local_rapidgrader', $changed),
        null,
        \core\output\notification::NOTIFY_SUCCESS,
    );
}

$items = $service->items();
$learnercount = $service->learner_count($search);
$learners = $service->learners($search, $perpage, $page * $perpage);
$canedit = has_capability('local/rapidgrader:grade', $context)
    && has_capability('moodle/grade:edit', $context);

echo html_writer::start_tag('form', [
    'method' => 'get',
    'class' => 'rapidgrader-filters',
]);
if ($scope->companyid()) {
    echo html_writer::empty_tag('input', [
        'type' => 'hidden',
        'name' => 'companyid',
        'value' => $scope->companyid(),
    ]);
}
echo html_writer::label(get_string('course'), 'rapidgrader-course');
echo html_writer::select($courses, 'courseid', $courseid, false, [
    'id' => 'rapidgrader-course',
    'aria-label' => get_string('course'),
]);
echo html_writer::label(get_string('search'), 'rapidgrader-search');
echo html_writer::empty_tag('input', [
    'type' => 'search',
    'name' => 'search',
    'id' => 'rapidgrader-search',
    'value' => $search,
    'class' => 'form-control',
    'aria-label' => get_string('search'),
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'mode', 'value' => $mode]);
echo html_writer::tag('button', get_string('applyfilters', 'local_rapidgrader'), [
    'type' => 'submit',
    'class' => 'btn btn-primary',
]);
echo html_writer::end_tag('form');

$tabs = [];
foreach (['matrix', 'item', 'quiz'] as $tabmode) {
    $tabs[] = new tabobject(
        $tabmode,
        new moodle_url('/local/rapidgrader/index.php', [
            'companyid' => $scope->companyid(),
            'courseid' => $courseid,
            'mode' => $tabmode,
        ]),
        get_string('mode' . $tabmode, 'local_rapidgrader'),
    );
}
echo $OUTPUT->tabtree($tabs, $mode);

$bands = $service->distribution($items, $learners);
$totalcells = max(1, array_sum($bands));
echo html_writer::start_div('rapidgrader-distribution', [
    'aria-label' => get_string('gradedistributionpage', 'local_rapidgrader'),
]);
foreach ($bands as $key => $count) {
    $percentage = round(($count / $totalcells) * 100, 1);
    echo html_writer::start_div('rapidgrader-band rapidgrader-band--' . $key);
    echo html_writer::div(get_string('band' . $key, 'local_rapidgrader'), 'rapidgrader-band__label');
    echo html_writer::div(
        html_writer::span('', 'rapidgrader-band__fill', [
            'style' => 'width:' . $percentage . '%',
        ]),
        'rapidgrader-band__track',
    );
    echo html_writer::div(get_string('bandcount', 'local_rapidgrader', [
        'count' => $count,
        'percentage' => $percentage,
    ]), 'rapidgrader-band__value');
    echo html_writer::end_div();
}
echo html_writer::end_div();

if (has_capability('local/rapidgrader:export', $context) || has_capability('moodle/grade:export', $context)) {
    echo html_writer::start_div('rapidgrader-exports');
    foreach (\local_rapidgrader\local\exporter::FORMATS as $format) {
        echo $OUTPUT->single_button(
            new moodle_url('/local/rapidgrader/export.php', [
                'companyid' => $scope->companyid(),
                'courseid' => $courseid,
                'format' => $format,
                'sesskey' => sesskey(),
            ]),
            get_string('exportformat', 'local_rapidgrader', strtoupper($format)),
            'get',
        );
    }
    echo html_writer::end_div();
}

if ($mode === 'quiz') {
    $table = new html_table();
    $table->head = [
        get_string('quiz', 'local_rapidgrader'),
        get_string('participants', 'local_rapidgrader'),
        get_string('attempts', 'local_rapidgrader'),
        get_string('actions'),
    ];
    foreach ($service->quizzes() as $quiz) {
        $table->data[] = [
            s($quiz['name']),
            $quiz['participants'],
            $quiz['attempts'],
            html_writer::link($quiz['url'], get_string('opengradereport', 'local_rapidgrader')),
        ];
    }
    echo $table->data
        ? html_writer::table($table)
        : $OUTPUT->notification(get_string('noquizzes', 'local_rapidgrader'), 'info');
    echo $OUTPUT->footer();
    exit;
}

if ($mode === 'item') {
    if (!$itemid && $items) {
        $itemid = (int)array_key_first($items);
    }
    $itemmenu = [];
    foreach ($items as $item) {
        $itemmenu[$item->id] = $item->get_name();
    }
    echo $OUTPUT->single_select(
        new moodle_url('/local/rapidgrader/index.php', [
            'companyid' => $scope->companyid(),
            'courseid' => $courseid,
            'mode' => 'item',
        ]),
        'itemid',
        $itemmenu,
        $itemid,
    );
    $items = isset($items[$itemid]) ? [$itemid => $items[$itemid]] : [];
}

echo html_writer::start_tag('form', ['method' => 'post']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'save']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'companyid', 'value' => $scope->companyid()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'mode', 'value' => $mode]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'itemid', 'value' => $itemid]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'search', 'value' => $search]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'page', 'value' => $page]);
echo html_writer::start_div('rapidgrader-table-wrap');
$table = new html_table();
$table->attributes['class'] = 'generaltable rapidgrader-table';
$table->head = [get_string('learner', 'local_rapidgrader')];
foreach ($items as $item) {
    $table->head[] = s($item->get_name()) . html_writer::div(
        get_string('graderange', 'local_rapidgrader', [
            'min' => round($item->grademin, 2),
            'max' => round($item->grademax, 2),
        ]),
        'rapidgrader-range',
    );
}
foreach ($learners as $learner) {
    $row = [
        html_writer::link(
            new moodle_url('/grade/report/user/index.php', [
                'id' => $courseid,
                'userid' => $learner->id,
            ]),
            fullname($learner),
        ),
    ];
    foreach ($items as $item) {
        $grade = $service->grade($item, (int)$learner->id);
        if ($canedit && $item->itemtype === 'manual' && !$item->is_locked()) {
            $attributes = [
                'class' => 'form-control rapidgrader-grade',
                'aria-label' => get_string('gradefor', 'local_rapidgrader', [
                    'item' => $item->get_name(),
                    'learner' => fullname($learner),
                ]),
            ];
            if ((int)$item->gradetype === GRADE_TYPE_SCALE) {
                $row[] = html_writer::select(
                    $service->scale_options($item),
                    'grades[' . $item->id . '][' . $learner->id . ']',
                    $grade === null ? '' : (int)round($grade),
                    false,
                    $attributes,
                );
            } else {
                $row[] = html_writer::empty_tag('input', $attributes + [
                    'type' => 'number',
                    'name' => 'grades[' . $item->id . '][' . $learner->id . ']',
                    'value' => $grade === null ? '' : $grade,
                    'min' => $item->grademin,
                    'max' => $item->grademax,
                    'step' => 'any',
                ]);
            }
        } else {
            $row[] = $service->display_grade($item, $grade);
        }
    }
    $table->data[] = $row;
}
echo $table->data
    ? html_writer::table($table)
    : $OUTPUT->notification(get_string('nolearners', 'local_rapidgrader'), 'info');
echo html_writer::end_div();
if ($canedit && $items) {
    echo html_writer::tag('button', get_string('savegrades', 'local_rapidgrader'), [
        'type' => 'submit',
        'class' => 'btn btn-primary',
    ]);
}
echo html_writer::end_tag('form');
echo $OUTPUT->paging_bar($learnercount, $page, $perpage, $PAGE->url);
echo $OUTPUT->footer();
