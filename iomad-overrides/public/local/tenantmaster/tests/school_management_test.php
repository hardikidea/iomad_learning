<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster;

use local_iomad\company;
use local_tenantmaster\local\academic_year_service;
use local_tenantmaster\local\course_copy_service;
use local_tenantmaster\local\default_service;
use local_tenantmaster\local\master_service;
use local_tenantmaster\local\onboarding_service;
use local_tenantmaster\local\projection_service;
use local_tenantmaster\local\school_year_setup_service;
use local_tenantmaster\local\student_placement_service;
use local_tenantmaster\local\student_progression_service;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/tenantmaster_testcase.php');

/**
 * Native-backed Indian school management lifecycle tests.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class school_management_test extends tenantmaster_testcase {
    /**
     * First-run setup adopts an existing native company without duplicating it.
     *
     * @covers \local_tenantmaster\local\onboarding_service::adopt_existing
     */
    public function test_school_initialisation_adopts_native_company_and_defaults(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $company = company::create_company((object)[
            'name' => 'Validation School',
            'shortname' => 'validationschool',
            'code' => 'TRUST_VALIDATION',
            'city' => 'Ahmedabad',
            'country' => 'IN',
            'address' => 'Sanitized validation address',
            'region' => 'Gujarat',
            'postcode' => '380001',
            'hostname' => 'validation-school.example.test',
            'theme' => '',
            'parentid' => 0,
            'custom1' => '',
            'custom2' => '',
            'custom3' => '',
            'templates' => [],
        ]);
        $tenant = (new onboarding_service())->adopt_existing((int)$company->id, 'school');

        $this->assertSame('Validation School', $company->get_name());
        $this->assertSame('TRUST_VALIDATION', $company->get('code'));
        $this->assertSame('school', $tenant->tenanttype);
        $this->assertSame(1, $DB->count_records('local_iomad_companies', ['code' => 'TRUST_VALIDATION']));
        $this->assertGreaterThan(0, (int)$tenant->activeyearid);
        $this->assertGreaterThan(0, $DB->count_records(
            'local_tenantmaster_rolemap',
            ['tenantid' => $tenant->id, 'active' => 1],
        ));
        $this->assertGreaterThan(0, $DB->count_records(
            'local_tenantmaster_master',
            ['tenantid' => $tenant->id, 'mastertype' => 'grade', 'active' => 1],
        ));
        $this->assertFalse($DB->record_exists('local_tenantmaster_dirty', [
            'tenantid' => $tenant->id,
            'module' => 'tenant',
        ]));
    }

    /**
     * Shared defaults expand idempotently into year-scoped native courses.
     *
     * @covers \local_tenantmaster\local\school_year_setup_service
     */
    public function test_school_year_setup_generates_year_scoped_course_shells(): void {
        global $DB;

        $this->resetAfterTest();
        $tenant = $this->create_tenant();
        (new default_service())->adopt($tenant);
        $year = $DB->get_record(
            'local_tenantmaster_acadyear',
            ['tenantid' => $tenant->id, 'iscurrent' => 1],
            '*',
            MUST_EXIST,
        );
        $defaults = [
            'board' => 'BOARD_CBSE',
            'medium' => 'MEDIUM_ENGLISH',
            'grade' => 'GRADE_STD_1',
            'subject' => 'SUBJECT_MATHEMATICS',
        ];
        $ids = [];
        foreach ($defaults as $type => $externalid) {
            $ids[$type] = (int)$DB->get_field('local_tenantmaster_master', 'id', [
                'tenantid' => $tenant->id,
                'mastertype' => $type,
                'externalid' => $externalid,
                'acadyearid' => 0,
                'active' => 1,
            ], MUST_EXIST);
        }
        $data = (object)[
            'setupyearid' => $year->id,
            'setupboardid' => $ids['board'],
            'setupmediumid' => $ids['medium'],
            'setupgradeids' => [$ids['grade']],
            'setupstreamid' => 0,
            'setupsubjectids' => [$ids['subject']],
        ];
        $service = new school_year_setup_service();
        $first = $service->generate($tenant, $data);
        $second = $service->generate($tenant, $data);
        (new projection_service())->process((int)$tenant->id, 'categories');
        (new projection_service())->process((int)$tenant->id, 'courses');

        $this->assertSame(4, $first['created']);
        $this->assertSame(1, $first['courses']);
        $this->assertSame(0, $second['created']);
        $this->assertSame(4, $second['existing']);
        $subject = $DB->get_record('local_tenantmaster_master', [
            'tenantid' => $tenant->id,
            'acadyearid' => $year->id,
            'mastertype' => 'subject',
        ], '*', MUST_EXIST);
        $this->assertTrue($DB->record_exists('local_tenantmaster_mapping', [
            'tenantid' => $tenant->id,
            'masterid' => $subject->id,
            'component' => 'core/course',
            'status' => 'synced',
        ]));
    }

    /**
     * Placement creates a real cohort, group and cohort-sync enrolment.
     *
     * @covers \local_tenantmaster\local\student_placement_service
     * @covers \local_tenantmaster\local\learning_access_service
     */
    public function test_student_placement_projects_native_class_access(): void {
        global $DB;

        $this->resetAfterTest();
        $tenant = $this->create_tenant();
        [$year, $board, $medium, $grade, $division, $subject, $courseid] =
            $this->create_school_structure($tenant, '2026', '7');
        $student = $this->create_company_user($tenant);

        $placement = (new student_placement_service())->save($tenant, (object)[
            'id' => 0,
            'userid' => $student->id,
            'acadyearid' => $year->id,
            'boardid' => $board->id,
            'mediumid' => $medium->id,
            'gradeid' => $grade->id,
            'streamid' => 0,
            'divisionid' => $division->id,
            'rollnumber' => '17',
            'status' => 'active',
        ]);

        $this->assertGreaterThan(0, (int)$placement->cohortid);
        $this->assertSame(1, (int)$placement->provisionedcourses);
        $this->assertTrue($DB->record_exists('cohort_members', [
            'cohortid' => $placement->cohortid,
            'userid' => $student->id,
        ]));
        $instance = $DB->get_record('enrol', [
            'enrol' => 'cohort',
            'courseid' => $courseid,
            'customint1' => $placement->cohortid,
            'status' => ENROL_INSTANCE_ENABLED,
        ], '*', MUST_EXIST);
        $this->assertGreaterThan(0, (int)$instance->customint2);
        $this->assertTrue($DB->record_exists('groups_members', [
            'groupid' => $instance->customint2,
            'userid' => $student->id,
        ]));
        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $this->assertSame(SEPARATEGROUPS, (int)$course->groupmode);
        $this->assertSame(1, (int)$course->groupmodeforce);
        $this->assertSame((int)$subject->id, (int)$DB->get_field(
            'local_tenantmaster_mapping',
            'masterid',
            ['component' => 'core/course', 'targetid' => $courseid],
            MUST_EXIST,
        ));
    }

    /**
     * Promotion creates next-year native access and preserves prior placement.
     *
     * @covers \local_tenantmaster\local\student_progression_service
     */
    public function test_progression_preserves_history_and_creates_target_placement(): void {
        global $DB;

        $this->resetAfterTest();
        $tenant = $this->create_tenant();
        [$fromyear, $board, $medium, $grade7, $divisiona] =
            $this->create_school_structure($tenant, '2026', '7');
        [$toyear, , , $grade8, $divisionb] =
            $this->create_school_structure($tenant, '2027', '8');
        $student = $this->create_company_user($tenant);
        $source = (new student_placement_service())->save($tenant, (object)[
            'id' => 0,
            'userid' => $student->id,
            'acadyearid' => $fromyear->id,
            'boardid' => $board->id,
            'mediumid' => $medium->id,
            'gradeid' => $grade7->id,
            'streamid' => 0,
            'divisionid' => $divisiona->id,
            'rollnumber' => '17',
            'status' => 'active',
        ]);
        $service = new student_progression_service();
        $plan = $service->plan($tenant, (object)[
            'sourceplaceid' => $source->id,
            'toyearid' => $toyear->id,
            'decision' => 'promote',
            'targetgradeid' => $grade8->id,
            'targetstreamid' => 0,
            'targetdivisionid' => $divisionb->id,
            'reason' => 'Approved annual promotion',
        ]);
        $applied = $service->apply($tenant, (int)$plan->id);

        $this->assertSame('completed', $applied->status);
        $this->assertGreaterThan(0, (int)$applied->targetplaceid);
        $this->assertSame('completed', $DB->get_field(
            'local_tenantmaster_placement',
            'status',
            ['id' => $source->id],
            MUST_EXIST,
        ));
        $target = $DB->get_record(
            'local_tenantmaster_placement',
            ['id' => $applied->targetplaceid],
            '*',
            MUST_EXIST,
        );
        $this->assertSame((int)$toyear->id, (int)$target->acadyearid);
        $this->assertSame((int)$grade8->id, (int)$target->gradeid);
        $this->assertSame('active', $target->status);
        $this->assertTrue($DB->record_exists('cohort_members', [
            'cohortid' => $source->cohortid,
            'userid' => $student->id,
        ]));
        $this->assertTrue($DB->record_exists('cohort_members', [
            'cohortid' => $target->cohortid,
            'userid' => $student->id,
        ]));
    }

    /**
     * Placement rejects users from another IOMAD company.
     *
     * @covers \local_tenantmaster\local\student_placement_service
     */
    public function test_student_placement_rejects_cross_tenant_user(): void {
        $this->resetAfterTest();
        $tenant = $this->create_tenant();
        $foreign = $this->create_tenant('school');
        [$year, $board, $medium, $grade, $division] =
            $this->create_school_structure($tenant, '2026', '7');
        $foreignstudent = $this->create_company_user($foreign);

        $this->expectException(\invalid_parameter_exception::class);
        (new student_placement_service())->save($tenant, (object)[
            'id' => 0,
            'userid' => $foreignstudent->id,
            'acadyearid' => $year->id,
            'boardid' => $board->id,
            'mediumid' => $medium->id,
            'gradeid' => $grade->id,
            'streamid' => 0,
            'divisionid' => $division->id,
            'status' => 'active',
        ]);
    }

    /**
     * Course copy imports activities without users or enrolments.
     *
     * @covers \local_tenantmaster\local\course_copy_service
     */
    public function test_course_copy_is_user_free_and_idempotent(): void {
        global $DB;

        $this->resetAfterTest();
        $tenant = $this->create_tenant();
        $generator = $this->getDataGenerator();
        $source = $generator->create_course();
        $target = $generator->create_course();
        $generator->create_module('page', ['course' => $source->id, 'name' => 'Approved lesson']);
        $company = new company((int)$tenant->companyid);
        $department = company::get_company_parentnode((int)$tenant->companyid);
        $company->add_course($source, (int)$department->id);
        $company->add_course($target, (int)$department->id);

        $service = new course_copy_service();
        $first = $service->copy($tenant, (int)$source->id, (int)$target->id);
        $second = $service->copy($tenant, (int)$source->id, (int)$target->id);

        $this->assertSame('completed', $first->status);
        $this->assertSame((int)$first->id, (int)$second->id);
        $this->assertGreaterThan(0, $DB->count_records('course_modules', ['course' => $target->id]));
        $this->assertSame(0, $DB->count_records_sql(
            "SELECT COUNT(ue.id)
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.courseid = :courseid",
            ['courseid' => $target->id],
        ));
    }

    /**
     * Build one year-specific board/medium/grade/subject hierarchy.
     *
     * @return array<int, object|int>
     */
    private function create_school_structure(object $tenant, string $yearcode, string $gradecode): array {
        global $DB;

        $year = (new academic_year_service())->save((object)[
            'id' => 0,
            'tenantid' => $tenant->id,
            'externalid' => 'AY_' . $yearcode,
            'code' => $yearcode . '-' . ((int)$yearcode + 1),
            'name' => $yearcode . '-' . ((int)$yearcode + 1),
            'startdate' => make_timestamp((int)$yearcode, 4, 1),
            'enddate' => make_timestamp((int)$yearcode + 1, 3, 31),
            'iscurrent' => $yearcode === '2026' ? 1 : 0,
            'status' => 'active',
            'payloadjson' => '{}',
        ]);
        $service = new master_service();
        $board = $this->create_master($service, $tenant, $year, 'board', 'CBSE_' . $yearcode, 'CBSE', 0);
        $medium = $this->create_master(
            $service,
            $tenant,
            $year,
            'medium',
            'ENGLISH_' . $yearcode,
            'English',
            (int)$board->id,
        );
        $grade = $this->create_master(
            $service,
            $tenant,
            $year,
            'grade',
            'STD_' . $gradecode . '_' . $yearcode,
            'Standard ' . $gradecode,
            (int)$medium->id,
        );
        $division = $this->create_master(
            $service,
            $tenant,
            $year,
            'division',
            'DIV_A_' . $yearcode,
            $yearcode === '2026' ? 'Division A' : 'Division B',
            0,
        );
        $subject = $this->create_master(
            $service,
            $tenant,
            $year,
            'subject',
            'MATH_' . $gradecode . '_' . $yearcode,
            'Mathematics',
            (int)$grade->id,
        );
        (new projection_service())->process((int)$tenant->id, 'categories');
        (new projection_service())->process((int)$tenant->id, 'courses');
        $courseid = (int)$DB->get_field('local_tenantmaster_mapping', 'targetid', [
            'tenantid' => $tenant->id,
            'masterid' => $subject->id,
            'component' => 'core/course',
        ], MUST_EXIST);
        return [$year, $board, $medium, $grade, $division, $subject, $courseid];
    }

    /**
     * Create one year-scoped academic master.
     */
    private function create_master(
        master_service $service,
        object $tenant,
        object $year,
        string $type,
        string $code,
        string $name,
        int $parentid,
    ): object {
        return $service->save((object)[
            'id' => 0,
            'tenantid' => (int)$tenant->id,
            'acadyearid' => (int)$year->id,
            'parentid' => $parentid,
            'mastertype' => $type,
            'externalid' => $type . '_' . $code,
            'code' => $code,
            'name' => $name,
            'description' => '',
            'payloadjson' => '{}',
            'active' => 1,
            'sortorder' => 1,
        ]);
    }

    /**
     * Create one native user and company membership.
     */
    private function create_company_user(object $tenant): object {
        $user = $this->getDataGenerator()->create_user();
        $department = company::get_company_parentnode((int)$tenant->companyid);
        company::upsert_company_user(
            (int)$user->id,
            (int)$tenant->companyid,
            (int)$department->id,
            0,
        );
        return $user;
    }
}
