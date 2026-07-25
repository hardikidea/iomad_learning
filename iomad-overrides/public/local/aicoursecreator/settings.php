<?php
// This file is part of IOMAD - http://www.iomad.org/

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'local_aicoursecreator',
        get_string('pluginname', 'local_aicoursecreator')
    );
    $settings->add(new admin_setting_configtext(
        'local_aicoursecreator/defaultcredits',
        get_string('defaultcredits', 'local_aicoursecreator'),
        get_string('defaultcredits_help', 'local_aicoursecreator'),
        300,
        PARAM_INT
    ));
    $ADMIN->add('courses', $settings);
}
