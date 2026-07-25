<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\mod_scorm\event\status_submitted',
        'callback' => '\local_iomad_scorm_gen\observer::status_submitted',
        'priority' => 9999,
    ],
];
