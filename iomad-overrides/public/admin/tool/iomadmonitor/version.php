<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'tool_iomadmonitor';
$plugin->version = 2026072502;
$plugin->requires = 2025100605;
$plugin->supported = [501, 501];
$plugin->maturity = MATURITY_STABLE;
$plugin->release = '1.2.0';
$plugin->dependencies = [
    'local_iomad' => 2025100600,
    'local_institutionpack' => 2026072400,
];
