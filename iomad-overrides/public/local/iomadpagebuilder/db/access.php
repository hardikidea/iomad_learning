<?php
// This file is part of IOMAD - http://www.iomad.org/

defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'local/iomadpagebuilder:manage' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_COMPANY,
        'riskbitmask' => RISK_SPAM | RISK_DATALOSS | RISK_XSS,
        'archetypes' => [
            'companymanager' => CAP_ALLOW,
            'clientadministrator' => CAP_ALLOW,
        ],
    ],
    'local/iomadpagebuilder:publish' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_COMPANY,
        'riskbitmask' => RISK_SPAM | RISK_DATALOSS | RISK_XSS,
        'archetypes' => [
            'companymanager' => CAP_ALLOW,
        ],
    ],
    'local/iomadpagebuilder:view' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_COMPANY,
        'archetypes' => [
            'companymanager' => CAP_ALLOW,
            'companydepartmentmanager' => CAP_ALLOW,
            'clientadministrator' => CAP_ALLOW,
            'clientreporter' => CAP_ALLOW,
            'companyreporter' => CAP_ALLOW,
        ],
    ],
];
