<?php
// This file is part of IOMAD - http://www.iomad.org/

use local_iomadpagebuilder\page_repository;
use local_iomadpagebuilder\tenant_context;

require_once(__DIR__ . '/../../config.php');

require_login();
$companyid = tenant_context::companyid(false);
tenant_context::require_capability('local/iomadpagebuilder:manage', $companyid);
$context = tenant_context::context($companyid);
$url = new moodle_url('/local/iomadpagebuilder/index.php');

$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('pluginname', 'local_iomadpagebuilder'));
$PAGE->set_heading(get_string('pluginname', 'local_iomadpagebuilder'));

$repository = new page_repository();
$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);
if ($action === 'publish' && $id > 0) {
    require_sesskey();
    tenant_context::require_capability('local/iomadpagebuilder:publish', $companyid);
    $repository->publish($id, $companyid, $USER->id);
    redirect($url, get_string('pagepublished', 'local_iomadpagebuilder'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->single_button(
    new moodle_url('/local/iomadpagebuilder/edit.php'),
    get_string('newpage', 'local_iomadpagebuilder'),
    'get'
);

$table = new html_table();
$table->head = [
    get_string('name', 'local_iomadpagebuilder'),
    get_string('target', 'local_iomadpagebuilder'),
    get_string('status', 'local_iomadpagebuilder'),
    get_string('revision', 'local_iomadpagebuilder'),
    get_string('actions', 'local_iomadpagebuilder'),
];
$table->data = [];
foreach ($repository->list_for_company($companyid) as $page) {
    $actions = [];
    if ((int)$page->companyid === $companyid) {
        $actions[] = html_writer::link(
            new moodle_url('/local/iomadpagebuilder/edit.php', ['id' => $page->id]),
            get_string('edit')
        );
        if ($page->status !== 'published') {
            $actions[] = html_writer::link(
                new moodle_url($url, ['action' => 'publish', 'id' => $page->id, 'sesskey' => sesskey()]),
                get_string('publish', 'local_iomadpagebuilder')
            );
        }
    }
    $actions[] = html_writer::link(
        new moodle_url('/local/iomadpagebuilder/preview.php', ['id' => $page->id]),
        get_string('preview', 'local_iomadpagebuilder')
    );
    $actions[] = html_writer::link(
        new moodle_url('/local/iomadpagebuilder/export.php', ['id' => $page->id, 'sesskey' => sesskey()]),
        get_string('export', 'local_iomadpagebuilder')
    );
    $table->data[] = [
        format_string($page->name),
        s($page->target),
        get_string($page->status, 'local_iomadpagebuilder'),
        (int)$page->revision,
        implode(' | ', $actions),
    ];
}
echo html_writer::table($table);
echo $OUTPUT->footer();
