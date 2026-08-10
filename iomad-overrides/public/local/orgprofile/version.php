<?php
// This file is part of Moodle - https://moodle.org/

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_orgprofile';
$plugin->version = 2026080906;
$plugin->requires = 2025100600;
$plugin->supported = [501, 501];
$plugin->maturity = MATURITY_STABLE;
$plugin->release = '1.3.4';
$plugin->dependencies = [
    'local_iomad' => 2026071051,
];
