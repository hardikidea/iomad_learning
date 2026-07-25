<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Scheduled tasks for tenant analytics.
 *
 * @package    local_tenantanalytics
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => '\local_tenantanalytics\task\deliver_scheduled_reports',
        'blocking' => 0,
        'minute' => '17',
        'hour' => '*',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
];
