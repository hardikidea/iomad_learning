<?php
// This file is part of Moodle - https://moodle.org/

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $category = new admin_category('local_orgprofile_admin', get_string('pluginname', 'local_orgprofile'));
    $ADMIN->add('localplugins', $category);
    $ADMIN->add('local_orgprofile_admin', new admin_externalpage(
        'local_orgprofile_dashboard',
        get_string('organizationprofiles', 'local_orgprofile'),
        new moodle_url('/local/orgprofile/index.php'),
        'local/orgprofile:manage'
    ));
    foreach (['orgtype' => 'orgtypes', 'usertype' => 'usertypes', 'field' => 'fields',
            'form' => 'forms', 'category' => 'categories'] as $entity => $string) {
        $capability = $entity === 'field' ? 'local/orgprofile:managefields' :
            (in_array($entity, ['form', 'category'], true) ? 'local/orgprofile:manageforms' : 'local/orgprofile:manage');
        $ADMIN->add('local_orgprofile_admin', new admin_externalpage(
            'local_orgprofile_' . $entity,
            get_string($string, 'local_orgprofile'),
            new moodle_url('/local/orgprofile/manage.php', ['entity' => $entity]),
            $capability
        ));
    }
    $ADMIN->add('local_orgprofile_admin', new admin_externalpage(
        'local_orgprofile_formfields',
        get_string('formfields', 'local_orgprofile'),
        new moodle_url('/local/orgprofile/formfields.php'),
        'local/orgprofile:manageforms'
    ));
    $ADMIN->add('local_orgprofile_admin', new admin_externalpage(
        'local_orgprofile_company',
        get_string('companymapping', 'local_orgprofile'),
        new moodle_url('/local/orgprofile/company.php'),
        'local/orgprofile:manage'
    ));
    $ADMIN->add('local_orgprofile_admin', new admin_externalpage(
        'local_orgprofile_assignment',
        get_string('assignments', 'local_orgprofile'),
        new moodle_url('/local/orgprofile/assignment.php'),
        'local/orgprofile:manage'
    ));
    $settings = new admin_settingpage('local_orgprofile_settings', get_string('settings', 'local_orgprofile'));
    $settings->add(new admin_setting_configcheckbox(
        'local_orgprofile/showusernavigation',
        get_string('showusernavigation', 'local_orgprofile'),
        get_string('showusernavigation_desc', 'local_orgprofile'),
        1
    ));
    $settings->add(new admin_setting_configcheckbox(
        'local_orgprofile/allowownedit',
        get_string('allowownedit', 'local_orgprofile'),
        get_string('allowownedit_desc', 'local_orgprofile'),
        0
    ));
    $ADMIN->add('local_orgprofile_admin', $settings);
}
