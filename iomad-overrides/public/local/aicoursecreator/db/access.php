<?php
// This file is part of IOMAD - http://www.iomad.org/

defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'local/aicoursecreator:manage' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_COMPANY,
        'riskbitmask' => RISK_SPAM | RISK_DATALOSS | RISK_XSS,
        'archetypes' => [
            'companymanager' => CAP_ALLOW,
            'clientadministrator' => CAP_ALLOW,
        ],
    ],
    'local/aicoursecreator:approve' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_COMPANY,
        'riskbitmask' => RISK_DATALOSS | RISK_XSS,
        'archetypes' => [
            'companymanager' => CAP_ALLOW,
        ],
    ],
    'local/aicoursecreator:publish' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_COMPANY,
        'riskbitmask' => RISK_DATALOSS | RISK_XSS,
        'archetypes' => [
            'companymanager' => CAP_ALLOW,
        ],
    ],
];
