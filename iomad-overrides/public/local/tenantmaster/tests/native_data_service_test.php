<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster;

use local_iomad\company;
use local_tenantmaster\local\native_data_service;
use local_tenantmaster\local\organisation_service;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/tenantmaster_testcase.php');

/**
 * Native read-model tenant isolation tests.
 *
 * @package    local_tenantmaster
 * @covers     \local_tenantmaster\local\native_data_service
 */
final class native_data_service_test extends tenantmaster_testcase {
    /**
     * Department, user and course views never cross the selected company.
     */
    public function test_native_views_are_company_scoped(): void {
        global $DB;

        $this->resetAfterTest(true);
        $tenanta = $this->create_tenant();
        $tenantb = $this->create_tenant('university');
        $departments = new organisation_service();
        $departmenta = $departments->save($tenanta, (object)[
            'id' => 0,
            'name' => 'School science',
            'shortname' => 'SCHOOL_SCIENCE',
            'parentid' => company::get_company_parentnode((int)$tenanta->companyid)->id,
        ]);
        $departments->save($tenantb, (object)[
            'id' => 0,
            'name' => 'University science',
            'shortname' => 'UNIVERSITY_SCIENCE',
            'parentid' => company::get_company_parentnode((int)$tenantb->companyid)->id,
        ]);

        $usera = $this->getDataGenerator()->create_user(['idnumber' => 'SCHOOL_USER']);
        $userb = $this->getDataGenerator()->create_user(['idnumber' => 'UNIVERSITY_USER']);
        company::upsert_company_user(
            (int)$usera->id,
            (int)$tenanta->companyid,
            (int)$departmenta->id,
            0,
            false,
        );
        company::upsert_company_user(
            (int)$userb->id,
            (int)$tenantb->companyid,
            (int)company::get_company_parentnode((int)$tenantb->companyid)->id,
            0,
            false,
        );

        $coursea = $this->getDataGenerator()->create_course(['idnumber' => 'SCHOOL_COURSE']);
        $courseb = $this->getDataGenerator()->create_course(['idnumber' => 'UNIVERSITY_COURSE']);
        (new company((int)$tenanta->companyid))->add_course($coursea, (int)$departmenta->id);
        (new company((int)$tenantb->companyid))->add_course(
            $courseb,
            (int)company::get_company_parentnode((int)$tenantb->companyid)->id,
        );

        $service = new native_data_service();
        $this->assertContains('School science', array_column($service->departments($tenanta), 'name'));
        $this->assertNotContains('University science', array_column($service->departments($tenanta), 'name'));
        $this->assertSame([$usera->id], array_column($service->users($tenanta, 'SCHOOL_USER'), 'id'));
        $this->assertSame([], $service->users($tenanta, 'UNIVERSITY_USER'));
        $this->assertSame([$coursea->id], array_column($service->courses($tenanta, 'SCHOOL_COURSE'), 'id'));
        $this->assertSame([], $service->courses($tenanta, 'UNIVERSITY_COURSE'));
        $this->assertTrue($DB->record_exists('local_iomad_company_courses', [
            'companyid' => $tenanta->companyid,
            'courseid' => $coursea->id,
        ]));
    }
}
