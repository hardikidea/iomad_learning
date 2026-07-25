<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\local;

use core_course_category;
use local_iomad\company;

/**
 * Supported native adapter for pinned IOMAD 5.1.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class iomad_501_adapter implements projection_adapter {
    /** @var string[] Academic types represented as native course categories. */
    private const CATEGORY_TYPES = [
        'board',
        'medium',
        'grade',
        'programme',
        'semester',
        'stream',
        'specialisation',
        'division',
    ];

    /**
     * Constructor.
     *
     * @param mapping_repository $mappings Mapping repository.
     */
    public function __construct(
        private readonly mapping_repository $mappings = new mapping_repository(),
    ) {
    }

    /**
     * Project tenant profile using the supported IOMAD company API.
     *
     * @param object $tenant Tenant.
     * @return projection_result
     */
    public function project_tenant(object $tenant): projection_result {
        global $CFG, $DB, $USER;

        $component = 'local_iomad/company';
        $native = $DB->get_record('local_iomad_companies', ['id' => $tenant->companyid], '*', MUST_EXIST);
        $profile = json::decode_object($tenant->profilejson);
        $managed = field_ownership::for_component($component);
        $desiredrecord = clone $native;
        $desiredrecord->code = $tenant->trustcode;
        foreach ($managed as $field) {
            if ($field === 'code') {
                continue;
            }
            if (array_key_exists($field, $profile)) {
                $desiredrecord->{$field} = $profile[$field];
            }
        }

        // The public IOMAD web-service API performs validation and emits the
        // company_updated event. Background projection runs as the service
        // administrator only after the initiating user passed tenant capability
        // checks in the application service.
        require_once($CFG->dirroot . '/blocks/iomad_company_admin/externallib.php');
        $payload = ['id' => (int)$tenant->companyid];
        foreach ($managed as $field) {
            if (property_exists($desiredrecord, $field) && $desiredrecord->{$field} !== null) {
                $payload[$field] = $desiredrecord->{$field};
            }
        }
        $originaluser = $USER;
        try {
            $USER = get_admin();
            \block_iomad_company_admin_external::edit_companies([$payload]);
        } finally {
            $USER = $originaluser;
        }
        $readback = $DB->get_record('local_iomad_companies', ['id' => $tenant->companyid], '*', MUST_EXIST);
        $desired = field_ownership::select($component, $desiredrecord);
        $actual = field_ownership::select($component, $readback);
        $this->assert_readback($component, $desired, $actual);

        return new projection_result(
            $component,
            (string)$tenant->trustcode,
            (int)$tenant->companyid,
            $managed,
            $desired,
            $actual,
        );
    }

    /**
     * Project an academic master.
     *
     * @param object $tenant Tenant.
     * @param object $master Master.
     * @param string $module Module.
     * @return projection_result|null
     */
    public function project_master(object $tenant, object $master, string $module): ?projection_result {
        if ($module === 'categories' && in_array($master->mastertype, self::CATEGORY_TYPES, true)) {
            return $this->project_category($tenant, $master);
        }
        if ($module === 'courses' && in_array($master->mastertype, ['subject', 'course_template'], true)) {
            return $this->project_course($tenant, $master);
        }
        return null;
    }

    /**
     * Project an academic year as the root of the tenant academic hierarchy.
     *
     * @param object $tenant Tenant.
     * @param object $academicyear Academic year.
     * @return projection_result
     */
    public function project_academic_year(object $tenant, object $academicyear): projection_result {
        global $DB;

        $component = 'core_course/category';
        $externalkey = $this->academic_year_key($tenant, $academicyear);
        $desired = [
            'name' => (string)$academicyear->name,
            'idnumber' => $externalkey,
            'description' => '',
            'descriptionformat' => FORMAT_PLAIN,
            'parent' => (int)$DB->get_field(
                'local_iomad_companies',
                'coursecategoryid',
                ['id' => $tenant->companyid],
                MUST_EXIST,
            ),
            'visible' => $academicyear->status === 'active' ? 1 : 0,
        ];
        $mapping = $this->mappings->find((int)$tenant->id, $component, $externalkey);
        $targetid = (int)($mapping->targetid ?? 0);
        if ($targetid <= 0) {
            $targetid = (int)$DB->get_field('course_categories', 'id', ['idnumber' => $externalkey]);
        }
        if ($targetid > 0) {
            core_course_category::get($targetid, MUST_EXIST, true)->update((object)$desired);
        } else {
            $targetid = (int)core_course_category::create((object)$desired)->id;
        }
        $readback = $DB->get_record('course_categories', ['id' => $targetid], '*', MUST_EXIST);
        $actual = field_ownership::select($component, $readback);
        $this->assert_readback($component, $desired, $actual);
        return new projection_result(
            $component,
            $externalkey,
            $targetid,
            field_ownership::for_component($component),
            $desired,
            $actual,
        );
    }

    /**
     * Read current managed values.
     *
     * @param object $mapping Mapping.
     * @return array<string, mixed>|null
     */
    public function read_mapping(object $mapping): ?array {
        global $DB;

        $record = match ($mapping->component) {
            'local_iomad/company' => $DB->get_record('local_iomad_companies', ['id' => $mapping->targetid]),
            'local_iomad/department' => $DB->get_record(
                'local_iomad_company_departments',
                ['id' => $mapping->targetid],
            ),
            'core_course/category' => $DB->get_record('course_categories', ['id' => $mapping->targetid]),
            'core/course' => $DB->get_record('course', ['id' => $mapping->targetid]),
            'core/cohort' => $DB->get_record('cohort', ['id' => $mapping->targetid]),
            'core/group' => $DB->get_record('groups', ['id' => $mapping->targetid]),
            'mod_iomadcertificate/certificate' => $DB->get_record(
                'iomadcertificate',
                ['id' => $mapping->targetid],
            ),
            default => false,
        };
        if (!$record) {
            return null;
        }
        return field_ownership::select((string)$mapping->component, $record);
    }

    /**
     * Project a native course category.
     *
     * @param object $tenant Tenant.
     * @param object $master Master.
     * @return projection_result
     */
    private function project_category(object $tenant, object $master): projection_result {
        global $DB;

        $component = 'core_course/category';
        $externalkey = $this->native_key($tenant, $master);
        $parentid = $this->resolve_parent_category($tenant, $master);
        $desired = [
            'name' => (string)$master->name,
            'idnumber' => $externalkey,
            'description' => (string)($master->description ?? ''),
            'descriptionformat' => FORMAT_PLAIN,
            'parent' => $parentid,
            'visible' => (int)$master->active,
        ];
        $mapping = $this->mappings->find((int)$tenant->id, $component, $externalkey);
        $targetid = (int)($mapping->targetid ?? 0);
        if ($targetid <= 0) {
            $targetid = (int)$DB->get_field('course_categories', 'id', ['idnumber' => $externalkey]);
        }
        if ($targetid > 0) {
            $category = core_course_category::get($targetid, MUST_EXIST, true);
            $category->update((object)$desired);
        } else {
            $category = core_course_category::create((object)$desired);
            $targetid = (int)$category->id;
        }
        $readback = $DB->get_record('course_categories', ['id' => $targetid], '*', MUST_EXIST);
        $actual = field_ownership::select($component, $readback);
        $this->assert_readback($component, $desired, $actual);
        return new projection_result(
            $component,
            $externalkey,
            $targetid,
            field_ownership::for_component($component),
            $desired,
            $actual,
        );
    }

    /**
     * Project a native Moodle course and IOMAD company assignment.
     *
     * @param object $tenant Tenant.
     * @param object $master Master.
     * @return projection_result
     */
    private function project_course(object $tenant, object $master): projection_result {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/course/lib.php');
        $component = 'core/course';
        $externalkey = $this->native_key($tenant, $master);
        $payload = json::decode_object($master->payloadjson);
        $desired = [
            'fullname' => (string)$master->name,
            'shortname' => $this->course_shortname($tenant, $master),
            'idnumber' => $externalkey,
            'summary' => (string)($master->description ?? ''),
            'summaryformat' => FORMAT_PLAIN,
            'category' => $this->resolve_parent_category($tenant, $master),
            'visible' => $master->mastertype === 'course_template' ? 0 : (int)$master->active,
            'format' => (string)($payload['format'] ?? 'topics'),
            'startdate' => (int)($payload['startdate'] ?? 0),
            'enddate' => (int)($payload['enddate'] ?? 0),
        ];
        $mapping = $this->mappings->find((int)$tenant->id, $component, $externalkey);
        $targetid = (int)($mapping->targetid ?? 0);
        if ($targetid <= 0) {
            $targetid = (int)$DB->get_field('course', 'id', ['idnumber' => $externalkey]);
        }
        if ($targetid > 0) {
            $desired['id'] = $targetid;
            update_course((object)$desired);
        } else {
            $course = create_course((object)$desired);
            $targetid = (int)$course->id;
        }

        $company = new company((int)$tenant->companyid);
        $department = company::get_company_parentnode((int)$tenant->companyid);
        $course = $DB->get_record('course', ['id' => $targetid], '*', MUST_EXIST);
        $company->add_course(
            $course,
            (int)$department->id,
            true,
            (bool)($payload['licensed'] ?? false),
        );

        $readback = $DB->get_record('course', ['id' => $targetid], '*', MUST_EXIST);
        $actual = field_ownership::select($component, $readback);
        unset($desired['id']);
        $this->assert_readback($component, $desired, $actual);
        return new projection_result(
            $component,
            $externalkey,
            $targetid,
            field_ownership::for_component($component),
            $desired,
            $actual,
        );
    }

    /**
     * Resolve a mapped parent, falling back to the company's native category.
     *
     * @param object $tenant Tenant.
     * @param object $master Master.
     * @return int
     */
    private function resolve_parent_category(object $tenant, object $master): int {
        global $DB;

        if ((int)$master->parentid > 0) {
            $mapping = $this->mappings->find_for_master(
                (int)$tenant->id,
                (int)$master->parentid,
                'core_course/category',
            );
            if ($mapping && (int)$mapping->targetid > 0) {
                return (int)$mapping->targetid;
            }
        }
        $academicyearid = (int)($master->acadyearid ?? 0) ?: (int)$tenant->activeyearid;
        if ($academicyearid > 0) {
            $academicyear = $DB->get_record('local_tenantmaster_acadyear', [
                'id' => $academicyearid,
                'tenantid' => $tenant->id,
            ]);
            if ($academicyear) {
                $mapping = $this->mappings->find(
                    (int)$tenant->id,
                    'core_course/category',
                    $this->academic_year_key($tenant, $academicyear),
                );
                if ($mapping && (int)$mapping->targetid > 0) {
                    return (int)$mapping->targetid;
                }
            }
        }
        return (int)$DB->get_field(
            'local_iomad_companies',
            'coursecategoryid',
            ['id' => $tenant->companyid],
            MUST_EXIST,
        );
    }

    /**
     * Stable native idnumber with bounded length.
     *
     * @param object $tenant Tenant.
     * @param object $master Master.
     * @return string
     */
    private function native_key(object $tenant, object $master): string {
        $key = 'TM:' . $tenant->trustcode . ':' . strtoupper($master->mastertype) . ':' . $master->externalid;
        if (strlen($key) <= 100) {
            return $key;
        }
        return substr($key, 0, 67) . ':' . substr(hash('sha256', $key), 0, 32);
    }

    /**
     * Stable academic-year category key.
     *
     * @param object $tenant Tenant.
     * @param object $academicyear Academic year.
     * @return string
     */
    private function academic_year_key(object $tenant, object $academicyear): string {
        $key = 'TM:' . $tenant->trustcode . ':ACADEMIC_YEAR:' . $academicyear->externalid;
        return strlen($key) <= 100
            ? $key
            : substr($key, 0, 67) . ':' . substr(hash('sha256', $key), 0, 32);
    }

    /**
     * Stable and readable course shortname.
     *
     * @param object $tenant Tenant.
     * @param object $master Master.
     * @return string
     */
    private function course_shortname(object $tenant, object $master): string {
        $value = strtoupper($tenant->trustcode . '_' . $master->code);
        return substr(preg_replace('/[^A-Z0-9_-]/', '_', $value), 0, 255);
    }

    /**
     * Require exact managed-field readback.
     *
     * @param string $component Component.
     * @param array<string, mixed> $desired Desired.
     * @param array<string, mixed> $actual Actual.
     */
    private function assert_readback(string $component, array $desired, array $actual): void {
        foreach ($desired as $field => $value) {
            if (!array_key_exists($field, $actual)) {
                continue;
            }
            if ((string)$actual[$field] !== (string)$value) {
                throw new \moodle_exception(
                    'projectionreadbackfailed',
                    'local_tenantmaster',
                    '',
                    $component . ':' . $field,
                );
            }
        }
    }
}
