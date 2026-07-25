<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

/**
 * Add the learner event page to primary navigation.
 *
 * @param global_navigation $navigation Navigation.
 */
function local_global_events_extend_navigation(global_navigation $navigation): void {
    if (
        isloggedin()
        && !isguestuser()
        && has_capability(
            'local/global_events:view',
            context_system::instance(),
        )
    ) {
        $navigation->add(
            get_string('pluginname', 'local_global_events'),
            new moodle_url('/local/global_events/index.php'),
            navigation_node::TYPE_CUSTOM,
        );
    }
}
