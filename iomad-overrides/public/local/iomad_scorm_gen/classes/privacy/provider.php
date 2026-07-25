<?php
// This file is part of Moodle - http://moodle.org/

namespace local_iomad_scorm_gen\privacy;

/**
 * The generator and bridge persist no personal data.
 *
 * @package local_iomad_scorm_gen
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
