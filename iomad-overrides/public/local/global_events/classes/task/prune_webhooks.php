<?php
// This file is part of Moodle - http://moodle.org/

namespace local_global_events\task;

/**
 * Remove expired replay claims after their security window.
 *
 * @package local_global_events
 */
final class prune_webhooks extends \core\task\scheduled_task {
    /**
     * Task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('taskprunewebhooks', 'local_global_events');
    }

    /**
     * Execute.
     */
    public function execute(): void {
        global $DB;

        $DB->delete_records_select('local_ge_webhook', 'timecreated < :cutoff', [
            'cutoff' => time() - (7 * DAYSECS),
        ]);
    }
}
