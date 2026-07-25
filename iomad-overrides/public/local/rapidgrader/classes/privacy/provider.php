<?php
// This file is part of Moodle - http://moodle.org/

namespace local_rapidgrader\privacy;

/**
 * RapidGrader stores no personal data of its own.
 *
 * @package    local_rapidgrader
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider implements \core_privacy\local\metadata\null_provider {
    /**
     * Explain the storage model.
     *
     * @return string
     */
    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
