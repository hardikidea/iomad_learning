<?php
// This file is part of Moodle - http://moodle.org/

namespace tool_iomadmonitor\privacy;

/**
 * Site monitor stores only aggregate status in plugin configuration.
 *
 * @package    tool_iomadmonitor
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider implements \core_privacy\local\metadata\null_provider {
    /**
     * Reason.
     *
     * @return string
     */
    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
