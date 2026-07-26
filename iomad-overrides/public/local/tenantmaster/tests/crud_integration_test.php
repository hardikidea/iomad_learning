<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster;

use local_iomad\company;
use local_tenantmaster\local\academic_year_service;
use local_tenantmaster\local\drift_service;
use local_tenantmaster\local\json;
use local_tenantmaster\local\learning_access_service;
use local_tenantmaster\local\master_service;
use local_tenantmaster\local\organisation_service;
use local_tenantmaster\local\people_service;
use local_tenantmaster\local\projection_service;
use local_tenantmaster\local\queue_service;
use local_tenantmaster\local\role_service;
use local_tenantmaster\local\tenant_service;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/tenantmaster_testcase.php');

/**
 * End-to-end CRUD and native IOMAD integration tests.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class crud_integration_test extends tenantmaster_testcase {
    /**
     * Tenant Master metadata never overwrites authoritative native company fields.
     *
     * @covers \local_tenantmaster\local\tenant_service
     */
    public function test_tenant_profile_update_preserves_native_company(): void {
        global $DB;

        $this->resetAfterTest();
        $tenant = $this->create_tenant();
        $nativebefore = $DB->get_record(
            'local_iomad_companies',
            ['id' => $tenant->companyid],
            '*',
            MUST_EXIST,
        );
        $record = clone $tenant;
        $record->profilejson = json::encode([
            'udisecode' => '24000000001',
            'boardaffiliationnumber' => 'CBSE-DEMO-001',
        ]);

        $saved = (new tenant_service())->save($record);

        $native = $DB->get_record('local_iomad_companies', ['id' => $tenant->companyid], '*', MUST_EXIST);
        $this->assertSame($nativebefore->name, $native->name);
        $this->assertSame($nativebefore->city, $native->city);
        $this->assertSame($nativebefore->maincolor, $native->maincolor);
        $this->assertSame('24000000001', json::decode_object($saved->profilejson)['udisecode']);
        $this->assertFalse($DB->record_exists('local_tenantmaster_dirty', [
            'tenantid' => $tenant->id,
            'module' => 'tenant',
        ]));
    }

    /**
     * Department create/update is idempotent and rejects foreign IDs and cycles.
     *
     * @covers \local_tenantmaster\local\organisation_service
     */
    public function test_department_crud_is_scoped_and_acyclic(): void {
        global $DB;

        $this->resetAfterTest();
        $tenant = $this->create_tenant();
        $foreign = $this->create_tenant('university');
        $service = new organisation_service();
        $root = company::get_company_parentnode((int)$tenant->companyid);
        $foreignroot = company::get_company_parentnode((int)$foreign->companyid);
        $foreignname = (string)$foreignroot->name;

        $department = $service->save($tenant, (object)[
            'id' => 0,
            'name' => 'Science',
            'shortname' => 'SCIENCE',
            'parentid' => $root->id,
        ]);
        $updated = $service->save($tenant, (object)[
            'id' => $department->id,
            'name' => 'Science Faculty',
            'shortname' => 'SCIENCE',
            'parentid' => $root->id,
        ]);
        $child = $service->save($tenant, (object)[
            'id' => 0,
            'name' => 'Physics',
            'shortname' => 'PHYSICS',
            'parentid' => $department->id,
        ]);

        $this->assertSame((int)$department->id, (int)$updated->id);
        $this->assertSame('Science Faculty', $updated->name);
        $this->assertSame(1, $DB->count_records('local_tenantmaster_mapping', [
            'tenantid' => $tenant->id,
            'component' => 'local_iomad/department',
            'targetid' => $department->id,
        ]));

        try {
            $service->save($tenant, (object)[
                'id' => $foreignroot->id,
                'name' => 'Foreign overwrite',
                'shortname' => $foreignroot->shortname,
                'parentid' => 0,
            ]);
            $this->fail('A foreign department ID must be rejected.');
        } catch (\dml_missing_record_exception) {
            $this->assertSame($foreignname, $DB->get_field(
                'local_iomad_company_departments',
                'name',
                ['id' => $foreignroot->id],
                MUST_EXIST,
            ));
        }

        $this->expectException(\invalid_parameter_exception::class);
        $service->save($tenant, (object)[
            'id' => $department->id,
            'name' => 'Science Faculty',
            'shortname' => 'SCIENCE',
            'parentid' => $child->id,
        ]);
    }

    /**
     * Academic-year edit/archive reuses one native category and is tenant-bound.
     *
     * @covers \local_tenantmaster\local\academic_year_service
     */
    public function test_academic_year_crud_reuses_native_category(): void {
        global $DB;

        $this->resetAfterTest();
        $tenant = $this->create_tenant();
        $foreign = $this->create_tenant('university');
        $service = new academic_year_service();
        $year = $service->save($this->year_data($tenant, 'AY_2026', '2026-27', '2026-2027'));
        $foreignyear = $service->save($this->year_data($foreign, 'AY_2027', '2027-28', '2027-2028'));
        (new projection_service())->process((int)$tenant->id, 'categories');
        $mapping = $DB->get_record('local_tenantmaster_mapping', [
            'tenantid' => $tenant->id,
            'component' => 'core_course/category',
            'masterid' => 0,
        ], '*', MUST_EXIST);

        $update = $this->year_data($tenant, 'AY_2026', '2026-27', 'Academic year 2026-2027');
        $update->id = $year->id;
        $update->iscurrent = 0;
        $update->status = 'archived';
        $service->save($update);
        (new projection_service())->process((int)$tenant->id, 'categories');

        $this->assertSame('Academic year 2026-2027', $DB->get_field(
            'course_categories',
            'name',
            ['id' => $mapping->targetid],
            MUST_EXIST,
        ));
        $this->assertSame('0', (string)$DB->get_field(
            'course_categories',
            'visible',
            ['id' => $mapping->targetid],
            MUST_EXIST,
        ));
        $this->assertSame(0, (int)$DB->get_field(
            'local_tenantmaster_tenant',
            'activeyearid',
            ['id' => $tenant->id],
            MUST_EXIST,
        ));

        try {
            $foreignupdate = $this->year_data($tenant, 'AY_2027', '2027-28', 'Foreign overwrite');
            $foreignupdate->id = $foreignyear->id;
            $service->save($foreignupdate);
            $this->fail('A foreign academic-year ID must be rejected.');
        } catch (\dml_missing_record_exception) {
            $this->assertSame('2027-2028', $DB->get_field(
                'local_tenantmaster_acadyear',
                'name',
                ['id' => $foreignyear->id],
                MUST_EXIST,
            ));
        }

        $this->expectException(\invalid_parameter_exception::class);
        $invalid = clone $update;
        $invalid->externalid = 'AY_CHANGED';
        $service->save($invalid);
    }

    /**
     * Master update/deactivate changes the same native records without orphans.
     *
     * @covers \local_tenantmaster\local\master_service
     * @covers \local_tenantmaster\local\iomad_501_adapter
     */
    public function test_master_crud_updates_and_hides_native_records(): void {
        global $DB;

        $this->resetAfterTest();
        $tenant = $this->create_tenant();
        $year = (new academic_year_service())->save(
            $this->year_data($tenant, 'AY_2026', '2026-27', '2026-2027')
        );
        $tenant = $DB->get_record('local_tenantmaster_tenant', ['id' => $tenant->id], '*', MUST_EXIST);
        $service = new master_service();
        $grade = $service->save($this->master_data(
            $tenant,
            'grade',
            'GRADE_9',
            'STD_9',
            'Standard 9',
            0,
            0,
            (int)$year->id,
        ));
        $subject = $service->save($this->master_data(
            $tenant,
            'subject',
            'SUBJECT_MATH',
            'MATH_9',
            'Mathematics',
            0,
            (int)$grade->id,
            (int)$year->id,
        ));
        (new projection_service())->process((int)$tenant->id, 'categories');
        (new projection_service())->process((int)$tenant->id, 'courses');
        $categoryid = (int)$DB->get_field('local_tenantmaster_mapping', 'targetid', [
            'tenantid' => $tenant->id,
            'masterid' => $grade->id,
            'component' => 'core_course/category',
        ], MUST_EXIST);
        $courseid = (int)$DB->get_field('local_tenantmaster_mapping', 'targetid', [
            'tenantid' => $tenant->id,
            'masterid' => $subject->id,
            'component' => 'core/course',
        ], MUST_EXIST);

        $service->save($this->master_data(
            $tenant,
            'grade',
            'GRADE_9',
            'STD_9',
            'Archived Standard 9',
            (int)$grade->id,
            0,
            (int)$year->id,
            false,
        ));
        $service->save($this->master_data(
            $tenant,
            'subject',
            'SUBJECT_MATH',
            'MATH_9',
            'Archived Mathematics',
            (int)$subject->id,
            (int)$grade->id,
            (int)$year->id,
            false,
        ));
        (new projection_service())->process((int)$tenant->id, 'categories');
        (new projection_service())->process((int)$tenant->id, 'courses');

        $this->assertSame($categoryid, (int)$DB->get_field('local_tenantmaster_mapping', 'targetid', [
            'tenantid' => $tenant->id,
            'masterid' => $grade->id,
            'component' => 'core_course/category',
        ], MUST_EXIST));
        $this->assertSame($courseid, (int)$DB->get_field('local_tenantmaster_mapping', 'targetid', [
            'tenantid' => $tenant->id,
            'masterid' => $subject->id,
            'component' => 'core/course',
        ], MUST_EXIST));
        $this->assertEquals(0, $DB->get_field('course_categories', 'visible', ['id' => $categoryid]));
        $this->assertEquals(0, $DB->get_field('course', 'visible', ['id' => $courseid]));
        $this->assertSame(1, $DB->count_records('course', [
            'idnumber' => 'TM:' . $tenant->trustcode . ':SUBJECT:SUBJECT_MATH',
        ]));

        try {
            $service->save($this->master_data(
                $tenant,
                'subject',
                'SUBJECT_CHANGED',
                'MATH_9',
                'Invalid mutation',
                (int)$subject->id,
                (int)$grade->id,
                (int)$year->id,
            ));
            $this->fail('A stable external ID must not change during update.');
        } catch (\invalid_parameter_exception) {
            $this->assertSame('SUBJECT_MATH', $DB->get_field(
                'local_tenantmaster_master',
                'externalid',
                ['id' => $subject->id],
                MUST_EXIST,
            ));
        }

        $parent = $service->save($this->master_data(
            $tenant,
            'grade',
            'GRADE_11',
            'STD_11',
            'Standard 11',
        ));
        $child = $service->save($this->master_data(
            $tenant,
            'grade',
            'GRADE_11_A',
            'STD_11_A',
            'Standard 11 A',
            0,
            (int)$parent->id,
        ));
        $this->expectException(\invalid_parameter_exception::class);
        $service->save($this->master_data(
            $tenant,
            'grade',
            'GRADE_11',
            'STD_11',
            'Standard 11',
            (int)$parent->id,
            (int)$child->id,
        ));
    }

    /**
     * Native access upserts are idempotent and retain tenant relationships.
     *
     * @covers \local_tenantmaster\local\learning_access_service
     * @covers \local_tenantmaster\local\people_service
     */
    public function test_native_access_crud_is_idempotent(): void {
        global $DB;

        $this->resetAfterTest();
        $tenant = $this->create_tenant();
        (new role_service())->ensure_defaults((int)$tenant->id);
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $company = new company((int)$tenant->companyid);
        $root = company::get_company_parentnode((int)$tenant->companyid);
        $company->add_course($course, (int)$root->id);
        $learner = $generator->create_user();
        $guardian = $generator->create_user();
        foreach ([$learner, $guardian] as $user) {
            company::upsert_company_user(
                (int)$user->id,
                (int)$tenant->companyid,
                (int)$root->id,
                0,
            );
        }

        $service = new learning_access_service();
        $cohortid = $service->ensure_cohort($tenant, 'CLASS_A', 'Class A');
        $this->assertSame($cohortid, $service->ensure_cohort($tenant, 'CLASS_A', 'Class A updated'));
        $service->add_cohort_member($tenant, $cohortid, (int)$learner->id);
        $service->add_cohort_member($tenant, $cohortid, (int)$learner->id);

        $groupid = $service->ensure_group($tenant, (int)$course->id, 'SECTION_A', 'Section A');
        $this->assertSame($groupid, $service->ensure_group(
            $tenant,
            (int)$course->id,
            'SECTION_A',
            'Section A updated',
        ));
        $service->add_group_member($tenant, $groupid, (int)$learner->id);
        $studentroleid = (int)$DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        $service->enrol_user($tenant, (int)$course->id, (int)$learner->id, $studentroleid, $groupid);
        $service->enrol_user($tenant, (int)$course->id, (int)$learner->id, $studentroleid, $groupid);
        (new people_service())->link_guardian($tenant, (int)$guardian->id, (int)$learner->id);
        (new people_service())->link_guardian($tenant, (int)$guardian->id, (int)$learner->id);

        $this->assertSame(1, $DB->count_records('cohort_members', [
            'cohortid' => $cohortid,
            'userid' => $learner->id,
        ]));
        $this->assertSame(1, $DB->count_records('groups_members', [
            'groupid' => $groupid,
            'userid' => $learner->id,
        ]));
        $this->assertTrue(is_enrolled(\context_course::instance((int)$course->id), $learner, '', true));
        $this->assertSame(1, $DB->count_records('role_assignments', [
            'roleid' => $DB->get_field('role', 'id', ['shortname' => 'tenantguardian'], MUST_EXIST),
            'userid' => $guardian->id,
            'contextid' => \context_user::instance((int)$learner->id)->id,
        ]));
        $this->assertSame(2, $DB->count_records('local_tenantmaster_mapping', [
            'tenantid' => $tenant->id,
            'status' => 'synced',
        ]));
    }

    /**
     * A fresh edit or manual retry receives a fresh retry budget.
     *
     * @covers \local_tenantmaster\local\queue_service
     */
    public function test_dirty_upsert_resets_attempt_counter(): void {
        global $DB;

        $this->resetAfterTest();
        $tenant = $this->create_tenant();
        $queue = new queue_service();
        $dirtyid = $queue->mark_dirty(
            (int)$tenant->id,
            'categories',
            'local_tenantmaster_master',
            12345,
            'initial',
        );
        $DB->set_field('local_tenantmaster_dirty', 'attempts', 5, ['id' => $dirtyid]);
        $queue->mark_dirty(
            (int)$tenant->id,
            'categories',
            'local_tenantmaster_master',
            12345,
            'manual_retry',
        );

        $this->assertSame(0, (int)$DB->get_field(
            'local_tenantmaster_dirty',
            'attempts',
            ['id' => $dirtyid],
            MUST_EXIST,
        ));
    }

    /**
     * Ignore closes one drift baseline until the source is edited again.
     *
     * @covers \local_tenantmaster\local\drift_service
     */
    public function test_drift_ignore_does_not_reopen_unchanged_native_value(): void {
        global $DB;

        $this->resetAfterTest();
        $tenant = $this->create_tenant();
        $master = (new master_service())->save($this->master_data(
            $tenant,
            'grade',
            'GRADE_10',
            'STD_10',
            'Standard 10',
        ));
        (new projection_service())->process((int)$tenant->id, 'categories');
        $mapping = $DB->get_record('local_tenantmaster_mapping', [
            'tenantid' => $tenant->id,
            'masterid' => $master->id,
            'component' => 'core_course/category',
        ], '*', MUST_EXIST);
        \core_course_category::get((int)$mapping->targetid, MUST_EXIST, true)->update((object)[
            'name' => 'Native administrator name',
        ]);

        $service = new drift_service();
        $this->assertGreaterThan(0, $service->detect_mapping($mapping));
        $drift = $DB->get_record('local_tenantmaster_drift', [
            'mappingid' => $mapping->id,
            'fieldpath' => 'name',
            'status' => 'open',
        ], '*', MUST_EXIST);
        $service->resolve((int)$tenant->id, (int)$drift->id, 'ignore');
        $mapping = $DB->get_record('local_tenantmaster_mapping', ['id' => $mapping->id], '*', MUST_EXIST);

        $this->assertSame('ignored', $mapping->status);
        $this->assertSame(0, $service->detect_mapping($mapping));
        $this->assertSame(0, $DB->count_records('local_tenantmaster_drift', [
            'mappingid' => $mapping->id,
            'status' => 'open',
        ]));
    }

    /**
     * Academic-year fixture.
     *
     * @param object $tenant Tenant.
     * @param string $externalid External ID.
     * @param string $code Code.
     * @param string $name Name.
     * @return object
     */
    private function year_data(object $tenant, string $externalid, string $code, string $name): object {
        return (object)[
            'id' => 0,
            'tenantid' => $tenant->id,
            'externalid' => $externalid,
            'code' => $code,
            'name' => $name,
            'startdate' => make_timestamp(2026, 4, 1),
            'enddate' => make_timestamp(2027, 3, 31),
            'iscurrent' => 1,
            'status' => 'active',
            'payloadjson' => '{}',
        ];
    }

    /**
     * Academic master fixture.
     *
     * @param object $tenant Tenant.
     * @param string $type Type.
     * @param string $externalid External ID.
     * @param string $code Code.
     * @param string $name Name.
     * @param int $id Existing ID.
     * @param int $parentid Parent.
     * @param int $acadyearid Academic year.
     * @param bool $active Active.
     * @return object
     */
    private function master_data(
        object $tenant,
        string $type,
        string $externalid,
        string $code,
        string $name,
        int $id = 0,
        int $parentid = 0,
        int $acadyearid = 0,
        bool $active = true,
    ): object {
        return (object)[
            'id' => $id,
            'tenantid' => $tenant->id,
            'acadyearid' => $acadyearid,
            'parentid' => $parentid,
            'mastertype' => $type,
            'externalid' => $externalid,
            'code' => $code,
            'name' => $name,
            'description' => '',
            'payloadjson' => '{}',
            'active' => (int)$active,
            'sortorder' => 1,
        ];
    }
}
