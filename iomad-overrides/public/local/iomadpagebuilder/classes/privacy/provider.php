<?php
// This file is part of IOMAD - http://www.iomad.org/

namespace local_iomadpagebuilder\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy provider for page author attribution.
 */
final class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_iomadpagebuilder_page', [
            'createdby' => 'privacy:metadata:page',
            'modifiedby' => 'privacy:metadata:page',
        ], 'privacy:metadata:page');
        $collection->add_database_table('local_iomadpagebuilder_rev', [
            'createdby' => 'privacy:metadata:revision',
        ], 'privacy:metadata:revision');
        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $contextlist->add_system_context();
        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist): void {
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        $userlist->add_from_sql(
            'createdby',
            'SELECT createdby FROM {local_iomadpagebuilder_page}',
            []
        );
        $userlist->add_from_sql(
            'modifiedby',
            'SELECT modifiedby FROM {local_iomadpagebuilder_page}',
            []
        );
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        // Page definitions are institutional content; user IDs are attribution only.
    }

    public static function delete_data_for_all_users_in_context(\context $context): void {
        // Institutional page content is retained when an author account is removed.
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        // Moodle user deletion preserves authored institutional content and anonymises the user record.
    }

    public static function delete_data_for_users(approved_userlist $userlist): void {
        // Moodle user deletion preserves authored institutional content and anonymises the user record.
    }
}
