<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\local;

use local_iomad\company;

/**
 * Native IOMAD department application service.
 *
 * Departments are never duplicated in Tenant Master tables.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class organisation_service {
    /**
     * List native departments.
     *
     * @param object $tenant Tenant.
     * @return array<int, object>
     */
    public function list(object $tenant): array {
        global $DB;

        return $DB->get_records(
            'local_iomad_company_departments',
            ['companyid' => $tenant->companyid],
            'parentid ASC, name ASC',
        );
    }

    /**
     * Save through the IOMAD department API and record a mapping.
     *
     * @param object $tenant Tenant.
     * @param object $data Data.
     * @return object Native department.
     */
    public function save(object $tenant, object $data): object {
        global $DB;

        $departmentid = (int)($data->id ?? 0);
        $current = null;
        if ($departmentid > 0) {
            $current = $DB->get_record('local_iomad_company_departments', [
                'id' => $departmentid,
                'companyid' => (int)$tenant->companyid,
            ], '*', MUST_EXIST);
            if ($current->shortname !== (string)$data->shortname) {
                throw new \invalid_parameter_exception(
                    'Department shortname cannot change after creation.'
                );
            }
        }
        $parentid = (int)($data->parentid ?? 0);
        if (
            $parentid > 0 && !$DB->record_exists('local_iomad_company_departments', [
                'id' => $parentid,
                'companyid' => $tenant->companyid,
            ])
        ) {
            throw new \invalid_parameter_exception('Department parent belongs to another tenant.');
        }
        if (!catalog::valid_external_key((string)$data->shortname)) {
            throw new \invalid_parameter_exception('Invalid department shortname.');
        }
        $this->require_acyclic_parent((int)$tenant->companyid, $departmentid, $parentid);
        company::create_department(
            $departmentid,
            (int)$tenant->companyid,
            (string)$data->name,
            (string)$data->shortname,
            $parentid,
        );
        $department = $departmentid > 0
            ? $DB->get_record('local_iomad_company_departments', [
                'id' => $departmentid,
                'companyid' => $tenant->companyid,
            ], '*', MUST_EXIST)
            : $DB->get_record('local_iomad_company_departments', [
                'companyid' => $tenant->companyid,
                'shortname' => $data->shortname,
            ], '*', MUST_EXIST);
        $component = 'local_iomad/department';
        $desired = field_ownership::select($component, $department);
        $result = new projection_result(
            $component,
            'TM:' . $tenant->trustcode . ':DEPARTMENT:' . $data->shortname,
            (int)$department->id,
            field_ownership::for_component($component),
            $desired,
            $desired,
        );
        (new mapping_repository())->save((int)$tenant->id, 0, $result);
        (new queue_service())->mark_dirty(
            (int)$tenant->id,
            'organisation',
            'local_tenantmaster_tenant',
            (int)$tenant->id,
            'department_saved',
        );
        (new audit_service())->record(
            (int)$tenant->id,
            'organisation.department.saved',
            'success',
            ['shortname' => $data->shortname],
            [
                'entitytable' => 'local_iomad_company_departments',
                'entityid' => (int)$department->id,
                'targetcomponent' => $component,
                'targetid' => (int)$department->id,
            ],
        );
        return $department;
    }

    /**
     * Reject department parent cycles before calling the native IOMAD API.
     *
     * @param int $companyid Native company.
     * @param int $departmentid Department being edited.
     * @param int $parentid Requested parent.
     */
    private function require_acyclic_parent(int $companyid, int $departmentid, int $parentid): void {
        global $DB;

        $visited = [];
        while ($parentid > 0) {
            if ($parentid === $departmentid || isset($visited[$parentid])) {
                throw new \invalid_parameter_exception('Department hierarchy cannot contain a cycle.');
            }
            $visited[$parentid] = true;
            $parent = $DB->get_record('local_iomad_company_departments', [
                'id' => $parentid,
                'companyid' => $companyid,
            ], '*', MUST_EXIST);
            $parentid = (int)$parent->parentid;
        }
    }
}
