<?php
// This file is part of IOMAD - http://www.iomad.org/

use local_aicoursecreator\course_publisher;
use local_aicoursecreator\draft_repository;
use local_aicoursecreator\task\generate_draft;
use local_aicoursecreator\tenant_context;

require_once(__DIR__ . '/../../config.php');

require_login();
$companyid = tenant_context::companyid();
tenant_context::require_capability('local/aicoursecreator:manage', $companyid);
$context = tenant_context::context($companyid);
$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$url = new moodle_url('/local/aicoursecreator/view.php', ['id' => $id]);
$repository = new draft_repository();
$draft = $repository->get($id, $companyid);

if ($action !== '') {
    require_sesskey();
    if ($action === 'queue') {
        $draft = $repository->queue($id, $companyid, $USER->id);
        $task = new generate_draft();
        $task->set_userid($USER->id);
        $task->set_custom_data([
            'draftid' => $draft->id,
            'companyid' => $companyid,
            'userid' => $USER->id,
        ]);
        \core\task\manager::queue_adhoc_task($task, true);
        redirect($url, get_string('draftqueued', 'local_aicoursecreator'));
    } else if ($action === 'approve') {
        tenant_context::require_capability('local/aicoursecreator:approve', $companyid);
        $repository->approve($id, $companyid, $USER->id);
        redirect($url, get_string('draftapproved', 'local_aicoursecreator'));
    } else if ($action === 'publish') {
        tenant_context::require_capability('local/aicoursecreator:publish', $companyid);
        (new course_publisher())->publish($id, $companyid, $USER->id);
        redirect($url, get_string('coursepublished', 'local_aicoursecreator'));
    }
}

$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('report');
$PAGE->set_title(format_string($draft->title));
$PAGE->set_heading(format_string($draft->title));
echo $OUTPUT->header();
$table = new html_table();
$table->data = [
    [get_string('status', 'local_aicoursecreator'), s($draft->status)],
    [get_string('credits', 'local_aicoursecreator'), (int)$draft->credits],
];
echo html_writer::table($table);

if (in_array($draft->status, ['draft', 'failed'], true)) {
    echo $OUTPUT->single_button(
        new moodle_url($url, ['action' => 'queue', 'sesskey' => sesskey()]),
        get_string('queue', 'local_aicoursecreator'),
        'post'
    );
}
if ($draft->status === 'review') {
    echo $OUTPUT->single_button(
        new moodle_url('/local/aicoursecreator/review.php', ['id' => $draft->id]),
        get_string('review', 'local_aicoursecreator'),
        'get'
    );
    if (has_capability('local/aicoursecreator:approve', $context) || is_siteadmin()) {
        echo $OUTPUT->single_button(
            new moodle_url($url, ['action' => 'approve', 'sesskey' => sesskey()]),
            get_string('approve', 'local_aicoursecreator'),
            'post'
        );
    }
}
if ($draft->status === 'approved' && (has_capability('local/aicoursecreator:publish', $context) || is_siteadmin())) {
    echo $OUTPUT->single_button(
        new moodle_url($url, ['action' => 'publish', 'sesskey' => sesskey()]),
        get_string('publish', 'local_aicoursecreator'),
        'post'
    );
}
if (!empty($draft->definition)) {
    echo $OUTPUT->single_button(
        new moodle_url('/local/aicoursecreator/scorm.php', ['id' => $draft->id, 'sesskey' => sesskey()]),
        get_string('downloadscorm', 'local_aicoursecreator'),
        'get'
    );
    echo $OUTPUT->notification(get_string('scormscope', 'local_aicoursecreator'), 'info', false);
}
if ($draft->status === 'published' && $draft->courseid) {
    echo html_writer::link(
        new moodle_url('/course/view.php', ['id' => $draft->courseid]),
        format_string(get_course($draft->courseid)->fullname)
    );
}
echo $OUTPUT->footer();
