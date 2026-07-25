<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\task;

use local_tenantmaster\local\drift_service;

/**
 * Scheduled native drift detection.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class detect_drift extends \core\task\scheduled_task {
    /**
     * Task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_detectdrift', 'local_tenantmaster');
    }

    /**
     * Detect field-level drift.
     */
    public function execute(): void {
        (new drift_service())->detect_all();
    }
}
