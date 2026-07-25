<?php
// This file is part of Moodle - http://moodle.org/

namespace block_iomaddashboard\privacy;

use context_system;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\writer;

/**
 * Privacy provider for private dashboard tasks.
 *
 * @package    block_iomaddashboard
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe stored task metadata.
     *
     * @param collection $collection Collection.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('block_iomaddashboard_todo', [
            'userid' => 'privacy:metadata:todo:userid',
            'companyid' => 'privacy:metadata:todo:companyid',
            'tasktext' => 'privacy:metadata:todo:tasktext',
            'duedate' => 'privacy:metadata:todo:duedate',
            'completed' => 'privacy:metadata:todo:completed',
            'timecreated' => 'privacy:metadata:todo:timecreated',
            'timemodified' => 'privacy:metadata:todo:timemodified',
        ], 'privacy:metadata:todo');
        return $collection;
    }

    /**
     * Tasks live in the system context.
     *
     * @param int $userid User ID.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $contextlist->add_from_sql(
            'SELECT :contextid
               FROM {block_iomaddashboard_todo}
              WHERE userid = :userid',
            ['contextid' => context_system::instance()->id, 'userid' => $userid],
        );
        return $contextlist;
    }

    /**
     * Export private tasks.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        if (!in_array(context_system::instance()->id, $contextlist->get_contextids(), true)) {
            return;
        }
        $tasks = $DB->get_records('block_iomaddashboard_todo', ['userid' => $contextlist->get_user()->id]);
        $export = [];
        foreach ($tasks as $task) {
            $export[] = (object)[
                'task' => $task->tasktext,
                'due' => transform::datetime($task->duedate),
                'completed' => transform::yesno($task->completed),
                'created' => transform::datetime($task->timecreated),
                'modified' => transform::datetime($task->timemodified),
            ];
        }
        writer::with_context(context_system::instance())->export_data(
            [get_string('pluginname', 'block_iomaddashboard')],
            (object)['tasks' => $export],
        );
    }

    /**
     * Delete task data in the system context.
     *
     * @param \context $context Context.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if ($context->contextlevel === CONTEXT_SYSTEM) {
            $DB->delete_records('block_iomaddashboard_todo');
        }
    }

    /**
     * Delete one user's approved task data.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        if (in_array(context_system::instance()->id, $contextlist->get_contextids(), true)) {
            $DB->delete_records('block_iomaddashboard_todo', ['userid' => $contextlist->get_user()->id]);
        }
    }
}
