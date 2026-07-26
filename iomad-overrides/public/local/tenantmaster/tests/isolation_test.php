<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster;

use local_tenantmaster\local\ecosystem_verifier;
use local_tenantmaster\local\learning_access_service;
use local_tenantmaster\local\organisation_service;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/tenantmaster_testcase.php');

/**
 * Cross-company isolation tests.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class isolation_test extends tenantmaster_testcase {
    /**
     * A clean default installation is a valid ecosystem state.
     *
     * @covers \local_tenantmaster\local\ecosystem_verifier
     */
    public function test_ecosystem_verifier_accepts_clean_default_installation(): void {
        $this->resetAfterTest();

        $report = (new ecosystem_verifier())->run();
        $selection = array_values(array_filter(
            $report['results'],
            static fn(array $result): bool => $result['check'] === 'active_tenant_selection',
        ));

        $this->assertCount(1, $selection);
        $this->assertSame('pass', $selection[0]['status']);
        $this->assertSame('selected=0;state=clean-default', $selection[0]['metric']);
    }

    /**
     * A department cannot use a parent from another company.
     *
     * @covers \local_tenantmaster\local\organisation_service
     */
    public function test_department_parent_cannot_cross_tenant(): void {
        $this->resetAfterTest();
        $tenantone = $this->create_tenant();
        $tenanttwo = $this->create_tenant('university');
        $foreignparent = \local_iomad\company::get_company_parentnode((int)$tenanttwo->companyid);

        $this->expectException(\invalid_parameter_exception::class);
        (new organisation_service())->save($tenantone, (object)[
            'id' => 0,
            'name' => 'Invalid faculty',
            'shortname' => 'INVALID_FACULTY',
            'parentid' => $foreignparent->id,
        ]);
    }

    /**
     * A user from another tenant cannot enter a managed cohort.
     *
     * @covers \local_tenantmaster\local\learning_access_service
     */
    public function test_cohort_member_cannot_cross_tenant(): void {
        global $DB;

        $this->resetAfterTest();
        $tenantone = $this->create_tenant();
        $tenanttwo = $this->create_tenant('university');
        $user = $this->getDataGenerator()->create_user();
        $department = \local_iomad\company::get_company_parentnode((int)$tenanttwo->companyid);
        \local_iomad\company::upsert_company_user(
            (int)$user->id,
            (int)$tenanttwo->companyid,
            (int)$department->id,
            0,
        );
        $service = new learning_access_service();
        $cohortid = $service->ensure_cohort($tenantone, 'CLASS_A', 'Class A');

        $this->expectException(\invalid_parameter_exception::class);
        $service->add_cohort_member($tenantone, $cohortid, (int)$user->id);
    }

    /**
     * A group from another course cannot be used during native enrolment.
     *
     * @covers \local_tenantmaster\local\learning_access_service
     */
    public function test_enrolment_group_must_match_course(): void {
        global $DB;

        $this->resetAfterTest();
        $tenant = $this->create_tenant();
        $generator = $this->getDataGenerator();
        $courseone = $generator->create_course();
        $coursetwo = $generator->create_course();
        $company = new \local_iomad\company((int)$tenant->companyid);
        $department = \local_iomad\company::get_company_parentnode((int)$tenant->companyid);
        $company->add_course($courseone, (int)$department->id);
        $company->add_course($coursetwo, (int)$department->id);
        $user = $generator->create_user();
        \local_iomad\company::upsert_company_user(
            (int)$user->id,
            (int)$tenant->companyid,
            (int)$department->id,
            0,
        );
        $service = new learning_access_service();
        $groupid = $service->ensure_group($tenant, (int)$coursetwo->id, 'GROUP_B', 'Group B');
        $studentroleid = (int)$DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);

        $this->expectException(\invalid_parameter_exception::class);
        $service->enrol_user($tenant, (int)$courseone->id, (int)$user->id, $studentroleid, $groupid);
    }
}
