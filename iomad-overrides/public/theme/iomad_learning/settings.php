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
        'theme_iomad_learning_footer',
        get_string('footersettings', 'theme_iomad_learning'),
    );
    foreach (
        [
            'footerbrand' => ['IOMAD Learning', PARAM_TEXT],
            'footertagline' => ['Learning for every institution.', PARAM_TEXT],
            'footercontact' => ['', PARAM_EMAIL],
            'footerphone' => ['', PARAM_TEXT],
            'footersupporthours' => ['', PARAM_TEXT],
            'footerhelpurl' => ['', PARAM_URL],
            'footerprivacyurl' => ['', PARAM_URL],
            'footertermsurl' => ['', PARAM_URL],
            'footerfacebookurl' => ['', PARAM_URL],
            'footerinstagramurl' => ['', PARAM_URL],
            'footerlinkedinurl' => ['', PARAM_URL],
            'footerxurl' => ['', PARAM_URL],
            'footeryoutubeurl' => ['', PARAM_URL],
            'footerwhatsappurl' => ['', PARAM_URL],
            'footerlegal' => ['All rights reserved.', PARAM_TEXT],
        ] as $key => [$default, $paramtype]
    ) {
        $setting = new admin_setting_configtext(
            'theme_iomad_learning/' . $key,
            get_string($key, 'theme_iomad_learning'),
            get_string($key . '_desc', 'theme_iomad_learning'),
            $default,
            $paramtype,
        );
        $setting->set_updatedcallback('theme_reset_all_caches');
        $page->add($setting);
    }
    $setting = new admin_setting_configtextarea(
        'theme_iomad_learning/footeraddress',
        get_string('footeraddress', 'theme_iomad_learning'),
        get_string('footeraddress_desc', 'theme_iomad_learning'),
        '',
        PARAM_TEXT,
    );
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);
    foreach (
        [
            'footershowcorelinks' => 1,
            'footershowlogininfo' => 1,
            'footershowplatforminfo' => 1,
        ] as $key => $default
    ) {
        $setting = new admin_setting_configcheckbox(
            'theme_iomad_learning/' . $key,
            get_string($key, 'theme_iomad_learning'),
            get_string($key . '_desc', 'theme_iomad_learning'),
            $default,
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
