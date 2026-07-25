<?php
// This file is part of IOMAD - http://www.iomad.org/

namespace local_institutionpack;

defined('MOODLE_INTERNAL') || die();

class validator {
    private pack $pack;
    private array $errors = [];
    private array $warnings = [];
    private array $counts = [];

    private const REQUIRED_COLUMNS = [
        'institutions' => ['institution_id', 'name', 'type', 'country', 'timezone'],
        'companies' => ['institution_id', 'company_shortname', 'name', 'city', 'country'],
        'domains' => ['company_shortname', 'domain'],
        'departments' => ['company_shortname', 'department_shortname', 'name'],
        'categories' => ['category_idnumber', 'name'],
        'courses' => ['course_shortname', 'fullname', 'category_idnumber', 'company_shortname'],
        'users' => ['user_external_id', 'username', 'firstname', 'lastname', 'email', 'company_shortname', 'role_key'],
        'roles' => ['role_key', 'role_shortname', 'context', 'capabilities'],
        'cohorts' => ['cohort_idnumber', 'name', 'company_shortname'],
        'groups' => ['course_shortname', 'group_idnumber', 'name'],
        'enrolments' => ['user_external_id', 'course_shortname', 'role_shortname', 'company_shortname'],
        'parent_links' => ['parent_user_external_id', 'learner_user_external_id'],
        'policies' => ['policy_key', 'company_shortname', 'name', 'audience', 'content'],
        'licenses' => ['license_key', 'company_shortname', 'name', 'allocation', 'course_shortnames'],
        'branding' => ['company_shortname', 'maincolor', 'headingcolor', 'linkcolor'],
    ];

    public function __construct(pack $pack) {
        $this->pack = $pack;
    }

    public function validate(): array {
        $this->errors = [];
        $this->warnings = [];
        $this->counts = [];

        if ($this->pack->schema_version() !== 1) {
            $this->errors[] = 'manifest.yml must declare schema_version: 1';
        }

        $indexes = [];
        foreach (pack::ENTITIES as $entity) {
            $rows = $this->pack->rows($entity);
            $this->counts[$entity] = count($rows);
            $this->check_columns($entity, $rows);
            $indexes[$entity] = $this->index_entity($entity, $rows);
        }

        $this->check_foreign_keys($indexes);
        $this->check_password_policy();

        return [
            'ok' => empty($this->errors),
            'pack_id' => $this->pack->id(),
            'schema_version' => $this->pack->schema_version(),
            'counts' => $this->counts,
            'checksums' => $this->pack->checksums(),
            'errors' => $this->errors,
            'warnings' => $this->warnings,
        ];
    }

    private function check_columns(string $entity, array $rows): void {
        if (empty($rows) || !isset(self::REQUIRED_COLUMNS[$entity])) {
            return;
        }
        $available = array_keys($rows[0]);
        foreach (self::REQUIRED_COLUMNS[$entity] as $column) {
            if (!in_array($column, $available, true)) {
                $this->errors[] = "{$entity}.csv is missing required column {$column}";
            }
        }
    }

    private function index_entity(string $entity, array $rows): array {
        $key = match ($entity) {
            'institutions' => 'institution_id',
            'companies' => 'company_shortname',
            'departments' => 'department_shortname',
            'categories' => 'category_idnumber',
            'courses' => 'course_shortname',
            'users' => 'user_external_id',
            'roles' => 'role_key',
            'cohorts' => 'cohort_idnumber',
            'groups' => 'group_idnumber',
            'policies' => 'policy_key',
            'licenses' => 'license_key',
            default => '',
        };
        if ($key === '') {
            return [];
        }

        $index = [];
        foreach ($rows as $row) {
            $value = $row[$key] ?? '';
            if ($value === '') {
                $this->errors[] = "{$entity}.csv line {$row['_line']} has empty {$key}";
                continue;
            }
            if (isset($index[$value])) {
                $this->errors[] = "{$entity}.csv has duplicate {$key}: {$value}";
            }
            $index[$value] = $row;
        }
        return $index;
    }

    private function check_foreign_keys(array $indexes): void {
        foreach ($this->pack->rows('companies') as $row) {
            $institution = $row['institution_id'] ?? '';
            if ($institution !== '' && !isset($indexes['institutions'][$institution])) {
                $this->errors[] = "companies.csv line {$row['_line']} references missing institution {$institution}";
            }
            $parent = $row['parent_company_shortname'] ?? '';
            if ($parent !== '' && !isset($indexes['companies'][$parent])) {
                $this->errors[] = "companies.csv line {$row['_line']} references missing parent company {$parent}";
            }
        }

        foreach ($this->pack->rows('departments') as $row) {
            $company = $row['company_shortname'] ?? '';
            if ($company !== '' && !isset($indexes['companies'][$company])) {
                $this->errors[] = "departments.csv line {$row['_line']} references missing company {$company}";
            }
        }

        foreach ($this->pack->rows('courses') as $row) {
            $category = $row['category_idnumber'] ?? '';
            $company = $row['company_shortname'] ?? '';
            if ($category !== '' && !isset($indexes['categories'][$category])) {
                $this->errors[] = "courses.csv line {$row['_line']} references missing category {$category}";
            }
            if ($company !== '' && !isset($indexes['companies'][$company])) {
                $this->errors[] = "courses.csv line {$row['_line']} references missing company {$company}";
            }
        }

        foreach ($this->pack->rows('users') as $row) {
            $company = $row['company_shortname'] ?? '';
            $rolekey = $row['role_key'] ?? '';
            if ($company !== '' && !isset($indexes['companies'][$company])) {
                $this->errors[] = "users.csv line {$row['_line']} references missing company {$company}";
            }
            if ($rolekey !== '' && !isset($indexes['roles'][$rolekey])) {
                $this->errors[] = "users.csv line {$row['_line']} references missing role_key {$rolekey}";
            }
        }

        foreach ($this->pack->rows('enrolments') as $row) {
            $user = $row['user_external_id'] ?? '';
            $course = $row['course_shortname'] ?? '';
            if ($user !== '' && !isset($indexes['users'][$user])) {
                $this->errors[] = "enrolments.csv line {$row['_line']} references missing user {$user}";
            }
            if ($course !== '' && !isset($indexes['courses'][$course])) {
                $this->errors[] = "enrolments.csv line {$row['_line']} references missing course {$course}";
            }
        }

        foreach ($this->pack->rows('parent_links') as $row) {
            foreach (['parent_user_external_id', 'learner_user_external_id'] as $column) {
                $user = $row[$column] ?? '';
                if ($user !== '' && !isset($indexes['users'][$user])) {
                    $this->errors[] = "parent_links.csv line {$row['_line']} references missing user {$user}";
                }
            }
        }
    }

    private function check_password_policy(): void {
        $allow = (getenv('INSTITUTIONPACK_ALLOW_DEMO_PASSWORDS') ?: 'false') === 'true';
        foreach ($this->pack->rows('users') as $row) {
            if (!empty($row['password']) && !$allow) {
                $this->errors[] = "users.csv line {$row['_line']} includes a password but demo passwords are disabled";
            }
        }
    }
}
