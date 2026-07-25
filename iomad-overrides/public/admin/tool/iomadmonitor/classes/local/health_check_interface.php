<?php
// This file is part of Moodle - http://moodle.org/

namespace tool_iomadmonitor\local;

/**
 * Contract for bounded, read-only health checks.
 *
 * @package tool_iomadmonitor
 */
interface health_check_interface {
    /**
     * Run one check without exposing credentials or topology.
     *
     * @return array
     */
    public function check(): array;
}
