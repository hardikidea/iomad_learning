<?php
// This file is part of IOMAD - http://www.iomad.org/

use local_aicoursecreator\draft_repository;
use local_aicoursecreator\form\request as request_form;
use local_aicoursecreator\tenant_context;

require_once(__DIR__ . '/../../config.php');

require_login();
$companyid = tenant_context::companyid();
tenant_context::require_capability('local/aicoursecreator:manage', $companyid);
$context = tenant_context::context($companyid);
$url = new moodle_url('/local/aicoursecreator/request.php');
$returnurl = new moodle_url('/local/aicoursecreator/index.php');
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('newdraft', 'local_aicoursecreator'));
$PAGE->set_heading(get_string('newdraft', 'local_aicoursecreator'));

$form = new request_form($url);
if ($form->is_cancelled()) {
    redirect($returnurl);
}
if ($data = $form->get_data()) {
    $draft = (new draft_repository())->create((array)$data, $companyid, $USER->id);
    redirect(
        new moodle_url('/local/aicoursecreator/view.php', ['id' => $draft->id]),
        get_string('draftcreated', 'local_aicoursecreator'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}
echo $OUTPUT->header();
$form->display();
echo $OUTPUT->footer();
