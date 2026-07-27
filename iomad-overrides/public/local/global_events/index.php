<?php
// This file is part of Moodle - http://moodle.org/

require(__DIR__ . '/../../../config.php');

require_login();
require_capability('local/global_events:view', context_system::instance());

$requestedcompanyid = optional_param('companyid', 0, PARAM_INT);
if (is_siteadmin() && $requestedcompanyid <= 0) {
    $requestedcompanyid = (int)($SESSION->currenteditingcompany ?? 0);
}
$scope = \local_global_events\local\tenant_scope::current($requestedcompanyid);
$dashboard = new \local_global_events\output\dashboard($scope, (int)$USER->id);

$PAGE->set_url('/local/global_events/index.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('pluginname', 'local_global_events'));
$PAGE->set_heading(get_string('pluginname', 'local_global_events'));

echo $OUTPUT->header();
$companycontext = \local_iomad\custom_context\context_company::instance($scope->companyid());
if (
    is_siteadmin()
        || \local_iomad\iomad::has_capability(
            'local/global_events:manage',
            $companycontext,
            $scope->companyid(),
        )
) {
    echo $OUTPUT->single_button(
        new moodle_url('/local/global_events/manage.php', ['companyid' => $scope->companyid()]),
        get_string('eventmanagement', 'local_global_events'),
        'get',
    );
}
echo $OUTPUT->render_from_template(
    'local_global_events/event_page',
    $dashboard->export_for_template($OUTPUT),
);
echo $OUTPUT->footer();
