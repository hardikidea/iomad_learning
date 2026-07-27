<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\local;

/**
 * Stable Tenant Master catalogues.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class catalog {
    /** @var array<string, string> */
    public const TENANT_TYPES = [
        'school' => 'tenanttype_school',
        'university' => 'tenanttype_university',
        'college' => 'tenanttype_college',
        'training' => 'tenanttype_training',
    ];

    /** @var array<string, string> */
    public const MASTER_TYPES = [
        'board' => 'mastertype_board',
        'medium' => 'mastertype_medium',
        'grade' => 'mastertype_grade',
        'programme' => 'mastertype_programme',
        'semester' => 'mastertype_semester',
        'stream' => 'mastertype_stream',
        'specialisation' => 'mastertype_specialisation',
        'division' => 'mastertype_division',
        'subject' => 'mastertype_subject',
        'credit' => 'mastertype_credit',
        'course_template' => 'mastertype_course_template',
        'assessment_policy' => 'mastertype_assessment_policy',
        'attendance_policy' => 'mastertype_attendance_policy',
        'certificate_rule' => 'mastertype_certificate_rule',
        'progression_rule' => 'mastertype_progression_rule',
    ];

    /** @var string[] Master domains shared by every tenant type. */
    private const SHARED_MASTER_TYPES = [
        'course_template',
        'assessment_policy',
        'attendance_policy',
        'certificate_rule',
        'progression_rule',
    ];

    /** @var array<string, string> */
    public const ROLE_KEYS = [
        'principal_registrar' => 'role_principal_registrar',
        'trustee_management' => 'role_trustee_management',
        'it_coordinator' => 'role_it_coordinator',
        'teacher_faculty' => 'role_teacher_faculty',
        'student_learner' => 'role_student_learner',
        'parent_guardian' => 'role_parent_guardian',
        'hod_dean' => 'role_hod_dean',
    ];

    /** @var array<string, string> */
    public const MODULES = [
        'tenant' => 'module_tenant',
        'organisation' => 'module_organisation',
        'academic' => 'module_academic',
        'categories' => 'module_categories',
        'courses' => 'module_courses',
        'people' => 'module_people',
        'roles' => 'module_roles',
        'cohorts' => 'module_cohorts',
        'groups' => 'module_groups',
        'enrolments' => 'module_enrolments',
        'assessments' => 'module_assessments',
        'attendance' => 'module_attendance',
        'certificates' => 'module_certificates',
        'progression' => 'module_progression',
        'rollover' => 'module_rollover',
    ];

    /**
     * Localise a string-keyed catalogue.
     *
     * @param array<string, string> $items Items.
     * @return array<string, string>
     */
    public static function localise(array $items): array {
        return array_map(
            static fn(string $stringkey): string => get_string($stringkey, 'local_tenantmaster'),
            $items,
        );
    }

    /**
     * Master domains permitted for one tenant type.
     *
     * @param string $tenanttype Tenant type.
     * @return string[]
     */
    public static function master_types_for_tenant(string $tenanttype): array {
        $specific = match ($tenanttype) {
            'school' => ['board', 'medium', 'grade', 'stream', 'division', 'subject'],
            'university', 'college' => ['programme', 'semester', 'specialisation', 'credit', 'subject'],
            'training' => ['programme', 'credit', 'subject'],
            default => [],
        };
        return array_merge($specific, self::SHARED_MASTER_TYPES);
    }

    /**
     * Check that an external key is safe and stable.
     *
     * @param string $value Key.
     * @return bool
     */
    public static function valid_external_key(string $value): bool {
        return (bool)preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,99}$/', $value);
    }
}
