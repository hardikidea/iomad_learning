<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_global_events';
$plugin->version = 2026072600;
$plugin->requires = 2025100605;
$plugin->supported = [501, 501];
$plugin->maturity = MATURITY_STABLE;
$plugin->release = '0.4.0';
$plugin->dependencies = [
    'local_iomad' => 2025100600,
];
