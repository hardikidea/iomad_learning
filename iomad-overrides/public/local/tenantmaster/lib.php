<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

/**
 * Add Tenant Master to the primary navigation for authorised tenant managers.
 *
 * @param global_navigation $navigation Navigation.
 */
function local_tenantmaster_extend_navigation(global_navigation $navigation): void {
    if (!isloggedin() || isguestuser()) {
        return;
    }
    try {
        $access = \local_tenantmaster\local\access::resolve();
        if ($access->companyid() <= 0 && !is_siteadmin()) {
            return;
        }
        $navigation->add(
            get_string('pluginname', 'local_tenantmaster'),
            new moodle_url('/local/tenantmaster/index.php'),
            navigation_node::TYPE_CUSTOM,
        );
    } catch (\Throwable) {
        // Navigation must not break unrelated pages when no tenant is selected.
        return;
    }
}
