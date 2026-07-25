<?php
// This file is part of Moodle - http://moodle.org/

use local_tenantanalytics\form\report_filter;
use local_tenantanalytics\local\access;
use local_tenantanalytics\local\exporter;
use local_tenantanalytics\local\filter_options;
use local_tenantanalytics\local\report_catalog;
use local_tenantanalytics\local\report_engine;

require_once(__DIR__ . '/../../config.php');

require_login();
$access = access::resolve();
$context = $access->get_context();
$url = new moodle_url('/local/tenantanalytics/index.php');
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('pluginname', 'local_tenantanalytics'));
$PAGE->set_heading(get_string('pluginname', 'local_tenantanalytics'));
$PAGE->requires->css('/local/tenantanalytics/styles.css');

$form = new report_filter(null, [
    'options' => new filter_options($access->get_scope()),
]);
$result = null;
$selectedreport = '';
if ($data = $form->get_data()) {
    $selectedreport = (string)$data->reportkey;
    $filters = [
        'since' => (int)$data->since,
        'until' => (int)$data->until,
        'courseid' => (int)$data->courseid,
        'cohortid' => (int)$data->cohortid,
        'groupid' => (int)$data->groupid,
    ];
    $result = (new report_engine())->generate($selectedreport, $access->get_scope(), $filters);
    if (!empty($data->downloadbutton)) {
        (new exporter())->download(
            'tenant-' . $selectedreport . '-' . gmdate('Ymd-His'),
            (string)$data->dataformat,
            $result
        );
        exit;
    }
}

echo $OUTPUT->header();
echo html_writer::start_div('local-tenantanalytics-actions');
if ($access->can_manage_schedules()) {
    echo $OUTPUT->single_button(
        new moodle_url('/local/tenantanalytics/schedule.php'),
        get_string('manageschedules', 'local_tenantanalytics'),
        'get'
    );
}
echo html_writer::end_div();
echo $OUTPUT->notification(get_string('timeestimatornotice', 'local_tenantanalytics'), 'info', false);
$form->display();

if ($result) {
    echo html_writer::tag('h2', report_catalog::all()[$selectedreport]);
    echo html_writer::tag(
        'p',
        get_string('resultsummary', 'local_tenantanalytics', count($result->get_rows())),
        ['class' => 'local-tenantanalytics-summary']
    );
    $table = new html_table();
    $table->head = array_values($result->get_columns());
    foreach (array_slice($result->get_rows(), 0, 500) as $row) {
        $table->data[] = array_map('s', array_values($row));
    }
    echo html_writer::table($table);
    if (count($result->get_rows()) > 500) {
        echo $OUTPUT->notification(get_string('previewlimit', 'local_tenantanalytics', 500), 'info', false);
    }
}
echo $OUTPUT->footer();
