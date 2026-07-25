<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_global_events';
$plugin->version = 2026072502;
$plugin->requires = 2025100605;
$plugin->supported = [501, 501];
$plugin->maturity = MATURITY_BETA;
$plugin->release = '0.3.0';
$plugin->dependencies = [
    'local_iomad' => 2025100600,
];
