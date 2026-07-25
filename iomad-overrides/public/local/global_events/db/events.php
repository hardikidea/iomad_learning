<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\mod_quiz\event\attempt_submitted',
        'callback' => '\local_global_events\observer::quiz_submitted',
        'priority' => 9999,
    ],
    [
        'eventname' => '\mod_assign\event\assessable_submitted',
        'callback' => '\local_global_events\observer::assignment_submitted',
        'priority' => 9999,
    ],
    [
        'eventname' => '\core\event\course_completed',
        'callback' => '\local_global_events\observer::course_completed',
        'priority' => 9999,
    ],
];
