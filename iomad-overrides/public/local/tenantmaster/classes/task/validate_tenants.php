<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\task;

use local_tenantmaster\local\validation_service;

/**
 * Scheduled complete tenant validation.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class validate_tenants extends \core\task\scheduled_task {
    /**
     * Task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_validatetenants', 'local_tenantmaster');
    }

    /**
     * Validate every active tenant.
     */
    public function execute(): void {
        global $DB;

        $tenantids = $DB->get_fieldset_select(
            'local_tenantmaster_tenant',
            'id',
            'status = :status',
            ['status' => 'active'],
        );
        foreach ($tenantids as $tenantid) {
            (new validation_service())->validate((int)$tenantid);
        }
    }
}
