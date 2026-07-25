<?php
// This file is part of IOMAD - http://www.iomad.org/

use local_iomadpagebuilder\catalog;
use local_iomadpagebuilder\form\page as page_form;
use local_iomadpagebuilder\page_repository;
use local_iomadpagebuilder\tenant_context;

require_once(__DIR__ . '/../../config.php');

require_login();
$companyid = tenant_context::companyid(false);
tenant_context::require_capability('local/iomadpagebuilder:manage', $companyid);
$context = tenant_context::context($companyid);
$id = optional_param('id', 0, PARAM_INT);
$url = new moodle_url('/local/iomadpagebuilder/edit.php', $id ? ['id' => $id] : []);
$returnurl = new moodle_url('/local/iomadpagebuilder/index.php');
$repository = new page_repository();

$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string($id ? 'editpage' : 'newpage', 'local_iomadpagebuilder'));
$PAGE->set_heading(get_string($id ? 'editpage' : 'newpage', 'local_iomadpagebuilder'));
$PAGE->requires->js_call_amd('local_iomadpagebuilder/editor', 'init', [
    array_values(catalog::presets()),
    catalog::templates(),
]);

$form = new page_form($url);
if ($form->is_cancelled()) {
    redirect($returnurl);
}
if ($data = $form->get_data()) {
    $record = $repository->save((array)$data, $companyid, $USER->id);
    redirect(
        new moodle_url('/local/iomadpagebuilder/edit.php', ['id' => $record->id]),
        get_string($id ? 'pageupdated' : 'pagecreated', 'local_iomadpagebuilder'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

if ($id > 0) {
    $record = $repository->get($id, $companyid);
    $form->set_data($record);
} else {
    $form->set_data((object)[
        'target' => 'custompage',
        'targetid' => 0,
        'startertemplate' => 'school_home',
        'definition' => json_encode(
            catalog::template('school_home'),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ),
    ]);
}

echo $OUTPUT->header();
$form->display();
echo $OUTPUT->footer();
