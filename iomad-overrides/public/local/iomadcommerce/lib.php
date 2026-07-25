<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

/**
 * Add catalogue and purchased courses to primary navigation.
 *
 * @param global_navigation $navigation Navigation.
 */
function local_iomadcommerce_extend_navigation(global_navigation $navigation): void {
    if (!isloggedin() || isguestuser()) {
        return;
    }
    $navigation->add(
        get_string('catalogue', 'local_iomadcommerce'),
        new moodle_url('/local/iomadcommerce/index.php'),
        navigation_node::TYPE_CUSTOM,
        null,
        'iomadcommerce_catalogue',
        new pix_icon('icon', '', 'local_iomadcommerce'),
    );
    $navigation->add(
        get_string('mycourses', 'local_iomadcommerce'),
        new moodle_url('/local/iomadcommerce/purchases.php'),
        navigation_node::TYPE_CUSTOM,
        null,
        'iomadcommerce_purchases',
    );
}
