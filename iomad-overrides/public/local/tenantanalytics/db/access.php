<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Capabilities for tenant analytics.
 *
 * @package    local_tenantanalytics
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'local/tenantanalytics:viewown' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'user' => CAP_ALLOW,
        ],
    ],
    'local/tenantanalytics:viewcompany' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_COMPANY,
        'archetypes' => [
            'companymanager' => CAP_ALLOW,
            'companydepartmentmanager' => CAP_ALLOW,
            'companyreporter' => CAP_ALLOW,
            'clientadministrator' => CAP_ALLOW,
        ],
    ],
    'local/tenantanalytics:viewpii' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_COMPANY,
        'archetypes' => [
            'companymanager' => CAP_ALLOW,
            'companydepartmentmanager' => CAP_ALLOW,
            'clientadministrator' => CAP_ALLOW,
        ],
    ],
    'local/tenantanalytics:manageschedules' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'write',
        'contextlevel' => CONTEXT_COMPANY,
        'archetypes' => [
            'companymanager' => CAP_ALLOW,
            'companyreporter' => CAP_ALLOW,
            'clientadministrator' => CAP_ALLOW,
        ],
    ],
];
