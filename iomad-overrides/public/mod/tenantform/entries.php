<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Tenant-scoped form entry management.
 *
 * @package    mod_tenantform
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$id = required_param('id', PARAM_INT);
$page = max(0, optional_param('page', 0, PARAM_INT));
$perpage = 50;
$cm = get_coursemodule_from_id('tenantform', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$form = $DB->get_record('tenantform', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_course_login($course, false, $cm);
\mod_tenantform\local\tenant_access::require_manage($form, $context);

$PAGE->set_url('/mod/tenantform/entries.php', ['id' => $id, 'page' => $page]);
$PAGE->set_title(get_string('manageentries', 'mod_tenantform'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->add_body_class('limitedwidth');

$repository = new \mod_tenantform\local\entry_repository();
$entries = $repository->list((int)$form->id, $page * $perpage, $perpage);
$total = $DB->count_records('tenantform_entry', ['tenantformid' => $form->id]);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('manageentriesfor', 'mod_tenantform', format_string($form->name)));

$formatmenu = [];
foreach (\mod_tenantform\local\entry_exporter::FORMATS as $format) {
    $formatmenu[] = $OUTPUT->single_button(
        new moodle_url('/mod/tenantform/export.php', [
            'id' => $cm->id,
            'format' => $format,
            'sesskey' => sesskey(),
        ]),
        get_string('exportformat', 'mod_tenantform', strtoupper($format)),
        'get',
    );
}
echo \html_writer::div(implode('', $formatmenu), 'tenantform-exports d-flex flex-wrap gap-2 mb-3');

$table = new html_table();
$table->head = [
    get_string('entryid', 'mod_tenantform'),
    get_string('submitter', 'mod_tenantform'),
    get_string('status', 'mod_tenantform'),
    get_string('submitted', 'mod_tenantform'),
    get_string('actions'),
];
foreach ($entries as $entry) {
    if ($entry->userid) {
        $submitter = \core_user::get_user($entry->userid);
        $submittername = $submitter ? fullname($submitter) : get_string('deleteduser', 'mod_tenantform');
    } else {
        $submittername = get_string('guestsubmitter', 'mod_tenantform');
    }
    $table->data[] = [
        $entry->id,
        s($submittername),
        get_string('status' . $entry->status, 'mod_tenantform'),
        userdate($entry->timecreated),
        html_writer::link(
            new moodle_url('/mod/tenantform/entry.php', ['id' => $cm->id, 'entryid' => $entry->id]),
            get_string('view'),
        ),
    ];
}
if ($entries) {
    echo html_writer::table($table);
} else {
    echo $OUTPUT->notification(get_string('noentries', 'mod_tenantform'), 'info');
}
echo $OUTPUT->paging_bar($total, $page, $perpage, $PAGE->url);
echo $OUTPUT->single_button(
    new moodle_url('/mod/tenantform/view.php', ['id' => $cm->id]),
    get_string('backtoform', 'mod_tenantform'),
    'get',
);
echo $OUTPUT->footer();
