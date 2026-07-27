<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('root', new admin_category(
        'local_tenantmaster',
        get_string('pluginname', 'local_tenantmaster'),
    ));
    $ADMIN->add('local_tenantmaster', new admin_externalpage(
        'local_tenantmaster_catalogue',
        get_string('globalmastertemplates', 'local_tenantmaster'),
        new moodle_url('/local/tenantmaster/index.php', ['section' => 'catalogue']),
        'local/tenantmaster:managecatalogue',
    ));

    $settings = new admin_settingpage(
        'local_tenantmaster_settings',
        get_string('settings', 'local_tenantmaster'),
    );
    $settings->add(new admin_setting_configcheckbox(
        'local_tenantmaster/autosync',
        get_string('autosync', 'local_tenantmaster'),
        get_string('autosync_help', 'local_tenantmaster'),
        1,
    ));
    $settings->add(new admin_setting_configtext(
        'local_tenantmaster/retrylimit',
        get_string('retrylimit', 'local_tenantmaster'),
        get_string('retrylimit_help', 'local_tenantmaster'),
        5,
        PARAM_INT,
    ));
    $settings->add(new admin_setting_configtext(
        'local_tenantmaster/importmaxrows',
        get_string('importmaxrows', 'local_tenantmaster'),
        get_string('importmaxrows_help', 'local_tenantmaster'),
        25000,
        PARAM_INT,
    ));
    $ADMIN->add('local_tenantmaster', $settings);
}
