<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'block_gamification_telemetry';
$plugin->version = 2026072501;
$plugin->requires = 2025100605;
$plugin->supported = [501, 501];
$plugin->maturity = MATURITY_BETA;
$plugin->release = '0.2.0';
$plugin->dependencies = [
    'local_global_events' => 2026072502,
];
