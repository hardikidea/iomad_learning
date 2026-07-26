<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\local;

/**
 * Version-isolated IOMAD projection adapter.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface projection_adapter {
    /**
     * Read and link the authoritative native company without changing it.
     *
     * @param object $tenant Tenant.
     * @return projection_result
     */
    public function project_tenant(object $tenant): projection_result;

    /**
     * Project an academic master to a native record when applicable.
     *
     * @param object $tenant Tenant.
     * @param object $master Master.
     * @param string $module Dependency module.
     * @return projection_result|null
     */
    public function project_master(object $tenant, object $master, string $module): ?projection_result;

    /**
     * Project an academic year to a native course category.
     *
     * @param object $tenant Tenant.
     * @param object $academicyear Academic year.
     * @return projection_result
     */
    public function project_academic_year(object $tenant, object $academicyear): projection_result;

    /**
     * Read current managed native values for a mapping.
     *
     * @param object $mapping Mapping.
     * @return array<string, mixed>|null
     */
    public function read_mapping(object $mapping): ?array;
}
