<?php
// This file is part of Moodle - http://moodle.org/

use local_global_events\form\event;
use local_global_events\local\event_repository;
use local_global_events\local\tenant_scope;
use local_iomad\company;
use local_iomad\custom_context\context_company;
use local_iomad\iomad;

require(__DIR__ . '/../../../config.php');

require_login();

$requestedcompanyid = optional_param('companyid', 0, PARAM_INT);
$editid = optional_param('editid', 0, PARAM_INT);
if (is_siteadmin() && $requestedcompanyid <= 0) {
    $requestedcompanyid = (int)($SESSION->currenteditingcompany ?? 0);
    if (
        $requestedcompanyid <= 0
        || !$DB->record_exists('local_iomad_companies', ['id' => $requestedcompanyid])
    ) {
        $requestedcompanyid = (int)$DB->get_field_select(
            'local_iomad_companies',
            'id',
            'suspended = :suspended',
            ['suspended' => 0],
            IGNORE_MULTIPLE,
        );
    }
}
if (is_siteadmin() && $requestedcompanyid <= 0) {
    $url = new moodle_url('/local/global_events/manage.php');
    $PAGE->set_url($url);
    $PAGE->set_context(context_system::instance());
    $PAGE->set_pagelayout('admin');
    $PAGE->set_title(get_string('eventmanagement', 'local_global_events'));
    $PAGE->set_heading(get_string('eventmanagement', 'local_global_events'));
    $PAGE->navbar->add(get_string('pluginname', 'local_global_events'), new moodle_url('/local/global_events/index.php'));
    $PAGE->navbar->add(get_string('eventmanagement', 'local_global_events'));
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('companyrequiredevents', 'local_global_events'), 'info');
    echo $OUTPUT->single_button(
        new moodle_url('/blocks/iomad_company_admin/company_edit_form.php', ['createnew' => 1]),
        get_string('createcompany', 'local_global_events'),
        'get',
    );
    echo $OUTPUT->footer();
    exit;
}
$scope = tenant_scope::current($requestedcompanyid);
$companycontext = context_company::instance($scope->companyid());
if (!is_siteadmin()) {
    iomad::require_capability('local/global_events:manage', $companycontext, $scope->companyid());
}

$url = new moodle_url('/local/global_events/manage.php', ['companyid' => $scope->companyid()]);
$PAGE->set_url($url);
$PAGE->set_context($companycontext);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('eventmanagement', 'local_global_events'));
$PAGE->set_heading(get_string('eventmanagement', 'local_global_events'));
$PAGE->navbar->add(get_string('pluginname', 'local_global_events'), new moodle_url('/local/global_events/index.php'));
$PAGE->navbar->add(get_string('eventmanagement', 'local_global_events'));

$company = new company($scope->companyid());
$courses = [0 => get_string('nocourse', 'local_global_events')];
foreach ($company->get_menu_courses(shared: true, default: false, includehidden: true) as $courseid => $coursename) {
    $courses[(int)$courseid] = format_string($coursename);
}
$companyids = $scope->report_companyids(true);
if (is_siteadmin()) {
    $companyids = array_map('intval', $DB->get_fieldset_select(
        'local_iomad_companies',
        'id',
        'suspended = :suspended',
        ['suspended' => 0],
    ));
}
$companies = [];
if ($companyids) {
    [$insql, $params] = $DB->get_in_or_equal($companyids, SQL_PARAMS_NAMED, 'eventcompany');
    $companies = array_map(
        static fn(string $name): string => format_string($name),
        $DB->get_records_select_menu('local_iomad_companies', "id $insql", $params, 'name', 'id,name'),
    );
}

$repository = new event_repository();
$form = new event($url, [
    'editing' => $editid > 0,
    'companyid' => $scope->companyid(),
    'courses' => $courses,
    'companies' => $companies,
    'allowglobal' => is_siteadmin(),
]);
if ($form->is_cancelled()) {
    redirect($url);
}
if ($data = $form->get_data()) {
    require_sesskey();
    $record = $repository->upsert(
        $scope,
        (array)$data,
        array_values(array_filter(array_map('intval', (array)$data->companyids))),
        (int)$USER->id,
    );
    redirect($url, get_string('eventsaved', 'local_global_events', format_string($record->name)));
}
if ($editid > 0) {
    $record = $repository->get_owned($scope, $editid);
    $record->companyid = $scope->companyid();
    $record->companyids = $repository->company_ids($scope, $editid);
    $form->set_data($record);
} else {
    $form->set_data((object)[
        'companyid' => $scope->companyid(),
        'visibility' => 'companies',
        'companyids' => [$scope->companyid()],
        'status' => 'draft',
    ]);
}

echo $OUTPUT->header();
echo html_writer::div(
    $OUTPUT->single_button(
        new moodle_url('/local/global_events/index.php', ['companyid' => $scope->companyid()]),
        get_string('openeventdashboard', 'local_global_events'),
        'get',
    )
    . $OUTPUT->single_button(
        new moodle_url('/calendar/view.php', ['view' => 'month']),
        get_string('openmoodlecalendar', 'local_global_events'),
        'get',
    ),
    'd-flex flex-wrap gap-2 mb-3',
);

$table = new html_table();
$table->head = [
    get_string('eventname', 'local_global_events'),
    get_string('eventidnumber', 'local_global_events'),
    get_string('status'),
    get_string('visibility', 'local_global_events'),
    get_string('eventstart', 'local_global_events'),
    get_string('eventend', 'local_global_events'),
    get_string('actions'),
];
foreach ($repository->managed($scope) as $record) {
    $table->data[] = [
        format_string($record->name),
        s($record->idnumber),
        s($record->status),
        s($record->visibility),
        $record->timestart ? userdate($record->timestart) : get_string('notset', 'local_global_events'),
        $record->timeend ? userdate($record->timeend) : get_string('notset', 'local_global_events'),
        html_writer::link(
            new moodle_url('/local/global_events/manage.php', [
                'companyid' => $scope->companyid(),
                'editid' => $record->id,
            ]),
            get_string('edit'),
            ['class' => 'btn btn-secondary btn-sm'],
        ),
    ];
}
if (!$table->data) {
    $table->data[] = [['text' => get_string('nomanagedevents', 'local_global_events'), 'colspan' => 7]];
}
echo html_writer::table($table);
echo $OUTPUT->heading($editid
    ? get_string('editevent', 'local_global_events')
    : get_string('addevent', 'local_global_events'), 3);
$form->display();
echo $OUTPUT->footer();
