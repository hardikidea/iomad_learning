<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Display and submit a tenant form.
 *
 * @package    mod_tenantform
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/completionlib.php');

$id = required_param('id', PARAM_INT);
$submitted = optional_param('submitted', 0, PARAM_BOOL);
$cm = get_coursemodule_from_id('tenantform', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$form = $DB->get_record('tenantform', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_course_login($course, false, $cm);
if (isguestuser() && empty($form->allowguest)) {
    throw new moodle_exception('guestdisabled', 'mod_tenantform');
}
require_capability('mod/tenantform:submit', $context);
if (!isguestuser()) {
    \mod_tenantform\local\tenant_access::require_company($form, $context, $USER);
}

$PAGE->set_url('/mod/tenantform/view.php', ['id' => $id]);
$PAGE->set_title(format_string($form->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->add_body_class('limitedwidth');
$PAGE->requires->js_call_amd('mod_tenantform/form', 'init');

$definition = (new \mod_tenantform\local\definition_validator())->from_json($form->definitionjson);
$errors = [];
$values = [];
$token = optional_param('submissiontoken', random_string(48), PARAM_ALPHANUM);

if (optional_param('action', '', PARAM_ALPHA) === 'submit') {
    require_sesskey();
    try {
        $result = (new \mod_tenantform\local\submission_service())->submit(
            $form,
            $course,
            $cm,
            $context,
            $USER,
            $_POST,
            $_FILES,
        );
        redirect(
            new moodle_url('/mod/tenantform/view.php', ['id' => $id, 'submitted' => 1]),
            get_string($result->created ? 'submissionsaved' : 'submissionalreadysaved', 'mod_tenantform'),
            null,
            \core\output\notification::NOTIFY_SUCCESS,
        );
    } catch (\mod_tenantform\local\submission_validation_exception $exception) {
        $errors = $exception->get_errors();
        foreach ($definition['pages'] as $page) {
            foreach ($page['fields'] as $field) {
                $name = 'field_' . $field['id'];
                if (isset($_POST[$name]) && !is_array($_POST[$name])) {
                    $values[$field['id']] = (string)$_POST[$name];
                }
            }
        }
    }
}

$completion = new completion_info($course);
$completion->set_module_viewed($cm);

echo $OUTPUT->header();
if (trim((string)$form->intro) !== '') {
    echo $OUTPUT->box(format_module_intro('tenantform', $form, $cm->id), 'generalbox mod_introbox');
}
if ($submitted) {
    echo $OUTPUT->notification(get_string('submissionsaved', 'mod_tenantform'), 'success');
}
if (has_capability('mod/tenantform:manageentries', $context)) {
    echo $OUTPUT->single_button(
        new moodle_url('/mod/tenantform/entries.php', ['id' => $cm->id]),
        get_string('manageentries', 'mod_tenantform'),
        'get',
    );
}
echo (new \mod_tenantform\output\form_renderer())->render(
    $form,
    $definition,
    $token,
    $values,
    $errors,
);
echo $OUTPUT->footer();
