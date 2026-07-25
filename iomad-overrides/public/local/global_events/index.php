<?php
// This file is part of Moodle - http://moodle.org/

require(__DIR__ . '/../../../config.php');

require_login();
require_capability('local/global_events:view', context_system::instance());

$scope = \local_global_events\local\tenant_scope::current();
$dashboard = new \local_global_events\output\dashboard($scope, (int)$USER->id);

$PAGE->set_url('/local/global_events/index.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('pluginname', 'local_global_events'));
$PAGE->set_heading(get_string('pluginname', 'local_global_events'));

echo $OUTPUT->header();
echo $OUTPUT->render_from_template(
    'local_global_events/event_page',
    $dashboard->export_for_template($OUTPUT),
);
echo $OUTPUT->footer();
