<?php
// This file is part of IOMAD - http://www.iomad.org/

use local_aicoursecreator\draft_repository;
use local_aicoursecreator\form\review as review_form;
use local_aicoursecreator\tenant_context;

require_once(__DIR__ . '/../../config.php');

require_login();
$companyid = tenant_context::companyid();
tenant_context::require_capability('local/aicoursecreator:manage', $companyid);
$context = tenant_context::context($companyid);
$id = required_param('id', PARAM_INT);
$url = new moodle_url('/local/aicoursecreator/review.php', ['id' => $id]);
$returnurl = new moodle_url('/local/aicoursecreator/view.php', ['id' => $id]);
$repository = new draft_repository();
$draft = $repository->get($id, $companyid);
if ($draft->status !== 'review') {
    redirect($returnurl);
}
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('review', 'local_aicoursecreator'));
$PAGE->set_heading(format_string($draft->title));

$form = new review_form($url);
if ($form->is_cancelled()) {
    redirect($returnurl);
}
if ($data = $form->get_data()) {
    $repository->save_review($id, $companyid, $USER->id, $data->definition);
    redirect(
        $returnurl,
        get_string('draftsaved', 'local_aicoursecreator'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}
$form->set_data((object)[
    'id' => $draft->id,
    'definition' => json_encode(
        $repository->definition($draft),
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    ),
]);
echo $OUTPUT->header();
$form->display();
echo $OUTPUT->footer();
