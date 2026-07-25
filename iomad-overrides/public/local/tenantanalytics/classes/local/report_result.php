<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantanalytics\local;

/**
 * Immutable normalized report output.
 *
 * @package    local_tenantanalytics
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class report_result {
    /**
     * Create normalized report output.
     *
     * @param array $columns Column key to label map.
     * @param array $rows Normalized scalar rows.
     * @param array $metadata Metric and filter metadata.
     */
    public function __construct(
        private readonly array $columns,
        private readonly array $rows,
        private readonly array $metadata = [],
    ) {
    }

    /**
     * Return column map.
     *
     * @return array
     */
    public function get_columns(): array {
        return $this->columns;
    }

    /**
     * Return normalized rows.
     *
     * @return array
     */
    public function get_rows(): array {
        return $this->rows;
    }

    /**
     * Return report metadata.
     *
     * @return array
     */
    public function get_metadata(): array {
        return $this->metadata;
    }

    /**
     * Return a checksum without exposing row values in logs.
     *
     * @return string
     */
    public function get_checksum(): string {
        return hash('sha256', json_encode([
            'columns' => array_keys($this->columns),
            'rows' => $this->rows,
            'metadata' => $this->metadata,
        ], JSON_THROW_ON_ERROR));
    }
}
