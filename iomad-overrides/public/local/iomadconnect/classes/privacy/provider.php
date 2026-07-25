<?php
// This file is part of Moodle - http://moodle.org/

namespace local_iomadconnect\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\writer;

/**
 * Synchronization privacy provider.
 *
 * @package    local_iomadconnect
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Metadata.
     *
     * @param collection $collection Collection.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_iomadconnect_event', [
            'entityid' => 'privacy:metadata:event',
            'payloadhash' => 'privacy:metadata:event',
        ], 'privacy:metadata:event');
        $collection->add_database_table('local_iomadconnect_link', [
            'externalid' => 'privacy:metadata:link',
            'localid' => 'privacy:metadata:link',
        ], 'privacy:metadata:link');
        return $collection;
    }

    /**
     * User contexts containing connector links.
     *
     * @param int $userid User.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contexts = new contextlist();
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                 WHERE ctx.contextlevel = :contextlevel
                   AND ctx.instanceid = :userid
                   AND EXISTS (
                       SELECT 1
                         FROM {local_iomadconnect_link} l
                        WHERE l.localid = :localid
                          AND l.entitytype IN ('user', 'enrolment')
                   )";
        $contexts->add_from_sql($sql, [
            'contextlevel' => CONTEXT_USER,
            'userid' => $userid,
            'localid' => $userid,
        ]);
        return $contexts;
    }

    /**
     * Export stable synchronization links.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $user = $contextlist->get_user();
        $context = \context_user::instance($user->id);
        if (!in_array($context->id, $contextlist->get_contextids(), true)) {
            return;
        }
        $links = array_values($DB->get_records_select(
            'local_iomadconnect_link',
            "localid = :userid AND entitytype IN ('user', 'enrolment')",
            ['userid' => $user->id],
            'timecreated ASC',
            'id,systemkey,entitytype,externalid,timecreated,timemodified',
        ));
        writer::with_context($context)->export_data(
            [get_string('pluginname', 'local_iomadconnect')],
            (object)['links' => $links],
        );
    }

    /**
     * Delete connector data for a user context.
     *
     * @param \context $context Context.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        if ($context instanceof \context_user) {
            self::delete_user_links((int)$context->instanceid);
        }
    }

    /**
     * Delete approved connector data.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_user) {
                self::delete_user_links((int)$context->instanceid);
            }
        }
    }

    /**
     * Remove user and connector-owned enrolment identities.
     *
     * @param int $userid User.
     */
    private static function delete_user_links(int $userid): void {
        global $DB;

        $userlinks = $DB->get_records('local_iomadconnect_link', [
            'entitytype' => 'user',
            'localid' => $userid,
        ]);
        foreach ($userlinks as $link) {
            $DB->delete_records('local_iomadconnect_event', [
                'companyid' => $link->companyid,
                'entitytype' => 'user',
                'entityid' => $link->externalid,
            ]);
        }
        $DB->delete_records_select(
            'local_iomadconnect_link',
            "localid = :userid AND entitytype IN ('user', 'enrolment')",
            ['userid' => $userid],
        );
    }
}
