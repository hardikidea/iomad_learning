<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\local;

/**
 * Managed native fields for each projection component.
 *
 * Fields not listed here are never overwritten by Tenant Master.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class field_ownership {
    /** @var array<string, string[]> */
    private const FIELDS = [
        // IOMAD is authoritative for all native company fields.
        'local_iomad/company' => [],
        'local_iomad/department' => [
            'name',
            'shortname',
            'parentid',
        ],
        'core_course/category' => [
            'name',
            'idnumber',
            'description',
            'descriptionformat',
            'parent',
            'visible',
        ],
        'core/course' => [
            'fullname',
            'shortname',
            'idnumber',
            'summary',
            'summaryformat',
            'category',
            'visible',
            'format',
            'startdate',
            'enddate',
        ],
        'core/user' => [
            'username',
            'idnumber',
            'firstname',
            'lastname',
            'email',
            'auth',
            'suspended',
        ],
        'core/cohort' => [
            'name',
            'idnumber',
            'description',
            'descriptionformat',
            'visible',
        ],
        'core/group' => [
            'name',
            'idnumber',
            'description',
            'descriptionformat',
        ],
        'core/grade' => [
            'fullname',
            'idnumber',
            'aggregation',
            'grademax',
            'grademin',
            'gradepass',
        ],
        'mod_iomadcertificate/certificate' => [
            'name',
            'intro',
            'requiredtime',
            'emailteachers',
            'savecert',
        ],
    ];

    /**
     * Get managed fields.
     *
     * @param string $component Component.
     * @return string[]
     */
    public static function for_component(string $component): array {
        return self::FIELDS[$component] ?? [];
    }

    /**
     * Select managed fields from a record.
     *
     * @param string $component Component.
     * @param object|array<string, mixed> $record Record.
     * @return array<string, mixed>
     */
    public static function select(string $component, object|array $record): array {
        $record = (array)$record;
        $selected = [];
        foreach (self::for_component($component) as $field) {
            if (array_key_exists($field, $record)) {
                $selected[$field] = $record[$field];
            }
        }
        return $selected;
    }
}
