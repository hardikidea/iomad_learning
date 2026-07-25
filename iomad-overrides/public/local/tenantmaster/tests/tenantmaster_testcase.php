<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster;

use local_iomad\company;
use local_tenantmaster\local\json;
use local_tenantmaster\local\tenant_repository;

/**
 * Shared Tenant Master integration-test helpers.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class tenantmaster_testcase extends \advanced_testcase {
    /**
     * Create a complete native IOMAD company and plugin tenant identity.
     *
     * @param string $tenanttype Tenant type.
     * @return object
     */
    protected function create_tenant(string $tenanttype = 'school'): object {
        $this->setAdminUser();
        $suffix = strtoupper(substr(hash('sha256', microtime(true) . random_int(1, PHP_INT_MAX)), 0, 8));
        $company = company::create_company((object)[
            'name' => 'Tenant ' . $suffix,
            'shortname' => 'TM' . $suffix,
            'code' => 'TRUST_' . $suffix,
            'address' => '',
            'city' => 'Ahmedabad',
            'region' => 'Gujarat',
            'postcode' => '380001',
            'country' => 'IN',
            'theme' => '',
            'parentid' => 0,
            'hostname' => '',
            'custom1' => '',
            'custom2' => '',
            'custom3' => '',
            'templates' => [],
        ]);
        return (new tenant_repository())->save((object)[
            'companyid' => (int)$company->id,
            'trustcode' => 'TRUST_' . $suffix,
            'tenanttype' => $tenanttype,
            'status' => 'active',
            'activeyearid' => 0,
            'defaultversion' => null,
            'profilejson' => json::encode([]),
        ]);
    }
}
