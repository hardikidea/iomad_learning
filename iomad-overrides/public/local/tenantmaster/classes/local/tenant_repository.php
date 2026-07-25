<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\local;

/**
 * Tenant identity repository.
 *
 * Native company data stays in IOMAD. This repository stores only the stable
 * business identity and Tenant Master-owned profile attributes.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class tenant_repository {
    /**
     * Get by plugin tenant ID.
     *
     * @param int $tenantid Tenant ID.
     * @return object
     */
    public function get(int $tenantid): object {
        global $DB;
        return $DB->get_record('local_tenantmaster_tenant', ['id' => $tenantid], '*', MUST_EXIST);
    }

    /**
     * Get by native IOMAD company ID.
     *
     * @param int $companyid Company ID.
     * @return object|null
     */
    public function get_by_company(int $companyid): ?object {
        global $DB;
        $record = $DB->get_record('local_tenantmaster_tenant', ['companyid' => $companyid]);
        return $record ?: null;
    }

    /**
     * List tenant profiles joined to current native company values.
     *
     * @param int $companyid Optional company boundary.
     * @return array<int, object>
     */
    public function list(int $companyid = 0): array {
        global $DB;

        $where = '';
        $params = [];
        if ($companyid > 0) {
            $where = 'WHERE t.companyid = :companyid';
            $params['companyid'] = $companyid;
        }
        return $DB->get_records_sql(
            "SELECT t.*, c.name AS companyname, c.shortname AS companyshortname,
                    c.code AS companycode, c.suspended AS companysuspended
               FROM {local_tenantmaster_tenant} t
               JOIN {local_iomad_companies} c ON c.id = t.companyid
             $where
           ORDER BY c.name ASC",
            $params,
        );
    }

    /**
     * Create or update a tenant profile.
     *
     * @param object $record Record.
     * @return object
     */
    public function save(object $record): object {
        global $DB, $USER;

        $now = time();
        $record->profilejson = $record->profilejson ?? '{}';
        $record->sourcehash = json::hash([
            'trustcode' => $record->trustcode,
            'tenanttype' => $record->tenanttype,
            'profile' => json::decode_object($record->profilejson),
        ]);
        $record->timemodified = $now;
        $record->modifiedby = (int)($USER->id ?? 0);
        if (!empty($record->id)) {
            $DB->update_record('local_tenantmaster_tenant', $record);
        } else {
            $record->timecreated = $now;
            $record->createdby = (int)($USER->id ?? 0);
            $record->id = $DB->insert_record('local_tenantmaster_tenant', $record);
        }
        return $this->get((int)$record->id);
    }

    /**
     * Provision a minimal profile for an existing native company.
     *
     * @param int $companyid Company ID.
     * @param string $tenanttype Tenant type.
     * @return object
     */
    public function ensure_for_company(int $companyid, string $tenanttype = 'training'): object {
        global $DB;

        $existing = $this->get_by_company($companyid);
        if ($existing) {
            return $existing;
        }
        $company = $DB->get_record('local_iomad_companies', ['id' => $companyid], '*', MUST_EXIST);
        $basecode = trim((string)$company->code) ?: (string)$company->shortname;
        $trustcode = strtoupper(preg_replace('/[^A-Za-z0-9._:-]/', '_', $basecode));
        $candidate = $trustcode;
        $suffix = 1;
        while ($DB->record_exists('local_tenantmaster_tenant', ['trustcode' => $candidate])) {
            $candidate = $trustcode . '_' . ++$suffix;
        }
        return $this->save((object)[
            'companyid' => $companyid,
            'trustcode' => $candidate,
            'tenanttype' => $tenanttype,
            'status' => 'active',
            'activeyearid' => 0,
            'defaultversion' => null,
            'profilejson' => json::encode([
                'name' => (string)$company->name,
                'address' => (string)($company->address ?? ''),
                'city' => (string)($company->city ?? ''),
                'region' => (string)($company->region ?? ''),
                'postcode' => (string)($company->postcode ?? ''),
                'country' => (string)($company->country ?? ''),
                'hostname' => (string)($company->hostname ?? ''),
            ]),
        ]);
    }
}
