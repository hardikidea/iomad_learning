<?php
// This file is part of Moodle - https://moodle.org/

defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'local/orgprofile:manage' => [
        'riskbitmask' => RISK_CONFIG,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
    ],
    'local/orgprofile:managefields' => [
        'riskbitmask' => RISK_CONFIG | RISK_PERSONAL,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
    ],
    'local/orgprofile:manageforms' => [
        'riskbitmask' => RISK_CONFIG,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
    ],
    'local/orgprofile:managecompanymapping' => [
        'riskbitmask' => RISK_CONFIG,
        'captype' => 'write',
        'contextlevel' => CONTEXT_COMPANY,
        'archetypes' => ['companymanager' => CAP_ALLOW, 'clientadministrator' => CAP_ALLOW],
    ],
    'local/orgprofile:assignusertype' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'write',
        'contextlevel' => CONTEXT_COMPANY,
        'archetypes' => ['companymanager' => CAP_ALLOW, 'clientadministrator' => CAP_ALLOW],
    ],
    'local/orgprofile:viewall' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
    ],
    'local/orgprofile:viewcompany' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_COMPANY,
        'archetypes' => ['companymanager' => CAP_ALLOW, 'clientadministrator' => CAP_ALLOW],
    ],
    'local/orgprofile:viewown' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_COMPANY,
        'archetypes' => ['user' => CAP_ALLOW],
    ],
    'local/orgprofile:editall' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
    ],
    'local/orgprofile:editcompany' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'write',
        'contextlevel' => CONTEXT_COMPANY,
        'archetypes' => ['companymanager' => CAP_ALLOW, 'clientadministrator' => CAP_ALLOW],
    ],
    'local/orgprofile:editown' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'write',
        'contextlevel' => CONTEXT_COMPANY,
        'archetypes' => ['user' => CAP_ALLOW],
    ],
    'local/orgprofile:viewsensitive' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_COMPANY,
    ],
    'local/orgprofile:editsensitive' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'write',
        'contextlevel' => CONTEXT_COMPANY,
    ],
];
