<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster;

use local_iomad\company;
use local_tenantmaster\local\default_service;
use local_tenantmaster\local\onboarding_service;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/tenantmaster_testcase.php');

/**
 * Default adoption tests.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class default_service_test extends tenantmaster_testcase {
    /**
     * School defaults are type-specific and idempotent.
     *
     * @covers \local_tenantmaster\local\default_service
     */
    public function test_school_defaults_are_idempotent(): void {
        global $DB;

        $this->resetAfterTest();
        $tenant = $this->create_tenant('school');
        $service = new default_service();
        $first = $service->adopt($tenant);
        $second = $service->adopt($tenant);

        $this->assertGreaterThan(40, $first['created']);
        $this->assertSame(0, $second['created']);
        $this->assertTrue($DB->record_exists('local_tenantmaster_master', [
            'tenantid' => $tenant->id,
            'mastertype' => 'grade',
            'code' => 'STD_12',
        ]));
        $this->assertTrue($DB->record_exists('local_tenantmaster_master', [
            'tenantid' => $tenant->id,
            'mastertype' => 'subject',
            'code' => 'MATHEMATICS',
        ]));
        $this->assertSame(1, $DB->count_records('local_tenantmaster_acadyear', [
            'tenantid' => $tenant->id,
            'iscurrent' => 1,
        ]));
        $currentyearid = (int)$DB->get_field('local_tenantmaster_acadyear', 'id', [
            'tenantid' => $tenant->id,
            'iscurrent' => 1,
        ], MUST_EXIST);
        $this->assertSame(
            $currentyearid,
            (int)$DB->get_field('local_tenantmaster_tenant', 'activeyearid', ['id' => $tenant->id], MUST_EXIST),
        );
    }

    /**
     * University defaults include semesters, programmes and credit definitions.
     *
     * @covers \local_tenantmaster\local\default_service
     */
    public function test_university_defaults_have_academic_depth(): void {
        global $DB;

        $this->resetAfterTest();
        $tenant = $this->create_tenant('university');
        (new default_service())->adopt($tenant);

        $this->assertSame(8, $DB->count_records('local_tenantmaster_master', [
            'tenantid' => $tenant->id,
            'mastertype' => 'semester',
        ]));
        $this->assertGreaterThanOrEqual(10, $DB->count_records('local_tenantmaster_master', [
            'tenantid' => $tenant->id,
            'mastertype' => 'programme',
        ]));
        $this->assertGreaterThanOrEqual(5, $DB->count_records('local_tenantmaster_master', [
            'tenantid' => $tenant->id,
            'mastertype' => 'credit',
        ]));
        $programme = $DB->get_record('local_tenantmaster_master', [
            'tenantid' => $tenant->id,
            'mastertype' => 'programme',
            'externalid' => 'PROGRAMME_BTECH_CSE',
        ], '*', MUST_EXIST);
        $faculty = $DB->get_record('local_tenantmaster_master', [
            'tenantid' => $tenant->id,
            'externalid' => 'FACULTY_ENGINEERING',
        ], '*', MUST_EXIST);
        $this->assertSame((int)$faculty->id, (int)$programme->parentid);
    }

    /**
     * Existing IOMAD companies are adopted without duplication or type drift.
     *
     * @covers \local_tenantmaster\local\onboarding_service::adopt_existing
     */
    public function test_existing_company_adoption_is_complete_and_idempotent(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $company = company::create_company((object)[
            'name' => 'Imported school',
            'shortname' => 'IMPORTED_SCHOOL',
            'code' => 'SCHOOL_IMPORT',
            'address' => 'Campus road',
            'city' => 'Ahmedabad',
            'region' => 'Gujarat',
            'postcode' => '380001',
            'country' => 'IN',
            'theme' => '',
            'parentid' => 0,
            'hostname' => 'imported-school.example.test',
            'custom1' => '',
            'custom2' => '',
            'custom3' => '',
            'templates' => [],
        ]);

        $service = new onboarding_service();
        $first = $service->adopt_existing((int)$company->id, 'school');
        $second = $service->adopt_existing((int)$company->id, 'university');
        $this->assertSame((int)$first->id, (int)$second->id);
        $this->assertSame('school', $second->tenanttype);
        $this->assertSame('SCHOOL_IMPORT', $first->trustcode);
        $this->assertSame('{}', $first->profilejson);
        $this->assertSame(1, $DB->count_records('local_tenantmaster_tenant', [
            'companyid' => $company->id,
        ]));
        $this->assertSame(7, $DB->count_records('local_tenantmaster_rolemap', [
            'tenantid' => $first->id,
        ]));
        $this->assertGreaterThan(40, $DB->count_records('local_tenantmaster_master', [
            'tenantid' => $first->id,
        ]));
        $this->assertSame(1, $DB->count_records('local_tenantmaster_acadyear', [
            'tenantid' => $first->id,
            'iscurrent' => 1,
        ]));
        $this->assertGreaterThan(0, $DB->count_records('local_tenantmaster_dirty', [
            'tenantid' => $first->id,
        ]));
        $this->assertFalse($DB->record_exists('local_tenantmaster_dirty', [
            'tenantid' => $first->id,
            'module' => 'tenant',
        ]));
    }
}
