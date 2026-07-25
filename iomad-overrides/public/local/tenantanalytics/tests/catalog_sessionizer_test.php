<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantanalytics;

use local_tenantanalytics\local\report_catalog;
use local_tenantanalytics\local\sessionizer;

/**
 * Report catalogue and active-time estimator tests.
 *
 * @package    local_tenantanalytics
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_tenantanalytics\local\report_catalog
 * @covers     \local_tenantanalytics\local\sessionizer
 */
final class catalog_sessionizer_test extends \advanced_testcase {
    /**
     * The public report contract remains complete and stable.
     */
    public function test_catalog_contains_ten_reports_and_four_formats(): void {
        $this->assertSame([
            'course_engagement',
            'student_engagement',
            'learner',
            'time_site',
            'time_course',
            'time_activity',
            'visits',
            'completion',
            'license_usage',
            'cohort_group',
        ], array_keys(report_catalog::all()));
        $this->assertSame(['csv', 'excel', 'ods', 'pdf'], array_keys(report_catalog::formats()));
    }

    /**
     * Time aggregation caps inactive gaps and does not credit the last event.
     */
    public function test_sessionizer_applies_gap_cap_per_dimension(): void {
        $events = [
            ['userid' => 7, 'courseid' => 3, 'timecreated' => 100],
            ['userid' => 7, 'courseid' => 3, 'timecreated' => 160],
            ['userid' => 7, 'courseid' => 3, 'timecreated' => 5000],
            ['userid' => 8, 'courseid' => 3, 'timecreated' => 200],
        ];
        $result = (new sessionizer(1800))->aggregate($events, ['userid', 'courseid']);

        $this->assertSame(1860, $result['7:3']['seconds']);
        $this->assertSame(3, $result['7:3']['events']);
        $this->assertSame(0, $result['8:3']['seconds']);
        $this->assertSame(1, $result['8:3']['events']);
    }
}
