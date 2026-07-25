<?php
// This file is part of Moodle - http://moodle.org/

namespace block_tenantform\privacy;

/**
 * The block stores configuration only.
 *
 * @package    block_tenantform
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider implements \core_privacy\local\metadata\null_provider {
    /**
     * Explain that no personal data is stored.
     *
     * @return string
     */
    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
