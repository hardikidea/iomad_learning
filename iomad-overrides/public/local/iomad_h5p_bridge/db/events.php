<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\mod_h5pactivity\event\statement_received',
        'callback' => '\local_iomad_h5p_bridge\observer::statement_received',
        'priority' => 9999,
    ],
];
