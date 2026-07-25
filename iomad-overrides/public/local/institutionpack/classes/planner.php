<?php
// This file is part of IOMAD - http://www.iomad.org/

namespace local_institutionpack;

defined('MOODLE_INTERNAL') || die();

class planner {
    private pack $pack;

    public function __construct(pack $pack) {
        $this->pack = $pack;
    }

    public function plan(): array {
        global $DB;

        $plan = [
            'pack_id' => $this->pack->id(),
            'checksums' => $this->pack->checksums(),
            'entities' => [],
        ];

        $lookups = [
            'companies' => ['local_iomad_companies', 'shortname', 'company_shortname'],
            'departments' => ['local_iomad_company_departments', 'shortname', 'department_shortname'],
            'categories' => ['course_categories', 'idnumber', 'category_idnumber'],
            'courses' => ['course', 'shortname', 'course_shortname'],
            'users' => ['user', 'idnumber', 'user_external_id'],
            'cohorts' => ['cohort', 'idnumber', 'cohort_idnumber'],
        ];

        foreach (pack::ENTITIES as $entity) {
            $rows = $this->pack->rows($entity);
            $summary = ['rows' => count($rows), 'create' => 0, 'update' => 0, 'validate_only' => 0];
            foreach ($rows as $row) {
                if (!isset($lookups[$entity])) {
                    $summary['validate_only']++;
                    continue;
                }
                [$table, $field, $column] = $lookups[$entity];
                $value = $row[$column] ?? '';
                if ($value !== '' && $DB->record_exists($table, [$field => $value])) {
                    $summary['update']++;
                } else {
                    $summary['create']++;
                }
            }
            $plan['entities'][$entity] = $summary;
        }

        return $plan;
    }
}
