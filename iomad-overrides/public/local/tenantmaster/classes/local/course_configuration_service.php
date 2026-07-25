<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\local;

/**
 * Native gradebook, attendance-grade and completion configuration.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class course_configuration_service {
    /**
     * Apply tenant policies to one native company course.
     *
     * @param object $tenant Tenant.
     * @param int $courseid Course.
     * @return array<string, int|string>
     */
    public function apply(object $tenant, int $courseid): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/grade/lib.php');
        require_once($CFG->dirroot . '/course/lib.php');
        if (
            !$DB->record_exists('local_iomad_company_courses', [
                'companyid' => $tenant->companyid,
                'courseid' => $courseid,
            ])
        ) {
            throw new \invalid_parameter_exception('Course belongs to another tenant.');
        }
        $assessment = $this->policy((int)$tenant->id, 'assessment_policy');
        $attendance = $this->policy((int)$tenant->id, 'attendance_policy');
        $assessmentconfig = $assessment ? json::decode_object($assessment->payloadjson) : [];
        $attendanceconfig = $attendance ? json::decode_object($attendance->payloadjson) : [];
        $passpercent = (float)($assessmentconfig['passpercent'] ?? 40);
        $minimumattendance = (float)($attendanceconfig['minimumpercent'] ?? 75);

        $categoryid = $this->ensure_grade_category($courseid, 'TM_ASSESSMENT', 'Assessment');
        $attendanceitemid = $this->ensure_grade_item(
            $courseid,
            'TM_ATTENDANCE',
            'Attendance',
            100.0,
            $minimumattendance,
        );
        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        if (empty($course->enablecompletion)) {
            update_course((object)['id' => $courseid, 'enablecompletion' => 1]);
        }
        $courseitem = \grade_item::fetch_course_item($courseid);
        $courseitem->gradepass = $passpercent;
        $courseitem->update('local_tenantmaster');
        (new audit_service())->record(
            (int)$tenant->id,
            'course.configuration.applied',
            'success',
            [
                'courseid' => $courseid,
                'passpercent' => $passpercent,
                'minimumattendance' => $minimumattendance,
            ],
            ['entitytable' => 'course', 'entityid' => $courseid, 'targetcomponent' => 'core/grade'],
        );
        return [
            'courseid' => $courseid,
            'gradecategoryid' => $categoryid,
            'attendanceitemid' => $attendanceitemid,
            'status' => 'configured',
        ];
    }

    /**
     * Ensure a native grade category through the grade API.
     *
     * @param int $courseid Course.
     * @param string $idnumber ID number.
     * @param string $fullname Name.
     * @return int
     */
    private function ensure_grade_category(int $courseid, string $idnumber, string $fullname): int {
        $item = \grade_item::fetch([
            'courseid' => $courseid,
            'itemtype' => 'category',
            'idnumber' => $idnumber,
        ]);
        if ($item) {
            $category = \grade_category::fetch(['id' => $item->iteminstance]);
            $category->fullname = $fullname;
            $category->aggregation = GRADE_AGGREGATE_WEIGHTED_MEAN;
            $category->update('local_tenantmaster');
            return (int)$category->id;
        }
        $category = new \grade_category();
        $category->courseid = $courseid;
        $category->fullname = $fullname;
        $category->aggregation = GRADE_AGGREGATE_WEIGHTED_MEAN;
        $categoryid = (int)$category->insert('local_tenantmaster');
        $item = $category->get_grade_item();
        $item->idnumber = $idnumber;
        $item->update('local_tenantmaster');
        return $categoryid;
    }

    /**
     * Ensure a native manual grade item.
     *
     * @param int $courseid Course.
     * @param string $idnumber ID number.
     * @param string $name Name.
     * @param float $grademax Maximum.
     * @param float $gradepass Pass.
     * @return int
     */
    private function ensure_grade_item(
        int $courseid,
        string $idnumber,
        string $name,
        float $grademax,
        float $gradepass,
    ): int {
        global $DB;

        $item = $DB->get_record('grade_items', [
            'courseid' => $courseid,
            'itemtype' => 'local',
            'itemmodule' => 'tenantmaster',
            'idnumber' => $idnumber,
        ]);
        $details = [
            'itemname' => $name,
            'idnumber' => $idnumber,
            'gradetype' => GRADE_TYPE_VALUE,
            'grademin' => 0,
            'grademax' => $grademax,
            'gradepass' => $gradepass,
        ];
        if ($item) {
            $details['id'] = $item->id;
        }
        grade_update(
            'local_tenantmaster',
            $courseid,
            'local',
            'tenantmaster',
            0,
            0,
            null,
            $details,
        );
        return (int)$DB->get_field('grade_items', 'id', [
            'courseid' => $courseid,
            'itemtype' => 'local',
            'itemmodule' => 'tenantmaster',
            'idnumber' => $idnumber,
        ]) ?: (int)$DB->get_field('grade_items', 'id', [
            'courseid' => $courseid,
            'idnumber' => $idnumber,
        ], MUST_EXIST);
    }

    /**
     * Active policy.
     *
     * @param int $tenantid Tenant.
     * @param string $type Type.
     * @return object|null
     */
    private function policy(int $tenantid, string $type): ?object {
        global $DB;
        $record = $DB->get_record('local_tenantmaster_master', [
            'tenantid' => $tenantid,
            'mastertype' => $type,
            'active' => 1,
        ], '*', IGNORE_MULTIPLE);
        return $record ?: null;
    }
}
