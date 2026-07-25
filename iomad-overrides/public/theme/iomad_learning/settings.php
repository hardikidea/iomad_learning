<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

use theme_iomad_learning\local\token_catalog;

if ($ADMIN->fulltree) {
    $settings = new theme_boost_admin_settingspage_tabs(
        'themesettingiomad_learning',
        get_string('configtitle', 'theme_iomad_learning'),
    );
    foreach (token_catalog::groups() as $group => $title) {
        $page = new admin_settingpage('theme_iomad_learning_' . $group, $title);
        foreach (token_catalog::definitions() as $key => $definition) {
            if ($definition['group'] !== $group) {
                continue;
            }
            $name = 'theme_iomad_learning/' . $key;
            $description = get_string('tokendesc', 'theme_iomad_learning', token_catalog::css_name($key));
            if ($definition['type'] === 'colour') {
                $setting = new admin_setting_configcolourpicker(
                    $name,
                    $definition['label'],
                    $description,
                    $definition['default'],
                );
            } else if ($definition['type'] === 'boolean') {
                $setting = new admin_setting_configcheckbox(
                    $name,
                    $definition['label'],
                    $description,
                    (int)$definition['default'],
                );
            } else {
                $setting = new admin_setting_configselect(
                    $name,
                    $definition['label'],
                    $description,
                    $definition['default'],
                    $definition['options'],
                );
            }
            $setting->set_updatedcallback('theme_reset_all_caches');
            $page->add($setting);
        }
        $settings->add($page);
    }

    $page = new admin_settingpage(
        'theme_iomad_learning_assets',
        get_string('assetsettings', 'theme_iomad_learning'),
    );
    foreach (
        [
        'logo' => ['.png', '.jpg', '.jpeg', '.webp', '.svg'],
        'compactlogo' => ['.png', '.jpg', '.jpeg', '.webp', '.svg'],
        'favicon' => ['.ico', '.png'],
        'loginbackgroundimage' => ['.png', '.jpg', '.jpeg', '.webp'],
        'customfont' => ['.woff2'],
        ] as $filearea => $types
    ) {
        $setting = new admin_setting_configstoredfile(
            'theme_iomad_learning/' . $filearea,
            get_string($filearea, 'theme_iomad_learning'),
            get_string($filearea . '_desc', 'theme_iomad_learning'),
            $filearea,
            0,
            ['maxfiles' => 1, 'accepted_types' => $types],
        );
        $setting->set_updatedcallback('theme_reset_all_caches');
        $page->add($setting);
    }
    $settings->add($page);

    $page = new admin_settingpage(
        'theme_iomad_learning_advanced',
        get_string('advancedsettings', 'theme_iomad_learning'),
    );
    $setting = new admin_setting_scsscode(
        'theme_iomad_learning/customscss',
        get_string('customscss', 'theme_iomad_learning'),
        get_string('customscss_desc', 'theme_iomad_learning'),
        '',
        PARAM_RAW,
    );
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);
    $settings->add($page);
}

$ADMIN->add('themes', new admin_externalpage(
    'theme_iomad_learning_customizer',
    get_string('customizer', 'theme_iomad_learning'),
    new moodle_url('/theme/iomad_learning/customizer.php'),
    'moodle/site:config',
));
