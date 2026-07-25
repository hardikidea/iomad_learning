<?php
// This file is part of Moodle - http://moodle.org/

namespace local_rapidgrader;

use local_iomad\company;
use local_rapidgrader\local\course_scope;
use local_rapidgrader\local\gradebook_service;

/**
 * Tenant-filtered gradebook read and update tests.
 *
 * @package    local_rapidgrader
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_rapidgrader\local\gradebook_service
 */
final class gradebook_service_test extends \advanced_testcase {
    /**
     * Learners and writes remain inside the selected company.
     */
    public function test_manual_grade_update_rejects_cross_company_learner(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        [$service, $item, $usera, $userb] = $this->fixture();

        $learners = array_column($service->learners(), null, 'id');
        $this->assertArrayHasKey($usera->id, $learners);
        $this->assertArrayNotHasKey($userb->id, $learners);

        $changed = $service->update([
            $item->id => [
                $usera->id => '87.5',
                $userb->id => '91',
            ],
        ], get_admin()->id);

        $this->assertSame(1, $changed);
        $this->assertSame(87.5, $service->grade($item, $usera->id));
        $this->assertNull($service->grade($item, $userb->id));
        $this->assertSame([
            'notgraded' => 0,
            'below50' => 0,
            'from50' => 0,
            'from65' => 0,
            'from80' => 1,
        ], $service->distribution([$item], [$usera]));
    }

    /**
     * Out-of-range and oversized updates are rejected.
     */
    public function test_update_validation_rejects_unsafe_batches(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        [$service, $item, $usera] = $this->fixture();

        try {
            $service->update([$item->id => [$usera->id => '101']], get_admin()->id);
            $this->fail('An out-of-range grade was accepted.');
        } catch (\invalid_parameter_exception $exception) {
            $this->assertStringContainsString('outside the item range', $exception->getMessage());
        }

        $this->expectException(\invalid_parameter_exception::class);
        $service->update([
            $item->id => array_fill(1, gradebook_service::MAX_UPDATE_CELLS + 1, '10'),
        ], get_admin()->id);
    }

    /**
     * Quiz mode uses the core report and tenant participant counts.
     */
    public function test_quiz_summary_uses_core_quiz_report(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        [$service, , , , $course] = $this->fixture();
        $this->getDataGenerator()->create_module('quiz', [
            'course' => $course->id,
            'name' => 'Tenant quiz',
        ]);

        $quizzes = $service->quizzes();

        $this->assertCount(1, $quizzes);
        $this->assertSame('Tenant quiz', $quizzes[0]['name']);
        $this->assertSame(0, $quizzes[0]['attempts']);
        $this->assertSame(0, $quizzes[0]['participants']);
        $this->assertStringContainsString('mode=overview', $quizzes[0]['url']->out(false));
    }

    /**
     * Create two-company course and grade data.
     *
     * @return array
     */
    private function fixture(): array {
        require_once(__DIR__ . '/../../../lib/gradelib.php');

        $companya = $this->company('Grade Company A', 'grade_a');
        $companyb = $this->company('Grade Company B', 'grade_b');
        $course = $this->getDataGenerator()->create_course();
        $usera = $this->getDataGenerator()->create_user([
            'firstname' => 'Allowed',
            'lastname' => 'Learner',
        ]);
        $userb = $this->getDataGenerator()->create_user([
            'firstname' => 'Other',
            'lastname' => 'Tenant',
        ]);
        $this->assertTrue($companya->add_course($course));
        $this->assertTrue($companya->assign_user_to_company($usera->id));
        $this->assertTrue($companyb->assign_user_to_company($userb->id));
        $this->getDataGenerator()->enrol_user($usera->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($userb->id, $course->id, 'student');

        $category = \grade_category::fetch_course_category($course->id);
        $item = new \grade_item();
        $item->courseid = $course->id;
        $item->categoryid = $category->id;
        $item->itemtype = 'manual';
        $item->itemname = 'Practical assessment';
        $item->idnumber = 'rapid-practical';
        $item->gradetype = GRADE_TYPE_VALUE;
        $item->grademin = 0;
        $item->grademax = 100;
        $item->insert();

        return [
            new gradebook_service(new course_scope($companya->id), $course),
            $item,
            $usera,
            $userb,
            $course,
        ];
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
