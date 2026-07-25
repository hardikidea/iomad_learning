<?php
// This file is part of IOMAD - http://www.iomad.org/

use local_aicoursecreator\draft_repository;
use local_aicoursecreator\tenant_context;

require_once(__DIR__ . '/../../config.php');

require_login();
$companyid = tenant_context::companyid();
tenant_context::require_capability('local/aicoursecreator:manage', $companyid);
$context = tenant_context::context($companyid);
$url = new moodle_url('/local/aicoursecreator/index.php');
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('pluginname', 'local_aicoursecreator'));
$PAGE->set_heading(get_string('pluginname', 'local_aicoursecreator'));

$repository = new draft_repository();
echo $OUTPUT->header();
echo $OUTPUT->single_button(
    new moodle_url('/local/aicoursecreator/request.php'),
    get_string('newdraft', 'local_aicoursecreator'),
    'get'
);
$table = new html_table();
$table->head = [
    get_string('title', 'local_aicoursecreator'),
    get_string('status', 'local_aicoursecreator'),
    get_string('credits', 'local_aicoursecreator'),
    get_string('actions', 'local_aicoursecreator'),
];
foreach ($repository->list_for_company($companyid) as $draft) {
    $table->data[] = [
        format_string($draft->title),
        s($draft->status),
        (int)$draft->credits,
        html_writer::link(
            new moodle_url('/local/aicoursecreator/view.php', ['id' => $draft->id]),
            get_string('view')
        ),
    ];
}
echo html_writer::table($table);
echo $OUTPUT->footer();
