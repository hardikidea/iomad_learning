<?php
// This file is part of Moodle - http://moodle.org/

namespace tool_iomadmonitor\local;

/**
 * Explicit span for bounded project-owned operations.
 *
 * @package tool_iomadmonitor
 */
final class operation_span {
    /** @var int Start time in Unix nanoseconds. */
    private int $started;

    /**
     * Constructor.
     *
     * @param string $operation Operation.
     * @param array $attributes Attributes.
     * @param telemetry_exporter_interface $exporter Exporter.
     */
    public function __construct(
        private readonly string $operation,
        private readonly array $attributes = [],
        private readonly telemetry_exporter_interface $exporter = new otlp_exporter(),
    ) {
        $this->started = (int)(microtime(true) * 1000000000);
    }

    /**
     * Finish.
     *
     * @param string $status ok or error.
     */
    public function finish(string $status): void {
        $this->exporter->span(
            $this->operation,
            $this->started,
            (int)(microtime(true) * 1000000000),
            $status,
            $this->attributes,
        );
    }
}
