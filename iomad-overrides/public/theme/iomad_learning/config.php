<?php
// This file is part of IOMAD - http://www.iomad.org/

defined('MOODLE_INTERNAL') || die();

$THEME->name = 'iomad_learning';
$THEME->parents = ['boost'];
$THEME->sheets = [];
$THEME->editor_sheets = [];
$THEME->scss = function ($theme) {
    return theme_iomad_learning_get_main_scss_content($theme);
};
$THEME->extrascsscallback = 'theme_iomad_learning_get_extra_scss';
$THEME->prescsscallback = 'theme_iomad_learning_get_pre_scss';
$THEME->enable_dock = false;
$THEME->yuicssmodules = [];
$THEME->rendererfactory = 'theme_overridden_renderer_factory';
$THEME->addblockposition = BLOCK_ADDBLOCK_POSITION_FLATNAV;
$THEME->iconsystem = \theme_iomad_learning\output\icon_system_svg::class;
$THEME->usescourseindex = true;
