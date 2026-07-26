<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\local;

/**
 * Native-first Tenant Master initialisation.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class onboarding_service {
    /**
     * Adopt an existing native IOMAD company without creating a duplicate.
     *
     * Existing Tenant Master configuration is preserved. Defaults are adopted
     * only for a new or previously uninitialised tenant profile.
     *
     * @param int $companyid Native company ID.
     * @param string $tenanttype Tenant type.
     * @return object Tenant.
     */
    public function adopt_existing(int $companyid, string $tenanttype): object {
        global $DB;

        if (!array_key_exists($tenanttype, catalog::TENANT_TYPES)) {
            throw new \invalid_parameter_exception('Invalid tenant type.');
        }
        $company = $DB->get_record('local_iomad_companies', ['id' => $companyid, 'suspended' => 0]);
        if (!$company) {
            throw new \invalid_parameter_exception('The selected company is unavailable.');
        }
        if (!catalog::valid_external_key(trim((string)$company->code))) {
            throw new \invalid_parameter_exception(
                'Configure a stable company code in native IOMAD before initialising Tenant Master.',
            );
        }

        $transaction = $DB->start_delegated_transaction();
        $repository = new tenant_repository();
        $existing = $repository->get_by_company($companyid);
        $tenant = $existing ?? $repository->ensure_for_company($companyid, $tenanttype);
        (new role_service())->ensure_defaults((int)$tenant->id);

        if (!$existing || empty($tenant->defaultversion)) {
            (new default_service())->adopt($tenant);
            (new audit_service())->record(
                (int)$tenant->id,
                'tenant.existing_company.adopted',
                'success',
                ['tenanttype' => $tenant->tenanttype],
                ['entitytable' => 'local_tenantmaster_tenant', 'entityid' => (int)$tenant->id],
            );
        }

        $transaction->allow_commit();
        return $repository->get((int)$tenant->id);
    }
}
