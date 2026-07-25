<?php
// This file is part of Moodle - http://moodle.org/

namespace tool_iomadmonitor\task;

use tool_iomadmonitor\local\alert_service;
use tool_iomadmonitor\local\health_service;

/**
 * Run frequent platform checks.
 *
 * @package    tool_iomadmonitor
 */
final class run_checks extends \core\task\scheduled_task {
    /**
     * Name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('taskchecks', 'tool_iomadmonitor');
    }

    /**
     * Execute.
     */
    public function execute(): void {
        $report = (new health_service())->run(false);
        set_config('lastreport', json_encode($report, JSON_THROW_ON_ERROR), 'tool_iomadmonitor');
        (new alert_service())->deliver($report);
    }
}
