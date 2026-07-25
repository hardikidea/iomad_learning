<?php
// This file is part of Moodle - http://moodle.org/

namespace local_rapidgrader;

use local_iomad\company;
use local_rapidgrader\local\course_scope;

/**
 * Company course and learner boundary tests.
 *
 * @package    local_rapidgrader
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_rapidgrader\local\course_scope
 */
final class course_scope_test extends \advanced_testcase {
    /**
     * Company scope exposes only its courses and members.
     */
    public function test_scope_rejects_other_company_courses_and_users(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $companya = $this->company('Rapid Company A', 'rapid_a');
        $companyb = $this->company('Rapid Company B', 'rapid_b');
        $coursea = $this->getDataGenerator()->create_course();
        $courseb = $this->getDataGenerator()->create_course();
        $usera = $this->getDataGenerator()->create_user();
        $userb = $this->getDataGenerator()->create_user();
        $this->assertTrue($companya->add_course($coursea));
        $this->assertTrue($companyb->add_course($courseb));
        $this->assertTrue($companya->assign_user_to_company($usera->id));
        $this->assertTrue($companyb->assign_user_to_company($userb->id));

        $scope = new course_scope($companya->id);

        $this->assertArrayHasKey($coursea->id, $scope->courses());
        $this->assertArrayNotHasKey($courseb->id, $scope->courses());
        $this->assertTrue($scope->contains_user($usera->id));
        $this->assertFalse($scope->contains_user($userb->id));
        $this->assertSame($coursea->id, $scope->require_course($coursea->id)->id);
    }

    /**
     * A site administrator cannot select a non-existent company.
     */
    public function test_resolve_rejects_unknown_company(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $this->expectException(\invalid_parameter_exception::class);
        course_scope::resolve(999999);
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
