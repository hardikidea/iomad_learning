<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Version metadata for Tenant Master.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_tenantmaster';
$plugin->version = 2026072506;
$plugin->requires = 2025100600;
$plugin->supported = [501, 501];
$plugin->maturity = MATURITY_STABLE;
$plugin->release = '1.0.6';
$plugin->dependencies = [
    'local_iomad' => 2025100600,
];
