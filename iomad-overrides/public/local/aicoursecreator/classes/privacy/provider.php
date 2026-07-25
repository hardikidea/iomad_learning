<?php
// This file is part of IOMAD - http://www.iomad.org/

namespace local_aicoursecreator\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy provider for AI course workflow data.
 */
final class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_aicoursecreator_draft', [
            'title' => 'privacy:metadata:draft',
            'brief' => 'privacy:metadata:draft',
            'definition' => 'privacy:metadata:draft',
            'createdby' => 'privacy:metadata:draft',
            'reviewedby' => 'privacy:metadata:draft',
            'publishedby' => 'privacy:metadata:draft',
        ], 'privacy:metadata:draft');
        $collection->add_database_table('local_aicoursecreator_audit', [
            'actorid' => 'privacy:metadata:audit',
            'eventname' => 'privacy:metadata:audit',
            'metadatajson' => 'privacy:metadata:audit',
        ], 'privacy:metadata:audit');
        $collection->add_database_table('local_aicoursecreator_quota', [
            'companyid' => 'privacy:metadata:quota',
            'creditsused' => 'privacy:metadata:quota',
        ], 'privacy:metadata:quota');
        $collection->add_external_location_link('core_ai', [
            'brief' => 'privacy:metadata:draft',
            'definition' => 'privacy:metadata:draft',
        ], 'privacy:metadata:draft');
        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();
        if (
            $DB->record_exists_select(
                'local_aicoursecreator_draft',
                'createdby = :userid OR reviewedby = :userid OR publishedby = :userid',
                ['userid' => $userid]
            ) || $DB->record_exists('local_aicoursecreator_audit', ['actorid' => $userid])
        ) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist): void {
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        foreach (['createdby', 'reviewedby', 'publishedby'] as $field) {
            $userlist->add_from_sql(
                $field,
                "SELECT {$field} FROM {local_aicoursecreator_draft} WHERE {$field} IS NOT NULL",
                []
            );
        }
        $userlist->add_from_sql('actorid', 'SELECT actorid FROM {local_aicoursecreator_audit}', []);
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        if (!$contextlist->get_contexts() || !$contextlist->get_user()) {
            return;
        }
        $userid = $contextlist->get_user()->id;
        $drafts = array_values($DB->get_records_select(
            'local_aicoursecreator_draft',
            'createdby = :userid OR reviewedby = :userid OR publishedby = :userid',
            ['userid' => $userid],
            'timecreated ASC'
        ));
        foreach ($drafts as $draft) {
            $draft->timecreated = transform::datetime($draft->timecreated);
            $draft->timemodified = transform::datetime($draft->timemodified);
        }
        writer::with_context(\context_system::instance())->export_data(
            [get_string('pluginname', 'local_aicoursecreator')],
            (object)['drafts' => $drafts]
        );
    }

    public static function delete_data_for_all_users_in_context(\context $context): void {
        // Institutional course content and immutable audit records follow the configured retention policy.
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        // Moodle retains institutional authorship against the anonymised core user record.
    }

    public static function delete_data_for_users(approved_userlist $userlist): void {
        // Moodle retains institutional authorship against anonymised core user records.
    }
}
