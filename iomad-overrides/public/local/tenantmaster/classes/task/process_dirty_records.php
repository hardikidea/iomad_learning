<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\task;

use local_tenantmaster\local\projection_service;

/**
 * Recover dirty records if an ad-hoc task was interrupted.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class process_dirty_records extends \core\task\scheduled_task {
    /**
     * Task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_processdirty', 'local_tenantmaster');
    }

    /**
     * Process each tenant with available work.
     */
    public function execute(): void {
        global $DB;

        if (get_config('local_tenantmaster', 'autosync') === '0') {
            mtrace('Tenant Master automatic synchronization is paused.');
            return;
        }
        $deadline = microtime(true) + 45;
        $maxbatches = 20;
        $tenantids = $DB->get_fieldset_sql(
            "SELECT DISTINCT tenantid
               FROM {local_tenantmaster_dirty}
              WHERE state IN ('dirty', 'retryable')
                AND availabletime <= :now",
            ['now' => time()],
        );
        foreach ($tenantids as $tenantid) {
            try {
                $batches = 0;
                do {
                    (new projection_service())->process((int)$tenantid);
                    $batches++;
                    $pending = $DB->record_exists_select(
                        'local_tenantmaster_dirty',
                        "tenantid = :tenantid
                             AND state IN ('dirty', 'retryable')
                             AND availabletime <= :now",
                        ['tenantid' => $tenantid, 'now' => time()],
                    );
                } while ($pending && $batches < $maxbatches && microtime(true) < $deadline);

                if ($pending) {
                    mtrace('Tenant Master projection has additional work for a later scheduler run.');
                }
            } catch (\Throwable $exception) {
                mtrace('Tenant Master projection skipped after a retryable worker exception.');
            }
        }
    }
}
