<?php
// This file is part of Moodle - http://moodle.org/

namespace local_global_events\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for learner rewards, messages, and chat opt-in state.
 *
 * @package local_global_events
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
        $collection->add_database_table('local_ge_ledger', [
            'userid' => 'privacy:metadata:ledger',
            'points' => 'privacy:metadata:ledger',
            'sourcecomponent' => 'privacy:metadata:ledger',
            'sourceevent' => 'privacy:metadata:ledger',
            'timecreated' => 'privacy:metadata:ledger',
        ], 'privacy:metadata:ledger');
        $collection->add_database_table('local_ge_message', [
            'userid' => 'privacy:metadata:message',
            'templatekey' => 'privacy:metadata:message',
            'status' => 'privacy:metadata:message',
        ], 'privacy:metadata:message');
        $collection->add_database_table('local_ge_chatstate', [
            'userid' => 'privacy:metadata:chatstate',
            'addresshash' => 'privacy:metadata:chatstate',
        ], 'privacy:metadata:chatstate');
        return $collection;
    }

    /**
     * User contexts.
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
                   AND (
                       EXISTS (SELECT 1 FROM {local_ge_ledger} l WHERE l.userid = :ledgeruser)
                       OR EXISTS (SELECT 1 FROM {local_ge_message} m WHERE m.userid = :messageuser)
                       OR EXISTS (SELECT 1 FROM {local_ge_chatstate} c WHERE c.userid = :chatuser)
                   )";
        $contexts->add_from_sql($sql, [
            'contextlevel' => CONTEXT_USER,
            'userid' => $userid,
            'ledgeruser' => $userid,
            'messageuser' => $userid,
            'chatuser' => $userid,
        ]);
        return $contexts;
    }

    /**
     * Export approved user records.
     *
     * @param approved_contextlist $contextlist Contexts.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $user = $contextlist->get_user();
        $context = \context_user::instance($user->id);
        if (!in_array($context->id, $contextlist->get_contextids(), true)) {
            return;
        }
        $ledger = array_values($DB->get_records(
            'local_ge_ledger',
            ['userid' => $user->id],
            'timecreated ASC',
            'companyid,courseid,pointstype,points,sourcecomponent,sourceevent,timecreated',
        ));
        $messages = array_values($DB->get_records(
            'local_ge_message',
            ['userid' => $user->id],
            'timecreated ASC',
            'companyid,channel,templatekey,status,timecreated,timemodified',
        ));
        writer::with_context($context)->export_data(
            [get_string('pluginname', 'local_global_events')],
            (object)['ledger' => $ledger, 'messages' => $messages],
        );
    }

    /**
     * Delete all user records in a user context.
     *
     * @param \context $context Context.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        if ($context instanceof \context_user) {
            self::delete_user((int)$context->instanceid);
        }
    }

    /**
     * Delete approved user records.
     *
     * @param approved_contextlist $contextlist Contexts.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_user) {
                self::delete_user((int)$context->instanceid);
            }
        }
    }

    /**
     * Privacy deletion is the controlled exception to ledger immutability.
     *
     * @param int $userid User.
     */
    private static function delete_user(int $userid): void {
        global $DB;

        $DB->delete_records('local_ge_message', ['userid' => $userid]);
        $DB->delete_records('local_ge_chatstate', ['userid' => $userid]);
        $DB->delete_records('local_ge_ledger', ['userid' => $userid]);
    }
}
