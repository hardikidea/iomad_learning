<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_iomadconnect', get_string('pluginname', 'local_iomadconnect'));
    $settings->add(new admin_setting_configtext(
        'local_iomadconnect/authmethod',
        get_string('authmethod', 'local_iomadconnect'),
        get_string('authmethod_desc', 'local_iomadconnect'),
        'iomadoidc',
        PARAM_PLUGIN,
    ));
    $settings->add(new admin_setting_configcheckbox(
        'local_iomadconnect/allowinsecurelocal',
        get_string('allowinsecurelocal', 'local_iomadconnect'),
        get_string('allowinsecurelocal_desc', 'local_iomadconnect'),
        0,
    ));
    $ADMIN->add('localplugins', $settings);
}
