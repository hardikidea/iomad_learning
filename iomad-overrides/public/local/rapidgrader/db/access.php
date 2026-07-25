<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Capabilities for tenant rapid grading.
 *
 * @package    local_rapidgrader
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'local/rapidgrader:viewcompany' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_COMPANY,
        'archetypes' => [
            'companymanager' => CAP_ALLOW,
            'companydepartmentmanager' => CAP_ALLOW,
            'clientadministrator' => CAP_ALLOW,
        ],
    ],
    'local/rapidgrader:view' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
            'companycourseeditor' => CAP_ALLOW,
        ],
        'clonepermissionsfrom' => 'moodle/grade:viewall',
    ],
    'local/rapidgrader:grade' => [
        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
            'companycourseeditor' => CAP_ALLOW,
        ],
        'clonepermissionsfrom' => 'moodle/grade:edit',
    ],
    'local/rapidgrader:export' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
            'companycourseeditor' => CAP_ALLOW,
        ],
        'clonepermissionsfrom' => 'moodle/grade:export',
    ],
];
