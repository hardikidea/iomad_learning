<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'local/iomadcommerce:viewcatalogue' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'user' => CAP_ALLOW,
        ],
    ],
    'local/iomadcommerce:viewpurchases' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'user' => CAP_ALLOW,
        ],
    ],
    'local/iomadcommerce:manage' => [
        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS | RISK_CONFIG,
        'captype' => 'write',
        'contextlevel' => CONTEXT_COMPANY,
        'archetypes' => [
            'companymanager' => CAP_ALLOW,
            'clientadministrator' => CAP_ALLOW,
        ],
    ],
    'local/iomadcommerce:assignseats' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'write',
        'contextlevel' => CONTEXT_COMPANY,
        'archetypes' => [
            'companymanager' => CAP_ALLOW,
            'companydepartmentmanager' => CAP_ALLOW,
            'clientadministrator' => CAP_ALLOW,
        ],
    ],
];
