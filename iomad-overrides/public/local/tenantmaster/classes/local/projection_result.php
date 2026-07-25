<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\local;

/**
 * Verified native projection result.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class projection_result {
    /**
     * Constructor.
     *
     * @param string $component Native component.
     * @param string $externalkey Stable external key.
     * @param int $targetid Native target ID.
     * @param string[] $managed Managed field names.
     * @param array<string, mixed> $desired Desired managed values.
     * @param array<string, mixed> $native Read-back managed values.
     */
    public function __construct(
        public readonly string $component,
        public readonly string $externalkey,
        public readonly int $targetid,
        public readonly array $managed,
        public readonly array $desired,
        public readonly array $native,
    ) {
    }
}
