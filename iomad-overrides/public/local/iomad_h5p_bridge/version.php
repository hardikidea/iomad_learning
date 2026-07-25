<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_iomad_h5p_bridge';
$plugin->version = 2026072500;
$plugin->requires = 2025100605;
$plugin->supported = [501, 501];
$plugin->maturity = MATURITY_BETA;
$plugin->release = '0.1.0';
$plugin->dependencies = [
    'local_global_events' => 2026072500,
    'mod_h5pactivity' => ANY_VERSION,
];
