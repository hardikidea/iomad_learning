<?php
// This file is part of Moodle - http://moodle.org/

namespace local_iomadcommerce\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\writer;

/**
 * Export commerce records and detach users during erasure.
 *
 * Financial records remain under the configured retention policy, but direct
 * user links are replaced with zero when a privacy erasure is approved.
 *
 * @package    local_iomadcommerce
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe stored personal identifiers.
     *
     * @param collection $collection Collection.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_iomadcommerce_order', [
            'userid' => 'privacy:metadata:order',
            'status' => 'privacy:metadata:order',
        ], 'privacy:metadata:order');
        $collection->add_database_table('local_iomadcommerce_seat', [
            'userid' => 'privacy:metadata:seat',
            'assignedby' => 'privacy:metadata:seat',
            'status' => 'privacy:metadata:seat',
        ], 'privacy:metadata:seat');
        return $collection;
    }

    /**
     * User contexts containing commerce data.
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
                       EXISTS (
                           SELECT 1
                             FROM {local_iomadcommerce_order} o
                            WHERE o.userid = :orderuserid
                       )
                       OR EXISTS (
                           SELECT 1
                             FROM {local_iomadcommerce_seat} s
                            WHERE s.userid = :seatuserid OR s.assignedby = :assignedby
                       )
                   )";
        $contexts->add_from_sql($sql, [
            'contextlevel' => CONTEXT_USER,
            'userid' => $userid,
            'orderuserid' => $userid,
            'seatuserid' => $userid,
            'assignedby' => $userid,
        ]);
        return $contexts;
    }

    /**
     * Export purchase and seat records.
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
        $orders = array_values($DB->get_records(
            'local_iomadcommerce_order',
            ['userid' => $user->id],
            'timecreated ASC',
            'id,externalid,status,provider,totalminor,currency,timecreated,timemodified',
        ));
        $seats = array_values($DB->get_records_select(
            'local_iomadcommerce_seat',
            'userid = :userid OR assignedby = :assignedby',
            ['userid' => $user->id, 'assignedby' => $user->id],
            'timeassigned ASC',
            'id,status,timeassigned,timerevoked',
        ));
        writer::with_context($context)->export_data(
            [get_string('pluginname', 'local_iomadcommerce')],
            (object)['orders' => $orders, 'seats' => $seats],
        );
    }

    /**
     * Detach all user-linked records in one user context.
     *
     * @param \context $context Context.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        if ($context instanceof \context_user) {
            self::detach_user((int)$context->instanceid);
        }
    }

    /**
     * Detach approved user data.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_user) {
                self::detach_user((int)$context->instanceid);
            }
        }
    }

    /**
     * Replace direct identifiers while preserving accounting state.
     *
     * @param int $userid User.
     */
    private static function detach_user(int $userid): void {
        global $DB;

        $DB->set_field('local_iomadcommerce_order', 'userid', 0, ['userid' => $userid]);
        $DB->set_field('local_iomadcommerce_seat', 'userid', 0, ['userid' => $userid]);
        $DB->set_field('local_iomadcommerce_seat', 'assignedby', 0, ['assignedby' => $userid]);
        $DB->set_field('local_iomadcommerce_event', 'actorid', 0, ['actorid' => $userid]);
    }
}
