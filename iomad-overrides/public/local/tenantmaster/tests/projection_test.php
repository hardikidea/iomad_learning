<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster;

use local_tenantmaster\local\course_configuration_service;
use local_tenantmaster\local\academic_year_service;
use local_tenantmaster\local\master_service;
use local_tenantmaster\local\projection_service;
use local_tenantmaster\local\queue_service;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/tenantmaster_testcase.php');

/**
 * Native category, course and gradebook projection tests.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class projection_test extends tenantmaster_testcase {
    /**
     * Academic years become native root categories for academic projections.
     *
     * @covers \local_tenantmaster\local\academic_year_service
     * @covers \local_tenantmaster\local\iomad_501_adapter
     */
    public function test_academic_year_is_native_category_parent(): void {
        global $DB;

        $this->resetAfterTest();
        $tenant = $this->create_tenant();
        $year = (new academic_year_service())->ensure_current($tenant);
        $tenant = $DB->get_record('local_tenantmaster_tenant', ['id' => $tenant->id], '*', MUST_EXIST);
        $grade = (new master_service())->save(
            $this->master_data($tenant, 'grade', 'GRADE_9', 'STD_9', 'Standard 9'),
        );
        (new projection_service())->process((int)$tenant->id, 'categories');

        $yearmapping = $DB->get_record('local_tenantmaster_mapping', [
            'tenantid' => $tenant->id,
            'component' => 'core_course/category',
            'externalkey' => 'TM:' . $tenant->trustcode . ':ACADEMIC_YEAR:' . $year->externalid,
        ], '*', MUST_EXIST);
        $grademapping = $DB->get_record('local_tenantmaster_mapping', [
            'tenantid' => $tenant->id,
            'masterid' => $grade->id,
            'component' => 'core_course/category',
        ], '*', MUST_EXIST);
        $this->assertSame(
            (int)$yearmapping->targetid,
            (int)$DB->get_field('course_categories', 'parent', ['id' => $grademapping->targetid], MUST_EXIST),
        );
    }

    /**
     * Grade and subject project to real native records idempotently.
     *
     * @covers \local_tenantmaster\local\projection_service
     * @covers \local_tenantmaster\local\iomad_501_adapter
     */
    public function test_grade_and_subject_project_to_native_records(): void {
        global $DB;

        $this->resetAfterTest();
        $tenant = $this->create_tenant();
        $service = new master_service();
        $grade = $service->save($this->master_data($tenant, 'grade', 'GRADE_8', 'STD_8', 'Standard 8'));
        $subject = $service->save($this->master_data(
            $tenant,
            'subject',
            'SUBJECT_MATH',
            'MATHEMATICS',
            'Mathematics',
        ));

        (new projection_service())->process((int)$tenant->id, 'categories');
        (new projection_service())->process((int)$tenant->id, 'courses');
        $categorymapping = $DB->get_record('local_tenantmaster_mapping', [
            'tenantid' => $tenant->id,
            'masterid' => $grade->id,
            'component' => 'core_course/category',
        ], '*', MUST_EXIST);
        $coursemapping = $DB->get_record('local_tenantmaster_mapping', [
            'tenantid' => $tenant->id,
            'masterid' => $subject->id,
            'component' => 'core/course',
        ], '*', MUST_EXIST);
        $this->assertTrue($DB->record_exists('course_categories', ['id' => $categorymapping->targetid]));
        $this->assertTrue($DB->record_exists('course', ['id' => $coursemapping->targetid]));
        $this->assertTrue($DB->record_exists('local_iomad_company_courses', [
            'companyid' => $tenant->companyid,
            'courseid' => $coursemapping->targetid,
        ]));
        $this->assertSame(
            $tenant->trustcode,
            $this->course_custom_field_value((int)$coursemapping->targetid, 'tm_company_code'),
        );
        $this->assertSame(
            'school',
            $this->course_custom_field_value((int)$coursemapping->targetid, 'tm_institution_type'),
        );
        $this->assertSame(
            'MATHEMATICS',
            $this->course_custom_field_value((int)$coursemapping->targetid, 'tm_subject'),
        );
        [$fieldsql, $fieldparams] = $DB->get_in_or_equal(
            array_keys(\local_tenantmaster\local\course_metadata_service::FIELDS),
            SQL_PARAMS_NAMED,
            'tmfield',
        );
        $this->assertSame(13, $DB->count_records_select(
            'customfield_field',
            "shortname $fieldsql",
            $fieldparams,
        ));

        $service->save($this->master_data(
            $tenant,
            'subject',
            'SUBJECT_MATH',
            'MATHEMATICS',
            'Mathematics',
            (int)$subject->id,
        ));
        (new projection_service())->process((int)$tenant->id, 'courses');
        $this->assertSame(1, $DB->count_records('course', ['idnumber' => $coursemapping->externalkey]));
    }

    /**
     * Read one native course custom field by stable shortname.
     */
    private function course_custom_field_value(int $courseid, string $shortname): string {
        global $DB;

        return (string)$DB->get_field_sql(
            "SELECT d.value
               FROM {customfield_data} d
               JOIN {customfield_field} f ON f.id = d.fieldid
              WHERE d.instanceid = :courseid
                AND d.component = :component
                AND d.area = :area
                AND f.shortname = :shortname",
            [
                'courseid' => $courseid,
                'component' => 'core_course',
                'area' => 'course',
                'shortname' => $shortname,
            ],
            MUST_EXIST,
        );
    }

    /**
     * Attendance and assessment policy create native gradebook records.
     *
     * @covers \local_tenantmaster\local\course_configuration_service
     */
    public function test_course_policy_uses_native_gradebook(): void {
        global $DB;

        $this->resetAfterTest();
        $tenant = $this->create_tenant();
        $subject = (new master_service())->save($this->master_data(
            $tenant,
            'subject',
            'SUBJECT_SCIENCE',
            'SCIENCE',
            'Science',
        ));
        (new projection_service())->process((int)$tenant->id, 'courses');
        $courseid = (int)$DB->get_field('local_tenantmaster_mapping', 'targetid', [
            'tenantid' => $tenant->id,
            'masterid' => $subject->id,
            'component' => 'core/course',
        ], MUST_EXIST);

        (new course_configuration_service())->apply($tenant, $courseid);
        $categoryitem = $DB->get_record('grade_items', [
            'courseid' => $courseid,
            'itemtype' => 'category',
            'idnumber' => 'TM_ASSESSMENT',
        ], '*', MUST_EXIST);
        $this->assertTrue($DB->record_exists('grade_categories', [
            'id' => $categoryitem->iteminstance,
        ]));
        $this->assertTrue($DB->record_exists('grade_items', [
            'courseid' => $courseid,
            'itemtype' => 'local',
            'itemmodule' => 'tenantmaster',
            'idnumber' => 'TM_ATTENDANCE',
        ]));
        $this->assertEquals(1, $DB->get_field('course', 'enablecompletion', ['id' => $courseid]));
    }

    /**
     * A failed item is retryable and its parent job closes without truncation.
     *
     * @covers \local_tenantmaster\local\projection_service
     */
    public function test_projection_failure_is_recorded_without_crashing_job(): void {
        global $DB;

        $this->resetAfterTest();
        $tenant = $this->create_tenant();
        (new queue_service())->mark_dirty(
            (int)$tenant->id,
            'categories',
            'local_tenantmaster_master',
            999999,
            'failure_path_test',
        );

        $job = (new projection_service())->process((int)$tenant->id, 'categories');

        $this->assertSame('completed_with_errors', $job->status);
        $this->assertSame(1, (int)$job->faileditems);
        $this->assertTrue($DB->record_exists('local_tenantmaster_dirty', [
            'tenantid' => $tenant->id,
            'entityid' => 999999,
            'state' => 'retryable',
        ]));
    }

    /**
     * Master fixture.
     *
     * @param object $tenant Tenant.
     * @param string $type Type.
     * @param string $externalid External ID.
     * @param string $code Code.
     * @param string $name Name.
     * @param int $id Optional existing ID.
     * @return object
     */
    private function master_data(
        object $tenant,
        string $type,
        string $externalid,
        string $code,
        string $name,
        int $id = 0,
    ): object {
        return (object)[
            'id' => $id,
            'tenantid' => $tenant->id,
            'acadyearid' => 0,
            'parentid' => 0,
            'mastertype' => $type,
            'externalid' => $externalid,
            'code' => $code,
            'name' => $name,
            'description' => '',
            'payloadjson' => '{}',
            'active' => 1,
            'sortorder' => 1,
        ];
    }
}
