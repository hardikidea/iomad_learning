<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\local;

/**
 * Native IOMAD certificate activity projection.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class certificate_service {
    /**
     * Ensure one managed IOMAD certificate activity in a tenant course.
     *
     * @param object $tenant Tenant.
     * @param int $courseid Course.
     * @return int Certificate instance ID.
     */
    public function ensure(object $tenant, int $courseid): int {
        global $CFG, $DB, $USER;

        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->dirroot . '/mod/iomadcertificate/lib.php');
        if (
            !$DB->record_exists('local_iomad_company_courses', [
                'companyid' => $tenant->companyid,
                'courseid' => $courseid,
            ])
        ) {
            throw new \invalid_parameter_exception('Course belongs to another tenant.');
        }
        $policy = $DB->get_record('local_tenantmaster_master', [
            'tenantid' => $tenant->id,
            'mastertype' => 'certificate_rule',
            'active' => 1,
        ], '*', IGNORE_MULTIPLE);
        $config = $policy ? json::decode_object($policy->payloadjson) : [];
        $cmidnumber = $this->cm_key($tenant, $courseid);
        $existing = $DB->get_record_sql(
            "SELECT cert.*, cm.id AS cmid
               FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module AND m.name = :modulename
               JOIN {iomadcertificate} cert ON cert.id = cm.instance
              WHERE cm.course = :courseid
                AND cm.idnumber = :idnumber",
            ['modulename' => 'iomadcertificate', 'courseid' => $courseid, 'idnumber' => $cmidnumber],
        );
        $data = (object)[
            'course' => $courseid,
            'name' => (string)($config['name'] ?? get_string('defaultcertificatename', 'local_tenantmaster')),
            'intro' => (string)($config['intro'] ?? ''),
            'introformat' => FORMAT_HTML,
            'emailteachers' => (int)($config['emailteachers'] ?? 0),
            'emailothers' => '',
            'savecert' => (int)($config['savecertificate'] ?? 1),
            'reportcert' => 1,
            'delivery' => 0,
            'requiredtime' => (int)($config['requiredtime'] ?? 0),
            'iomadcertificatetype' => 'A4_non_embedded',
            'orientation' => 'L',
            'borderstyle' => '0',
            'bordercolor' => '0',
            'printwmark' => '0',
            'printdate' => 0,
            'datefmt' => 3,
            'printnumber' => 1,
            'printgrade' => 0,
            'gradefmt' => 1,
            'printoutcome' => 0,
            'printhours' => '',
            'printteacher' => 0,
            'customtext' => '',
            'printsignature' => '0',
            'printseal' => '0',
        ];
        if ($existing) {
            $data->instance = $existing->id;
            iomadcertificate_update_instance($data);
            $instanceid = (int)$existing->id;
        } else {
            $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
            $originaluser = $USER;
            try {
                $USER = get_admin();
                [, , , , $moduleinfo] = prepare_new_moduleinfo_data($course, 'iomadcertificate', 0);
                foreach ((array)$data as $field => $value) {
                    $moduleinfo->{$field} = $value;
                }
                $moduleinfo->cmidnumber = $cmidnumber;
                $created = add_moduleinfo($moduleinfo, $course);
            } finally {
                $USER = $originaluser;
            }
            $instanceid = (int)$created->instance;
        }
        $native = $DB->get_record('iomadcertificate', ['id' => $instanceid], '*', MUST_EXIST);
        $component = 'mod_iomadcertificate/certificate';
        $desired = field_ownership::select($component, $data);
        $actual = field_ownership::select($component, $native);
        (new mapping_repository())->save(
            (int)$tenant->id,
            (int)($policy->id ?? 0),
            new projection_result(
                $component,
                $cmidnumber,
                $instanceid,
                field_ownership::for_component($component),
                $desired,
                $actual,
            ),
        );
        (new audit_service())->record(
            (int)$tenant->id,
            'certificate.activity.synchronized',
            'success',
            ['courseid' => $courseid],
            [
                'entitytable' => 'course',
                'entityid' => $courseid,
                'targetcomponent' => $component,
                'targetid' => $instanceid,
            ],
        );
        return $instanceid;
    }

    /**
     * Bounded course-module ID number.
     *
     * @param object $tenant Tenant.
     * @param int $courseid Course.
     * @return string
     */
    private function cm_key(object $tenant, int $courseid): string {
        $key = 'TM:' . $tenant->trustcode . ':CERTIFICATE:' . $courseid;
        return strlen($key) <= 100
            ? $key
            : substr($key, 0, 67) . ':' . substr(hash('sha256', $key), 0, 32);
    }
}
