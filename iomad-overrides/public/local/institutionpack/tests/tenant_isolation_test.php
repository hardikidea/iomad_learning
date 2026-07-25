<?php
// This file is part of IOMAD - http://www.iomad.org/

namespace local_institutionpack;

use context_system;
use local_iomad\company;
use local_iomad\company_user;
use local_iomad\iomad;

defined('MOODLE_INTERNAL') || die();

final class tenant_isolation_test extends \advanced_testcase {
    public function test_company_capability_is_denied_across_company_scope(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        $companya = company::create_company((object) [
            'name' => 'Isolation Company A',
            'shortname' => 'isolation_a',
            'city' => 'London',
            'country' => 'GB',
        ]);
        $companyb = company::create_company((object) [
            'name' => 'Isolation Company B',
            'shortname' => 'isolation_b',
            'city' => 'London',
            'country' => 'GB',
        ]);
        $user = $this->getDataGenerator()->create_user();

        $this->assertTrue($companya->assign_user_to_company($user->id));
        $this->assertTrue($DB->record_exists('local_iomad_company_users', [
            'companyid' => $companya->id,
            'userid' => $user->id,
        ]));
        $this->assertFalse($DB->record_exists('local_iomad_company_users', [
            'companyid' => $companyb->id,
            'userid' => $user->id,
        ]));

        $systemcontext = context_system::instance();
        $roleid = create_role('Tenant capability test', 'tenantcapabilitytest', '');
        assign_capability('moodle/user:viewdetails', CAP_ALLOW, $roleid, $systemcontext);
        role_assign($roleid, $user->id, $systemcontext);

        $this->setUser($user);
        accesslib_clear_all_caches_for_unit_testing();

        $this->assertTrue(has_capability('moodle/user:viewdetails', $systemcontext));
        $this->assertTrue(iomad::has_capability(
            'moodle/user:viewdetails',
            $systemcontext,
            $companya->id
        ));
        $this->assertFalse(iomad::has_capability(
            'moodle/user:viewdetails',
            $systemcontext,
            $companyb->id
        ));
    }

    /**
     * Fresh and drifted native memberships retain their canonical manager type.
     *
     * @covers \local_institutionpack\company_membership_service
     */
    public function test_company_membership_reconciliation_preserves_manager_type(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        $company = company::create_company((object)[
            'name' => 'Membership Company',
            'shortname' => 'membership_company',
            'city' => 'Pune',
            'country' => 'IN',
        ]);
        $departmentid = (int)company::get_company_parentnode((int)$company->id)->id;
        $payload = $this->getDataGenerator()->create_user();
        $payload->companyid = (int)$company->id;
        $payload->departmentid = $departmentid;
        $payload->managertype = 1;
        $payload->educator = 0;
        $payload->sendnewpasswordemails = 0;
        $payload->preference_auth_forcepasswordchange = 0;
        $payload->use_email_as_username = 0;
        $userid = company_user::create($payload, (int)$company->id);

        $service = new company_membership_service();
        $membership = $service->reconcile($userid, (int)$company->id, $departmentid, 1, false);
        $this->assertSame(1, (int)$membership->managertype);

        company::upsert_company_user(
            $userid,
            (int)$company->id,
            $departmentid,
            0,
            false,
            true,
            true,
        );
        $membership = $service->reconcile($userid, (int)$company->id, $departmentid, 1, false);
        $this->assertSame(1, (int)$membership->managertype);
        $this->assertSame(1, $DB->count_records('local_iomad_company_users', [
            'userid' => $userid,
            'companyid' => $company->id,
        ]));
    }

    public function test_tenant_manager_is_idempotent(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        $input = [
            'name' => 'Managed College',
            'shortname' => 'managed_college',
            'city' => 'Pune',
            'country' => 'IN',
            'hostname' => 'college.example.test',
            'emaildomain' => 'example.test',
            'maxusers' => 250,
            'theme' => 'iomad_learning',
            'externalid' => 'SIS-MANAGED-001',
        ];
        $manager = new tenant_manager();

        $plan = $manager->plan($input);
        $this->assertTrue($plan['ok']);
        $this->assertSame('create', $plan['action']);

        $first = $manager->apply($input);
        $this->assertTrue($first['ok']);
        $this->assertSame('created', $first['action']);
        $this->assertTrue($DB->record_exists('local_iomad_companies', [
            'shortname' => 'managed_college',
            'maxusers' => 250,
        ]));

        $second = $manager->apply($input);
        $this->assertTrue($second['ok']);
        $this->assertSame('unchanged', $second['action']);
        $this->assertSame(1, $DB->count_records('local_iomad_companies', [
            'shortname' => 'managed_college',
        ]));
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function test_license_manager_uses_immutable_reference(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        $company = company::create_company((object)[
            'name' => 'Licensed Company',
            'shortname' => 'licensed_company',
            'city' => 'Pune',
            'country' => 'IN',
            'theme' => 'iomad_learning',
        ]);
        $course = $this->getDataGenerator()->create_course([
            'shortname' => 'LICENSED-COURSE',
            'idnumber' => 'LICENSED-COURSE-001',
        ]);
        $this->assertTrue($company->add_course($course, 0, false, true));

        $input = [
            'company' => 'licensed_company',
            'courseidnumber' => 'LICENSED-COURSE-001',
            'allocation' => 50,
            'reference' => 'ORDER-998231',
            'startdate' => '2026-07-24',
            'expirydate' => '2027-07-24',
            'validlength' => 365,
            'type' => 0,
        ];
        $manager = new license_manager();
        $first = $manager->apply($input);

        $this->assertTrue($first['ok']);
        $this->assertSame('allocated', $first['action']);
        $this->assertSame(1, $DB->count_records('local_iomad_company_licenses', [
            'companyid' => $company->id,
            'reference' => 'ORDER-998231',
        ]));

        $second = $manager->apply($input);
        $this->assertTrue($second['ok']);
        $this->assertSame('unchanged', $second['action']);
        $this->assertSame(1, $DB->count_records('local_iomad_company_licenses', [
            'companyid' => $company->id,
            'reference' => 'ORDER-998231',
        ]));
    }

    public function test_auditor_detects_cross_company_enrolment(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $companya = company::create_company((object)[
            'name' => 'Audit Company A',
            'shortname' => 'audit_a',
            'city' => 'London',
            'country' => 'GB',
            'theme' => 'iomad_learning',
        ]);
        $companyb = company::create_company((object)[
            'name' => 'Audit Company B',
            'shortname' => 'audit_b',
            'city' => 'London',
            'country' => 'GB',
            'theme' => 'iomad_learning',
        ]);
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $this->assertTrue($companya->assign_user_to_company($user->id));
        $this->assertTrue($companyb->add_course($course));

        $manual = enrol_get_plugin('manual');
        $instances = enrol_get_instances($course->id, true);
        $manualinstance = null;
        foreach ($instances as $instance) {
            if ($instance->enrol === 'manual') {
                $manualinstance = $instance;
                break;
            }
        }
        $this->assertNotNull($manualinstance);
        $manual->enrol_user($manualinstance, $user->id);

        $report = (new tenant_auditor())->run(10, false);
        $this->assertFalse($report['ok']);
        $this->assertGreaterThanOrEqual(
            1,
            $report['checks']['course_enrolment_scope']['anomalies']
        );
        $this->assertNotEmpty(
            $report['checks']['course_enrolment_scope']['references']
        );
    }

    public function test_block_manager_is_idempotent(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        $input = [
            'blockname' => 'dash',
            'page' => 'site-index',
            'region' => 'content',
            'weight' => -10,
        ];
        $manager = new block_manager();
        $first = $manager->apply($input);
        $this->assertTrue($first['ok']);

        $second = $manager->apply($input);
        $this->assertTrue($second['ok']);
        $this->assertSame('unchanged', $second['action']);
        $this->assertSame(1, $DB->count_records('block_instances', [
            'blockname' => 'dash',
            'parentcontextid' => context_system::instance()->id,
            'pagetypepattern' => 'site-index',
            'defaultregion' => 'content',
        ]));
    }
}
