<?php
// This file is part of IOMAD - http://www.iomad.org/

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_iomadpagebuilder';
$plugin->version = 2026072500;
$plugin->requires = 2025100605;
$plugin->supported = [501, 501];
$plugin->maturity = MATURITY_ALPHA;
$plugin->release = '0.1.0';
$plugin->dependencies = [
    'local_iomad' => 2025100600,
];
