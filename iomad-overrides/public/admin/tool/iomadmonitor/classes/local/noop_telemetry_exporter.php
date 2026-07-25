<?php
// This file is part of Moodle - http://moodle.org/

namespace tool_iomadmonitor\local;

/**
 * Explicit no-op exporter for disabled or test environments.
 *
 * @package tool_iomadmonitor
 */
final class noop_telemetry_exporter implements telemetry_exporter_interface {
    /**
     * {@inheritDoc}
     */
    public function log(string $event, string $severity, array $attributes = []): bool {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function span(
        string $operation,
        int $startnano,
        int $endnano,
        string $status,
        array $attributes = [],
    ): bool {
        return false;
    }
}
