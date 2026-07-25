<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Version metadata for the IOMAD video course format.
 *
 * @package    format_iomadvideo
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'format_iomadvideo';
$plugin->version = 2026072500;
$plugin->requires = 2025100600;
$plugin->supported = [501, 501];
$plugin->maturity = MATURITY_STABLE;
$plugin->release = '1.0.0';
$plugin->dependencies = [
    'format_topics' => 2025100600,
];
