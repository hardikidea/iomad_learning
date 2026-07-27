<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\local;

/**
 * Canonical Tenant Master import schema and operator examples.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class import_schema {
    /** Current package schema version. */
    public const VERSION = '1.0';

    /** @var string[] Columns that must never enter this import pipeline. */
    private const FORBIDDEN_COLUMNS = [
        'password',
        'newpassword',
        'token',
        'secret',
        'firstname',
        'lastname',
        'email',
        'phone',
        'address',
    ];

    /**
     * Supported entities, columns, examples and native outcomes.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function entities(): array {
        return [
            'academic_years' => [
                'required' => ['externalid', 'code', 'name', 'startdate', 'enddate', 'iscurrent'],
                'optional' => ['status'],
                'example' => [
                    'externalid' => 'AY_2026_27',
                    'code' => '2026_27',
                    'name' => 'Academic Year 2026-27',
                    'startdate' => '2026-04-01',
                    'enddate' => '2027-03-31',
                    'iscurrent' => '0',
                    'status' => 'draft',
                ],
                'resultkey' => 'importresult_academic_years',
            ],
            'academic_masters' => [
                'required' => ['mastertype', 'externalid', 'code', 'name'],
                'optional' => [
                    'parent_externalid',
                    'description',
                    'configurationjson',
                    'active',
                    'sortorder',
                ],
                'example' => [
                    'mastertype' => 'subject',
                    'externalid' => 'SUB_MATHEMATICS',
                    'code' => 'MATH',
                    'name' => 'Mathematics',
                    'parent_externalid' => '',
                    'description' => 'Mathematics subject master',
                    'configurationjson' => '{}',
                    'active' => '1',
                    'sortorder' => '10',
                ],
                'resultkey' => 'importresult_academic_masters',
            ],
            'departments' => [
                'required' => ['externalid', 'shortname', 'name', 'parent_shortname'],
                'optional' => [],
                'example' => [
                    'externalid' => 'DEPT_PRIMARY',
                    'shortname' => 'PRIMARY',
                    'name' => 'Primary School',
                    'parent_shortname' => '',
                ],
                'resultkey' => 'importresult_departments',
            ],
            'cohorts' => [
                'required' => ['externalid', 'name'],
                'optional' => ['description'],
                'example' => [
                    'externalid' => 'COHORT_2026_GRADE_06_A',
                    'name' => '2026-27 Grade 6 Division A',
                    'description' => 'Academic class cohort',
                ],
                'resultkey' => 'importresult_cohorts',
            ],
            'cohort_members' => [
                'required' => ['cohort_externalid', 'user_externalid'],
                'optional' => [],
                'example' => [
                    'cohort_externalid' => 'COHORT_2026_GRADE_06_A',
                    'user_externalid' => 'STUDENT_0001',
                ],
                'resultkey' => 'importresult_cohort_members',
            ],
            'groups' => [
                'required' => ['externalid', 'name', 'course_idnumber'],
                'optional' => [],
                'example' => [
                    'externalid' => 'GROUP_2026_GRADE_06_A',
                    'name' => 'Grade 6 Division A',
                    'course_idnumber' => 'COURSE_2026_GRADE_06_MATH',
                ],
                'resultkey' => 'importresult_groups',
            ],
            'group_members' => [
                'required' => ['group_externalid', 'course_idnumber', 'user_externalid'],
                'optional' => [],
                'example' => [
                    'group_externalid' => 'GROUP_2026_GRADE_06_A',
                    'course_idnumber' => 'COURSE_2026_GRADE_06_MATH',
                    'user_externalid' => 'STUDENT_0001',
                ],
                'resultkey' => 'importresult_group_members',
            ],
            'user_roles' => [
                'required' => ['user_externalid', 'rolekey', 'department_shortname', 'course_idnumber'],
                'optional' => [],
                'example' => [
                    'user_externalid' => 'TEACHER_0001',
                    'rolekey' => 'teacher_faculty',
                    'department_shortname' => 'PRIMARY',
                    'course_idnumber' => 'COURSE_2026_GRADE_06_MATH',
                ],
                'resultkey' => 'importresult_user_roles',
            ],
            'guardian_links' => [
                'required' => ['guardian_externalid', 'learner_externalid'],
                'optional' => [],
                'example' => [
                    'guardian_externalid' => 'PARENT_0001',
                    'learner_externalid' => 'STUDENT_0001',
                ],
                'resultkey' => 'importresult_guardian_links',
            ],
        ];
    }

    /**
     * Get one entity definition.
     *
     * @param string $entity Entity key.
     * @return array<string, mixed>
     */
    public static function entity(string $entity): array {
        $entities = self::entities();
        if (!isset($entities[$entity])) {
            throw new \invalid_parameter_exception('Unsupported import entity: ' . $entity);
        }
        return $entities[$entity];
    }

    /**
     * Required columns for one entity.
     *
     * @param string $entity Entity key.
     * @return string[]
     */
    public static function required_columns(string $entity): array {
        return self::entity($entity)['required'];
    }

    /**
     * All supported columns for one entity.
     *
     * @param string $entity Entity key.
     * @return string[]
     */
    public static function columns(string $entity): array {
        $definition = self::entity($entity);
        return array_merge($definition['required'], $definition['optional']);
    }

    /**
     * Sanitized example row for one entity.
     *
     * @param string $entity Entity key.
     * @return array<string, string>
     */
    public static function example(string $entity): array {
        return self::entity($entity)['example'];
    }

    /**
     * Whether the entity is accepted.
     *
     * @param string $entity Entity key.
     * @return bool
     */
    public static function supports(string $entity): bool {
        return isset(self::entities()[$entity]);
    }

    /**
     * Sensitive columns rejected before normalization.
     *
     * @return string[]
     */
    public static function forbidden_columns(): array {
        return self::FORBIDDEN_COLUMNS;
    }

    /**
     * Operator guidance for a field.
     *
     * @param string $field Field.
     * @return string
     */
    public static function field_note(string $field): string {
        return match ($field) {
            'externalid', 'cohort_externalid', 'group_externalid',
                'guardian_externalid', 'learner_externalid' =>
                'Stable external key: letters, numbers, dot, underscore, colon or hyphen; maximum 100 characters.',
            'user_externalid' =>
                'Existing Moodle user idnumber assigned to the selected IOMAD company.',
            'course_idnumber' =>
                'Existing Moodle course idnumber assigned to the selected IOMAD company.',
            'parent_externalid' =>
                'Existing or same-package academic master externalid; leave blank for a root item.',
            'parent_shortname' =>
                'Existing or same-package department shortname; leave blank for the company root.',
            'mastertype' =>
                'One supported Tenant Master master type, such as grade, subject, stream or programme.',
            'rolekey' =>
                'One configured business role key, such as teacher_faculty or student_learner.',
            'startdate', 'enddate' => 'ISO date in YYYY-MM-DD format.',
            'iscurrent', 'active' => 'Boolean integer: 1 for yes, 0 for no.',
            'configurationjson' => 'Valid JSON object; use {} when no configuration is required.',
            'status' => 'Lifecycle value; use draft or active for academic years.',
            'sortorder' => 'Whole number used for display ordering.',
            'department_shortname' =>
                'Existing company department shortname; leave blank only when the selected role permits company scope.',
            'code', 'shortname' => 'Stable human-readable code; do not reuse it for another record.',
            default => 'Plain UTF-8 text. Keep values tenant-scoped and free of sensitive personal data.',
        };
    }
}
