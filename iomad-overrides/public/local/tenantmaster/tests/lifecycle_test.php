<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster;

use local_tenantmaster\local\academic_year_service;
use local_tenantmaster\local\master_service;
use local_tenantmaster\local\rollover_service;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/tenantmaster_testcase.php');

/**
 * Academic lifecycle integration tests.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class lifecycle_test extends tenantmaster_testcase {
    /**
     * Rollover rebuilds target-year parents and queues each native service.
     *
     * @covers \local_tenantmaster\local\rollover_service
     */
    public function test_rollover_preserves_hierarchy_and_service_routing(): void {
        global $DB;

        $this->resetAfterTest();
        $tenant = $this->create_tenant();
        $yearservice = new academic_year_service();
        $from = $yearservice->save($this->year_data(
            $tenant,
            'AY_2026',
            '2026-27',
            '2026-2027',
            make_timestamp(2026, 4, 1),
            make_timestamp(2027, 3, 31),
            true,
        ));
        $to = $yearservice->save($this->year_data(
            $tenant,
            'AY_2027',
            '2027-28',
            '2027-2028',
            make_timestamp(2027, 4, 1),
            make_timestamp(2028, 3, 31),
            false,
        ));
        $service = new master_service();
        $grade = $service->save($this->master_data(
            $tenant,
            (int)$from->id,
            'grade',
            'GRADE_8',
            'STD_8',
            'Standard 8',
        ));
        $subject = $service->save($this->master_data(
            $tenant,
            (int)$from->id,
            'subject',
            'SUBJECT_MATH',
            'MATH_8',
            'Mathematics',
            (int)$grade->id,
        ));
        $assessment = $service->save($this->master_data(
            $tenant,
            (int)$from->id,
            'assessment_policy',
            'ASSESSMENT_TERM',
            'TERM',
            'Term assessment',
        ));

        $rollovers = new rollover_service();
        $plan = $rollovers->plan($tenant, (int)$from->id, (int)$to->id);
        $result = $rollovers->apply($tenant, (int)$plan->id, 'test-recovery-set');

        $this->assertSame('completed', $result->status);
        $this->assertSame('test-recovery-set', $result->backupref);
        $gradeitem = $DB->get_record('local_tenantmaster_rollitem', [
            'rolloverid' => $plan->id,
            'sourceid' => $grade->id,
        ], '*', MUST_EXIST);
        $subjectitem = $DB->get_record('local_tenantmaster_rollitem', [
            'rolloverid' => $plan->id,
            'sourceid' => $subject->id,
        ], '*', MUST_EXIST);
        $assessmentitem = $DB->get_record('local_tenantmaster_rollitem', [
            'rolloverid' => $plan->id,
            'sourceid' => $assessment->id,
        ], '*', MUST_EXIST);
        $targetsubject = $DB->get_record('local_tenantmaster_master', [
            'id' => $subjectitem->targetid,
            'tenantid' => $tenant->id,
        ], '*', MUST_EXIST);

        $this->assertSame((int)$gradeitem->targetid, (int)$targetsubject->parentid);
        $this->assertSame((int)$to->id, (int)$targetsubject->acadyearid);
        $this->assertTrue($DB->record_exists('local_tenantmaster_dirty', [
            'tenantid' => $tenant->id,
            'module' => 'categories',
            'entityid' => $gradeitem->targetid,
        ]));
        $this->assertTrue($DB->record_exists('local_tenantmaster_dirty', [
            'tenantid' => $tenant->id,
            'module' => 'courses',
            'entityid' => $subjectitem->targetid,
        ]));
        $this->assertTrue($DB->record_exists('local_tenantmaster_dirty', [
            'tenantid' => $tenant->id,
            'module' => 'assessments',
            'entityid' => $assessmentitem->targetid,
        ]));
    }

    /**
     * Academic-year fixture.
     *
     * @param object $tenant Tenant.
     * @param string $externalid External ID.
     * @param string $code Code.
     * @param string $name Name.
     * @param int $startdate Start.
     * @param int $enddate End.
     * @param bool $current Current.
     * @return object
     */
    private function year_data(
        object $tenant,
        string $externalid,
        string $code,
        string $name,
        int $startdate,
        int $enddate,
        bool $current,
    ): object {
        return (object)[
            'id' => 0,
            'tenantid' => $tenant->id,
            'externalid' => $externalid,
            'code' => $code,
            'name' => $name,
            'startdate' => $startdate,
            'enddate' => $enddate,
            'iscurrent' => (int)$current,
            'status' => 'active',
            'payloadjson' => '{}',
        ];
    }

    /**
     * Academic master fixture.
     *
     * @param object $tenant Tenant.
     * @param int $yearid Academic year.
     * @param string $type Type.
     * @param string $externalid External ID.
     * @param string $code Code.
     * @param string $name Name.
     * @param int $parentid Parent.
     * @return object
     */
    private function master_data(
        object $tenant,
        int $yearid,
        string $type,
        string $externalid,
        string $code,
        string $name,
        int $parentid = 0,
    ): object {
        return (object)[
            'id' => 0,
            'tenantid' => $tenant->id,
            'acadyearid' => $yearid,
            'parentid' => $parentid,
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
