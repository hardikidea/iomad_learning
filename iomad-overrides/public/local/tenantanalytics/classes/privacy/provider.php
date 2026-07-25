<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantanalytics\privacy;

use context_system;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\writer;

/**
 * Privacy provider for report schedules and run audits.
 *
 * @package    local_tenantanalytics
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe schedule and audit metadata.
     *
     * @param collection $collection Collection.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_tanalytics_schedule', [
            'companyid' => 'privacy:metadata:schedule:companyid',
            'userid' => 'privacy:metadata:schedule:userid',
            'reportkey' => 'privacy:metadata:schedule:reportkey',
            'dataformat' => 'privacy:metadata:schedule:dataformat',
            'frequency' => 'privacy:metadata:schedule:frequency',
            'filtersjson' => 'privacy:metadata:schedule:filters',
            'enabled' => 'privacy:metadata:schedule:enabled',
            'nextrun' => 'privacy:metadata:schedule:nextrun',
            'lastrun' => 'privacy:metadata:schedule:lastrun',
        ], 'privacy:metadata:schedule');
        $collection->add_database_table('local_tanalytics_run', [
            'companyid' => 'privacy:metadata:run:companyid',
            'userid' => 'privacy:metadata:run:userid',
            'reportkey' => 'privacy:metadata:run:reportkey',
            'rowcount' => 'privacy:metadata:run:rowcount',
            'checksum' => 'privacy:metadata:run:checksum',
            'status' => 'privacy:metadata:run:status',
            'timecreated' => 'privacy:metadata:run:timecreated',
        ], 'privacy:metadata:run');
        return $collection;
    }

    /**
     * Return contexts containing data for a user.
     *
     * @param int $userid User.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $contextlist->add_from_sql(
            'SELECT :contextid
               FROM {local_tanalytics_schedule}
              WHERE userid = :userid',
            ['contextid' => context_system::instance()->id, 'userid' => $userid]
        );
        return $contextlist;
    }

    /**
     * Export a user's schedules and audit records.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        if (!in_array(context_system::instance()->id, $contextlist->get_contextids(), true)) {
            return;
        }
        $userid = $contextlist->get_user()->id;
        $schedules = $DB->get_records('local_tanalytics_schedule', ['userid' => $userid]);
        $runs = $DB->get_records('local_tanalytics_run', ['userid' => $userid]);
        writer::with_context(context_system::instance())->export_data(
            [get_string('pluginname', 'local_tenantanalytics')],
            (object)[
                'schedules' => array_map(static fn(object $record): object => (object)[
                    'report' => $record->reportkey,
                    'format' => $record->dataformat,
                    'frequency' => $record->frequency,
                    'filters' => $record->filtersjson,
                    'enabled' => transform::yesno($record->enabled),
                    'next' => transform::datetime($record->nextrun),
                    'last' => transform::datetime($record->lastrun),
                ], array_values($schedules)),
                'runs' => array_map(static fn(object $record): object => (object)[
                    'report' => $record->reportkey,
                    'rows' => $record->rowcount,
                    'checksum' => $record->checksum,
                    'status' => $record->status,
                    'created' => transform::datetime($record->timecreated),
                ], array_values($runs)),
            ]
        );
    }

    /**
     * Delete analytics data in a context.
     *
     * @param \context $context Context.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if ($context->contextlevel === CONTEXT_SYSTEM) {
            $DB->delete_records('local_tanalytics_run');
            $DB->delete_records('local_tanalytics_schedule');
        }
    }

    /**
     * Delete analytics data for an approved user.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        if (!in_array(context_system::instance()->id, $contextlist->get_contextids(), true)) {
            return;
        }
        $userid = $contextlist->get_user()->id;
        $scheduleids = $DB->get_fieldset_select(
            'local_tanalytics_schedule',
            'id',
            'userid = :userid',
            ['userid' => $userid]
        );
        if ($scheduleids) {
            $DB->delete_records_list('local_tanalytics_run', 'scheduleid', $scheduleids);
        }
        $DB->delete_records('local_tanalytics_run', ['userid' => $userid]);
        $DB->delete_records('local_tanalytics_schedule', ['userid' => $userid]);
    }
}
