<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_tenantform\local;

/**
 * Result of an idempotent submission operation.
 *
 * @package    mod_tenantform
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class submission_result {
    /**
     * Constructor.
     *
     * @param object $entry Stored entry.
     * @param bool $created Whether this request created it.
     */
    public function __construct(
        public readonly object $entry,
        public readonly bool $created,
    ) {
    }
}
