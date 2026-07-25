<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantanalytics;

use context_course;
use local_iomad\company;
use local_tenantanalytics\local\report_catalog;
use local_tenantanalytics\local\report_engine;
use local_tenantanalytics\local\tenant_scope;

/**
 * End-to-end tenant report query tests.
 *
 * @package    local_tenantanalytics
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_tenantanalytics\local\report_engine
 */
final class report_engine_test extends \advanced_testcase {
    /**
     * Every report executes while excluding another company's users and courses.
     */
    public function test_reports_enforce_company_user_and_course_boundaries(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $companya = $this->company('Report Company A', 'report_a');
        $companyb = $this->company('Report Company B', 'report_b');
        $usera = $this->getDataGenerator()->create_user([
            'firstname' => 'Scoped',
            'lastname' => 'Learner',
            'email' => 'scoped@example.test',
        ]);
        $userb = $this->getDataGenerator()->create_user([
            'firstname' => 'Excluded',
            'lastname' => 'Learner',
            'email' => 'excluded@example.test',
        ]);
        $coursea = $this->getDataGenerator()->create_course(['fullname' => 'Scoped Course']);
        $courseb = $this->getDataGenerator()->create_course(['fullname' => 'Excluded Course']);
        $companya->assign_user_to_company($usera->id);
        $companyb->assign_user_to_company($userb->id);
        $companya->add_course($coursea);
        $companyb->add_course($courseb);
        $this->enrol($usera->id, $coursea->id);
        $this->enrol($userb->id, $courseb->id);
        $DB->insert_record('local_iomad_tracks', (object)[
            'courseid' => $coursea->id,
            'coursename' => $coursea->fullname,
            'userid' => $usera->id,
            'timeenrolled' => time(),
            'finalscore' => 0,
            'companyid' => $companya->id,
            'modifiedtime' => time(),
        ]);
        $DB->insert_record('local_iomad_tracks', (object)[
            'courseid' => $courseb->id,
            'coursename' => $courseb->fullname,
            'userid' => $userb->id,
            'timeenrolled' => time(),
            'finalscore' => 0,
            'companyid' => $companyb->id,
            'modifiedtime' => time(),
        ]);
        $this->enable_standard_log();
        $this->setUser($usera);
        \core\event\course_viewed::create(['context' => context_course::instance($coursea->id)])->trigger();
        $this->setUser($userb);
        \core\event\course_viewed::create(['context' => context_course::instance($courseb->id)])->trigger();
        $this->setAdminUser();

        $engine = new report_engine();
        $scope = new tenant_scope($companya->id, get_admin()->id, false);
        $filters = ['since' => time() - MINSECS, 'until' => time()];
        foreach (array_keys(report_catalog::all()) as $reportkey) {
            $result = $engine->generate($reportkey, $scope, $filters);
            $serialized = json_encode($result->get_rows(), JSON_THROW_ON_ERROR);
            $this->assertStringNotContainsString('Excluded Learner', $serialized, $reportkey);
            $this->assertStringNotContainsString('Excluded Course', $serialized, $reportkey);
            $this->assertStringNotContainsString('excluded@example.test', $serialized, $reportkey);
        }

        $student = $engine->generate('student_engagement', $scope, $filters);
        $this->assertCount(1, $student->get_rows());
        $this->assertSame('Scoped Learner', $student->get_rows()[0]['learner']);
        $course = $engine->generate('course_engagement', $scope, $filters);
        $this->assertCount(1, $course->get_rows());
        $this->assertSame('Scoped Course', $course->get_rows()[0]['course']);
    }

    /**
     * Enable immediate event logging for the test.
     */
    private function enable_standard_log(): void {
        set_config('enabled_stores', 'logstore_standard', 'tool_log');
        set_config('buffersize', 0, 'logstore_standard');
        set_config('logguests', 1, 'logstore_standard');
        get_log_manager(true);
    }

    /**
     * Enrol a user through Moodle's manual enrolment API.
     *
     * @param int $userid User.
     * @param int $courseid Course.
     */
    private function enrol(int $userid, int $courseid): void {
        $plugin = enrol_get_plugin('manual');
        foreach (enrol_get_instances($courseid, true) as $instance) {
            if ($instance->enrol === 'manual') {
                $plugin->enrol_user($instance, $userid);
                return;
            }
        }
        $this->fail('Manual enrolment instance was not created.');
    }

    /**
     * Create a company through the supported IOMAD API.
     *
     * @param string $name Name.
     * @param string $shortname Shortname.
     * @return company
     */
    private function company(string $name, string $shortname): company {
        return company::create_company((object)[
            'name' => $name,
            'shortname' => $shortname,
            'city' => 'Pune',
            'country' => 'IN',
        ]);
    }
}
