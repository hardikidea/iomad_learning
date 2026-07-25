<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\task;

use local_tenantmaster\local\projection_service;

/**
 * Automatically process a tenant/module after validated CRUD.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class sync_entity extends \core\task\adhoc_task {
    /**
     * Run queued projection.
     */
    public function execute(): void {
        $data = $this->get_custom_data();
        if (empty($data->force) && get_config('local_tenantmaster', 'autosync') === '0') {
            mtrace('Tenant Master automatic synchronization is paused.');
            return;
        }
        (new projection_service())->process(
            (int)$data->tenantid,
            (string)($data->module ?? ''),
        );
    }
}
