<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Version metadata for tenant analytics.
 *
 * @package    local_tenantanalytics
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_tenantanalytics';
$plugin->version = 2026072501;
$plugin->requires = 2025100600;
$plugin->supported = [501, 501];
$plugin->maturity = MATURITY_STABLE;
$plugin->release = '1.0.1';
$plugin->dependencies = [
    'local_iomad' => 2025100600,
    'dataformat_csv' => 2025100600,
    'dataformat_excel' => 2025100600,
    'dataformat_ods' => 2025100600,
    'dataformat_pdf' => 2025100600,
];
