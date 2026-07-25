<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Scheduled tasks for Tenant Master.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => '\local_tenantmaster\task\process_dirty_records',
        'blocking' => 0,
        'minute' => '*',
        'hour' => '*',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
    [
        'classname' => '\local_tenantmaster\task\detect_drift',
        'blocking' => 0,
        'minute' => '23',
        'hour' => '2',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
    [
        'classname' => '\local_tenantmaster\task\validate_tenants',
        'blocking' => 0,
        'minute' => '47',
        'hour' => '3',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
];
