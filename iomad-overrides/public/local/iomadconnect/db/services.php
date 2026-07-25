<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_iomadconnect_get_catalogue' => [
        'classname' => \local_iomadconnect\external\get_catalogue::class,
        'description' => 'Return one company course catalogue using a replay-safe cursor.',
        'type' => 'read',
        'ajax' => false,
        'capabilities' => 'local/iomadconnect:read',
    ],
    'local_iomadconnect_apply_events' => [
        'classname' => \local_iomadconnect\external\apply_events::class,
        'description' => 'Apply idempotent company course, user, and enrolment events.',
        'type' => 'write',
        'ajax' => false,
        'capabilities' => 'local/iomadconnect:write',
    ],
];

$services = [
    'IOMAD Connect' => [
        'functions' => [
            'local_iomadconnect_get_catalogue',
            'local_iomadconnect_apply_events',
        ],
        'restrictedusers' => 1,
        'enabled' => 0,
        'shortname' => 'iomad_connect',
    ],
];
