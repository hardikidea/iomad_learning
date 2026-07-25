<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'local/global_events:view' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'user' => CAP_ALLOW,
        ],
    ],
    'local/global_events:manage' => [
        'riskbitmask' => RISK_DATALOSS | RISK_CONFIG,
        'captype' => 'write',
        'contextlevel' => CONTEXT_COMPANY,
        'archetypes' => [
            'companymanager' => CAP_ALLOW,
            'clientadministrator' => CAP_ALLOW,
        ],
    ],
    'local/global_events:viewreports' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_COMPANY,
        'archetypes' => [
            'companymanager' => CAP_ALLOW,
            'companyreporter' => CAP_ALLOW,
            'clientadministrator' => CAP_ALLOW,
        ],
    ],
    'local/global_events:award' => [
        'riskbitmask' => RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_COMPANY,
        'archetypes' => [
            'companymanager' => CAP_ALLOW,
            'clientadministrator' => CAP_ALLOW,
        ],
    ],
    'local/global_events:managegateways' => [
        'riskbitmask' => RISK_CONFIG | RISK_PERSONAL,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [],
    ],
];
