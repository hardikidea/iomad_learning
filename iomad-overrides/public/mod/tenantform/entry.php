<?php
// This file is part of Moodle - http://moodle.org/

/**
 * View and review one tenant form entry.
 *
 * @package    mod_tenantform
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$id = required_param('id', PARAM_INT);
$entryid = required_param('entryid', PARAM_INT);
$cm = get_coursemodule_from_id('tenantform', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$form = $DB->get_record('tenantform', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);
$repository = new \mod_tenantform\local\entry_repository();
$entry = $repository->get((int)$form->id, $entryid);

require_course_login($course, false, $cm);
$canmanage = has_capability('mod/tenantform:manageentries', $context);
if ($canmanage) {
    \mod_tenantform\local\tenant_access::require_manage($form, $context);
} else {
    require_capability('mod/tenantform:viewownentry', $context);
    \mod_tenantform\local\tenant_access::require_company($form, $context, $USER);
    if ((int)$entry->userid !== (int)$USER->id) {
        throw new required_capability_exception(
            $context,
            'mod/tenantform:viewownentry',
            'nopermissions',
            '',
        );
    }
}

if ($canmanage && optional_param('action', '', PARAM_ALPHA) === 'status') {
    require_sesskey();
    $status = required_param('status', PARAM_ALPHA);
    $repository->update_status($entry, $status, (int)$USER->id);
    redirect(
        new moodle_url('/mod/tenantform/entry.php', ['id' => $id, 'entryid' => $entryid]),
        get_string('statusupdated', 'mod_tenantform'),
        null,
        \core\output\notification::NOTIFY_SUCCESS,
    );
}

$PAGE->set_url('/mod/tenantform/entry.php', ['id' => $id, 'entryid' => $entryid]);
$PAGE->set_title(get_string('entrytitle', 'mod_tenantform', $entry->id));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->add_body_class('limitedwidth');

$definition = (new \mod_tenantform\local\definition_validator())->from_json($form->definitionjson);
$values = json_decode($entry->datajson, true, 64, JSON_THROW_ON_ERROR);
$fields = [];
foreach ($definition['pages'] as $page) {
    foreach ($page['fields'] as $field) {
        $fields[$field['id']] = $field;
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('entrytitle', 'mod_tenantform', $entry->id));
$description = new html_table();
$description->data = [
    [get_string('status', 'mod_tenantform'), get_string('status' . $entry->status, 'mod_tenantform')],
    [get_string('submitted', 'mod_tenantform'), userdate($entry->timecreated)],
    [get_string('checksum', 'mod_tenantform'), s($entry->checksum)],
];
echo html_writer::table($description);

$table = new html_table();
$table->head = [get_string('field', 'mod_tenantform'), get_string('value', 'mod_tenantform')];
foreach ($values as $fieldid => $value) {
    if (!isset($fields[$fieldid])) {
        continue;
    }
    $field = $fields[$fieldid];
    if ($field['type'] === 'file') {
        $files = get_file_storage()->get_area_files(
            $context->id,
            'mod_tenantform',
            'entry',
            $entry->id,
            'filepath,filename',
            false,
        );
        $links = [];
        foreach ($files as $file) {
            if (trim($file->get_filepath(), '/') !== $fieldid) {
                continue;
            }
            $url = moodle_url::make_pluginfile_url(
                $context->id,
                'mod_tenantform',
                'entry',
                $entry->id,
                $file->get_filepath(),
                $file->get_filename(),
                true,
            );
            $links[] = html_writer::link($url, s($file->get_filename()));
        }
        $displayvalue = implode(html_writer::empty_tag('br'), $links);
    } else if (in_array($field['type'], ['checkbox', 'consent'], true)) {
        $displayvalue = get_string($value === '1' ? 'yes' : 'no');
    } else {
        $displayvalue = html_writer::div(s((string)$value), 'tenantform-entry__value');
    }
    $table->data[] = [s($field['label']), $displayvalue];
}
echo html_writer::table($table);

if ($canmanage) {
    echo html_writer::start_tag('form', ['method' => 'post', 'class' => 'd-flex gap-2 align-items-end mb-3']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'status']);
    echo html_writer::label(get_string('status', 'mod_tenantform'), 'tenantform-status');
    $statuses = [];
    foreach (\mod_tenantform\local\entry_repository::STATUSES as $status) {
        $statuses[$status] = get_string('status' . $status, 'mod_tenantform');
    }
    echo html_writer::select($statuses, 'status', $entry->status, false, ['id' => 'tenantform-status']);
    echo html_writer::tag('button', get_string('updatestatus', 'mod_tenantform'), [
        'type' => 'submit',
        'class' => 'btn btn-primary',
    ]);
    echo html_writer::end_tag('form');
    echo $OUTPUT->single_button(
        new moodle_url('/mod/tenantform/entries.php', ['id' => $cm->id]),
        get_string('backtoentries', 'mod_tenantform'),
        'get',
    );
}
echo $OUTPUT->footer();
