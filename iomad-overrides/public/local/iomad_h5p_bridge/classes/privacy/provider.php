<?php
// This file is part of Moodle - http://moodle.org/

namespace local_iomad_h5p_bridge\privacy;

/**
 * The bridge persists no personal data.
 *
 * @package local_iomad_h5p_bridge
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
