<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\task;

use local_tenantmaster\local\catalogue_service;

/**
 * Propagate one global catalogue item without overwriting tenant customisation.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class propagate_catalogue_item extends \core\task\adhoc_task {
    /**
     * Execute propagation.
     */
    public function execute(): void {
        $data = $this->get_custom_data();
        (new catalogue_service())->propagate((int)$data->catalogitemid);
    }
}
