<?php
// This file is part of Moodle - http://moodle.org/

namespace block_iomaddashboard\local;

/**
 * Private per-user to-do storage.
 *
 * @package    block_iomaddashboard
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class todo_repository {
    /**
     * Return one user's tasks.
     *
     * @param int $userid User ID.
     * @param int $limit Result limit.
     * @return array
     */
    public function list_for_user(int $userid, int $limit = 20): array {
        global $DB;

        return array_values($DB->get_records(
            'block_iomaddashboard_todo',
            ['userid' => $userid],
            'completed ASC, duedate ASC, id ASC',
            '*',
            0,
            min(100, max(1, $limit)),
        ));
    }

    /**
     * Add a task for the owning user.
     *
     * @param int $userid User ID.
     * @param int $companyid Company ID.
     * @param string $text Task text.
     * @param int $duedate Due timestamp.
     * @return \stdClass
     */
    public function create(int $userid, int $companyid, string $text, int $duedate = 0): \stdClass {
        global $DB;

        $text = trim(clean_param($text, PARAM_TEXT));
        if ($text === '') {
            throw new \invalid_parameter_exception('Task text is required.');
        }
        $now = time();
        $record = (object)[
            'userid' => $userid,
            'companyid' => max(0, $companyid),
            'tasktext' => $text,
            'duedate' => max(0, $duedate),
            'completed' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $record->id = $DB->insert_record('block_iomaddashboard_todo', $record);
        return $record;
    }

    /**
     * Set task completion for the owner.
     *
     * @param int $id Task ID.
     * @param int $userid Owner ID.
     * @param bool $completed Completion state.
     */
    public function set_completed(int $id, int $userid, bool $completed): void {
        global $DB;

        $record = $DB->get_record(
            'block_iomaddashboard_todo',
            ['id' => $id, 'userid' => $userid],
            '*',
            MUST_EXIST,
        );
        $record->completed = (int)$completed;
        $record->timemodified = time();
        $DB->update_record('block_iomaddashboard_todo', $record);
    }

    /**
     * Delete an owned task.
     *
     * @param int $id Task ID.
     * @param int $userid Owner ID.
     */
    public function delete(int $id, int $userid): void {
        global $DB;

        if (!$DB->record_exists('block_iomaddashboard_todo', ['id' => $id, 'userid' => $userid])) {
            throw new \dml_missing_record_exception('block_iomaddashboard_todo');
        }
        $DB->delete_records('block_iomaddashboard_todo', ['id' => $id, 'userid' => $userid]);
    }
}
