<?php
// This file is part of Moodle - http://moodle.org/

namespace tool_iomadmonitor\local;

/**
 * Bounded telemetry exporter contract.
 *
 * @package tool_iomadmonitor
 */
interface telemetry_exporter_interface {
    /**
     * Export one sanitized event.
     *
     * @param string $event Event.
     * @param string $severity Severity.
     * @param array $attributes Allowlisted attributes.
     * @return bool
     */
    public function log(string $event, string $severity, array $attributes = []): bool;

    /**
     * Export one completed span.
     *
     * @param string $operation Operation.
     * @param int $startnano Start time.
     * @param int $endnano End time.
     * @param string $status Status.
     * @param array $attributes Allowlisted attributes.
     * @return bool
     */
    public function span(
        string $operation,
        int $startnano,
        int $endnano,
        string $status,
        array $attributes = [],
    ): bool;
}
