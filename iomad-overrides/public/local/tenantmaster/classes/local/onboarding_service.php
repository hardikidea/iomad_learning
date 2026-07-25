<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\local;

use local_iomad\company;

/**
 * Atomic native-company and Tenant Master onboarding.
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
        if (!$DB->record_exists('local_iomad_companies', ['id' => $companyid, 'suspended' => 0])) {
            throw new \invalid_parameter_exception('The selected company is unavailable.');
        }

        $transaction = $DB->start_delegated_transaction();
        $repository = new tenant_repository();
        $existing = $repository->get_by_company($companyid);
        $tenant = $existing ?? $repository->ensure_for_company($companyid, $tenanttype);
        (new role_service())->ensure_defaults((int)$tenant->id);

        if (!$existing || empty($tenant->defaultversion)) {
            (new default_service())->adopt($tenant);
            (new queue_service())->mark_dirty(
                (int)$tenant->id,
                'tenant',
                'local_tenantmaster_tenant',
                (int)$tenant->id,
                'existing_company_adopted',
            );
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

    /**
     * Create a native company, tenant identity, roles and applicable defaults.
     *
     * @param object $data Validated onboarding data.
     * @return object Tenant.
     */
    public function create(object $data): object {
        global $DB;

        if (!array_key_exists((string)$data->tenanttype, catalog::TENANT_TYPES)) {
            throw new \invalid_parameter_exception('Invalid tenant type.');
        }
        if (!catalog::valid_external_key((string)$data->trustcode)) {
            throw new \invalid_parameter_exception('Invalid trust code.');
        }
        if ($DB->record_exists('local_tenantmaster_tenant', ['trustcode' => $data->trustcode])) {
            throw new \invalid_parameter_exception('The trust code already exists.');
        }
        $parentcompanyid = (int)($data->parentcompanyid ?? 0);
        if (
            $parentcompanyid > 0
                && !$DB->record_exists('local_iomad_companies', ['id' => $parentcompanyid, 'suspended' => 0])
        ) {
            throw new \invalid_parameter_exception('The selected parent company is unavailable.');
        }
        $companydata = (object)[
            'name' => $data->name,
            'shortname' => $data->shortname,
            'code' => $data->trustcode,
            'address' => $data->address ?? '',
            'city' => $data->city,
            'region' => $data->region ?? '',
            'postcode' => $data->postcode ?? '',
            'country' => $data->country,
            'theme' => '',
            'parentid' => $parentcompanyid,
            'hostname' => $data->hostname ?? '',
            'custom1' => '',
            'custom2' => '',
            'custom3' => '',
            'templates' => [],
        ];
        $transaction = $DB->start_delegated_transaction();
        $company = company::create_company($companydata);
        $tenant = (new tenant_repository())->save((object)[
            'companyid' => (int)$company->id,
            'trustcode' => $data->trustcode,
            'tenanttype' => $data->tenanttype,
            'status' => 'active',
            'activeyearid' => 0,
            'defaultversion' => null,
            'profilejson' => json::encode([
                'name' => $data->name,
                'address' => $data->address ?? '',
                'city' => $data->city,
                'region' => $data->region ?? '',
                'postcode' => $data->postcode ?? '',
                'country' => $data->country,
                'hostname' => $data->hostname ?? '',
            ]),
        ]);
        (new role_service())->ensure_defaults((int)$tenant->id);
        (new default_service())->adopt($tenant);
        (new queue_service())->mark_dirty(
            (int)$tenant->id,
            'tenant',
            'local_tenantmaster_tenant',
            (int)$tenant->id,
            'tenant_onboarded',
        );
        (new audit_service())->record(
            (int)$tenant->id,
            'tenant.onboarded',
            'success',
            ['tenanttype' => $tenant->tenanttype],
            ['entitytable' => 'local_tenantmaster_tenant', 'entityid' => (int)$tenant->id],
        );
        $transaction->allow_commit();
        return $tenant;
    }
}
