<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_tenantform;

use local_iomad\company;
use mod_tenantform\local\tenant_access;

/**
 * Company membership and course-boundary tests.
 *
 * @package    mod_tenantform
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_tenantform\local\tenant_access
 */
final class tenant_access_test extends \advanced_testcase {
    /**
     * Users and courses do not leak across companies.
     */
    public function test_user_and_course_checks_reject_other_company(): void {
        global $SESSION;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $companya = company::create_company((object)[
            'name' => 'Forms Company A',
            'shortname' => 'forms_a',
            'city' => 'Pune',
            'country' => 'IN',
        ]);
        $companyb = company::create_company((object)[
            'name' => 'Forms Company B',
            'shortname' => 'forms_b',
            'city' => 'Pune',
            'country' => 'IN',
        ]);
        $user = $this->getDataGenerator()->create_user();
        $coursea = $this->getDataGenerator()->create_course();
        $courseb = $this->getDataGenerator()->create_course();
        $this->assertTrue($companya->assign_user_to_company($user->id));
        $this->assertTrue($companya->add_course($coursea));
        $this->assertTrue($companyb->add_course($courseb));

        $this->assertTrue(tenant_access::user_in_company($user->id, $companya->id));
        $this->assertFalse(tenant_access::user_in_company($user->id, $companyb->id));
        $this->assertTrue(tenant_access::course_in_company($coursea->id, $companya->id));
        $this->assertFalse(tenant_access::course_in_company($courseb->id, $companya->id));
        unset($SESSION->currenteditingcompany);
        $this->assertSame(
            $companya->id,
            tenant_access::resolve_company_for_course(
                $coursea->id,
                \context_course::instance($coursea->id),
            ),
        );
    }
}
