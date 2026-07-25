<?php
// This file is part of Moodle - http://moodle.org/

require(__DIR__ . '/../../../config.php');

require_login();
require_sesskey();
require_capability('local/global_events:view', context_system::instance());

$eventid = required_param('eventid', PARAM_INT);
$scope = \local_global_events\local\tenant_scope::current();
(new \local_global_events\local\event_enrolment_service())->enrol($scope, $eventid);

redirect(new moodle_url('/local/global_events/index.php'));
