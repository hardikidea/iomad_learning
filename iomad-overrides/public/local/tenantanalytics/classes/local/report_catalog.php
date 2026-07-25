<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantanalytics\local;

/**
 * Stable report catalogue and metric definitions.
 *
 * @package    local_tenantanalytics
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class report_catalog {
    /**
     * Return all maintained reports.
     *
     * @return array
     */
    public static function all(): array {
        return [
            'course_engagement' => get_string('reportcourseengagement', 'local_tenantanalytics'),
            'student_engagement' => get_string('reportstudentengagement', 'local_tenantanalytics'),
            'learner' => get_string('reportlearner', 'local_tenantanalytics'),
            'time_site' => get_string('reporttimesite', 'local_tenantanalytics'),
            'time_course' => get_string('reporttimecourse', 'local_tenantanalytics'),
            'time_activity' => get_string('reporttimeactivity', 'local_tenantanalytics'),
            'visits' => get_string('reportvisits', 'local_tenantanalytics'),
            'completion' => get_string('reportcompletion', 'local_tenantanalytics'),
            'license_usage' => get_string('reportlicenseusage', 'local_tenantanalytics'),
            'cohort_group' => get_string('reportcohortgroup', 'local_tenantanalytics'),
        ];
    }

    /**
     * Check a report key.
     *
     * @param string $reportkey Report key.
     * @return bool
     */
    public static function exists(string $reportkey): bool {
        return array_key_exists($reportkey, self::all());
    }

    /**
     * Return formats supported by Moodle core writers.
     *
     * @return array
     */
    public static function formats(): array {
        return [
            'csv' => 'CSV',
            'excel' => 'XLSX',
            'ods' => 'ODS',
            'pdf' => 'PDF',
        ];
    }
}
