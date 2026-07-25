<?php
// This file is part of Moodle - http://moodle.org/

namespace format_iomadvideo\privacy;

use core_privacy\local\metadata\null_provider;

/**
 * Privacy declaration for a format that stores no personal data.
 *
 * @package    format_iomadvideo
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements null_provider {
    /**
     * Return the no-personal-data explanation string.
     *
     * @return string
     */
    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
