<?php
// This file is part of Moodle - http://moodle.org/

namespace block_gamification_telemetry\privacy;

/**
 * The block stores no personal data.
 *
 * @package block_gamification_telemetry
 */
final class provider implements \core_privacy\local\metadata\null_provider {
    /**
     * Privacy reason.
     *
     * @return string
     */
    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
