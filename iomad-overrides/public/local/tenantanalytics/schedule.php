<?php
// This file is part of Moodle - http://moodle.org/

use local_tenantanalytics\form\schedule;
use local_tenantanalytics\local\access;
use local_tenantanalytics\local\filter_options;
use local_tenantanalytics\local\report_catalog;
use local_tenantanalytics\local\schedule_repository;

require_once(__DIR__ . '/../../config.php');

require_login();
$access = access::resolve();
if (!$access->can_manage_schedules()) {
    throw new required_capability_exception(
        $access->get_context(),
        'local/tenantanalytics:manageschedules',
        'nopermissions',
        ''
    );
}
$repository = new schedule_repository();
$id = optional_param('id', 0, PARAM_INT);
$delete = optional_param('delete', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);
$url = new moodle_url('/local/tenantanalytics/schedule.php');
$PAGE->set_context($access->get_context());
$PAGE->set_url($url);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('manageschedules', 'local_tenantanalytics'));
$PAGE->set_heading(get_string('manageschedules', 'local_tenantanalytics'));

if ($delete) {
    $record = $repository->get_owned($delete, (int)$USER->id);
    if ($confirm && confirm_sesskey()) {
        $repository->delete_owned((int)$record->id, (int)$USER->id);
        redirect($url, get_string('scheduledeleted', 'local_tenantanalytics'));
    }
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(
        get_string('confirmscheduledelete', 'local_tenantanalytics'),
        new moodle_url($url, ['delete' => $record->id, 'confirm' => 1, 'sesskey' => sesskey()]),
        $url
    );
    echo $OUTPUT->footer();
    exit;
}

$form = new schedule(null, [
    'options' => new filter_options($access->get_scope()),
]);
if ($id) {
    $record = $repository->get_owned($id, (int)$USER->id);
    $filters = json_decode($record->filtersjson, true, 8, JSON_THROW_ON_ERROR);
    $form->set_data((object)array_merge((array)$record, $filters));
}
if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/tenantanalytics/index.php'));
}
if ($data = $form->get_data()) {
    $repository->save($data, $access);
    redirect($url, get_string('schedulesaved', 'local_tenantanalytics'));
}

echo $OUTPUT->header();
$form->display();
$table = new html_table();
$table->head = [
    get_string('report', 'local_tenantanalytics'),
    get_string('frequency', 'local_tenantanalytics'),
    get_string('exportformat', 'local_tenantanalytics'),
    get_string('nextrun', 'local_tenantanalytics'),
    get_string('status'),
    get_string('actions'),
];
foreach ($repository->list_for_owner((int)$USER->id) as $record) {
    $actions = [
        html_writer::link(new moodle_url($url, ['id' => $record->id]), get_string('edit')),
        html_writer::link(new moodle_url($url, ['delete' => $record->id]), get_string('delete')),
    ];
    $table->data[] = [
        report_catalog::all()[$record->reportkey] ?? s($record->reportkey),
        schedule_repository::frequencies()[$record->frequency] ?? s($record->frequency),
        report_catalog::formats()[$record->dataformat] ?? s($record->dataformat),
        userdate((int)$record->nextrun),
        $record->enabled ? get_string('enabled', 'local_tenantanalytics') : get_string('disabled'),
        implode(' | ', $actions),
    ];
}
echo html_writer::table($table);
echo $OUTPUT->footer();
