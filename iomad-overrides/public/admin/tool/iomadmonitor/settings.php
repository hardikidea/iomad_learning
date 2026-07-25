<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('reports', new admin_externalpage(
        'tool_iomadmonitor_index',
        get_string('pluginname', 'tool_iomadmonitor'),
        new moodle_url('/admin/tool/iomadmonitor/index.php'),
        'tool/iomadmonitor:view',
    ));
    $settings = new admin_settingpage('tool_iomadmonitor', get_string('pluginname', 'tool_iomadmonitor'));
    $settings->add(new admin_setting_configduration(
        'tool_iomadmonitor/cronmaxage',
        get_string('cronmaxage', 'tool_iomadmonitor'),
        get_string('cronmaxage_desc', 'tool_iomadmonitor'),
        600,
        60,
    ));
    $settings->add(new admin_setting_configduration(
        'tool_iomadmonitor/backupmaxage',
        get_string('backupmaxage', 'tool_iomadmonitor'),
        get_string('backupmaxage_desc', 'tool_iomadmonitor'),
        86400,
        3600,
    ));
    $settings->add(new admin_setting_configtext(
        'tool_iomadmonitor/minfreedisk',
        get_string('minfreedisk', 'tool_iomadmonitor'),
        get_string('minfreedisk_desc', 'tool_iomadmonitor'),
        10,
        PARAM_INT,
    ));
    $settings->add(new admin_setting_configduration(
        'tool_iomadmonitor/alertcooldown',
        get_string('alertcooldown', 'tool_iomadmonitor'),
        get_string('alertcooldown_desc', 'tool_iomadmonitor'),
        3600,
        300,
    ));
    $ADMIN->add('server', $settings);
}
