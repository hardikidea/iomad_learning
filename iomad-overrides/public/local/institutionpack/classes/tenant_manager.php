<?php
// This file is part of IOMAD - http://www.iomad.org/

namespace local_institutionpack;

use core_component;
use local_iomad\company;

defined('MOODLE_INTERNAL') || die();

/**
 * Safe, idempotent company provisioning through the IOMAD company API.
 */
final class tenant_manager {
    /**
     * Build a tenant creation plan.
     *
     * @param array $input Raw command input.
     * @return array
     */
    public function plan(array $input): array {
        global $DB;

        $data = $this->normalise($input);
        $existing = $DB->get_record(
            'local_iomad_companies',
            ['shortname' => $data->shortname],
            '*',
            IGNORE_MISSING
        );

        if ($existing) {
            $differences = $this->differences($existing, $data);
            return [
                'ok' => empty($differences),
                'mode' => 'plan',
                'action' => empty($differences) ? 'unchanged' : 'conflict',
                'tenant' => $this->public_data($data),
                'differences' => $differences,
                'message' => empty($differences)
                    ? 'The tenant already matches the requested definition.'
                    : 'The shortname already exists with different values; use an institution pack to review updates.',
            ];
        }

        $this->validate_unique_routing($data);
        return [
            'ok' => true,
            'mode' => 'plan',
            'action' => 'create',
            'tenant' => $this->public_data($data),
        ];
    }

    /**
     * Create a tenant, or return an idempotent no-op.
     *
     * @param array $input Raw command input.
     * @return array
     */
    public function apply(array $input): array {
        global $DB;

        $plan = $this->plan($input);
        if (!$plan['ok'] || $plan['action'] === 'unchanged') {
            $plan['mode'] = 'apply';
            return $plan;
        }

        $data = $this->normalise($input);
        $transaction = $DB->start_delegated_transaction();
        $company = company::create_company($data);
        $transaction->allow_commit();

        $result = [
            'ok' => true,
            'mode' => 'apply',
            'action' => 'created',
            'tenant' => $this->public_data($data),
            'isolation' => 'IOMAD company scope enforced',
        ];
        $result['audit_report'] = audit_log::write('tenant-create', $result);
        return $result;
    }

    /**
     * Validate and canonicalise command data.
     *
     * @param array $input Raw input.
     * @return \stdClass
     */
    private function normalise(array $input): \stdClass {
        global $CFG, $DB;

        $name = trim((string)($input['name'] ?? ''));
        $shortname = trim((string)($input['shortname'] ?? ''));
        $city = trim((string)($input['city'] ?? ''));
        $country = strtoupper(trim((string)($input['country'] ?? '')));
        $theme = trim((string)($input['theme'] ?? $CFG->theme));
        $hostname = $this->normalise_domain((string)($input['hostname'] ?? ''), true);
        $emaildomain = $this->normalise_domain((string)($input['emaildomain'] ?? ''), false);
        $parent = trim((string)($input['parent'] ?? ''));
        $maxusers = filter_var($input['maxusers'] ?? 0, FILTER_VALIDATE_INT);

        if ($name === '' || \core_text::strlen($name) > 50) {
            throw new \InvalidArgumentException('Tenant name is required and must not exceed 50 characters.');
        }
        if (!preg_match('/^[A-Za-z0-9_]{1,25}$/', $shortname)) {
            throw new \InvalidArgumentException('Tenant shortname must contain 1-25 letters, digits, or underscores.');
        }
        if ($city === '' || \core_text::strlen($city) > 50) {
            throw new \InvalidArgumentException('City is required and must not exceed 50 characters.');
        }
        if (!array_key_exists($country, get_string_manager()->get_list_of_countries())) {
            throw new \InvalidArgumentException('Country must be a valid two-letter Moodle country code.');
        }
        if ($maxusers === false || $maxusers < 0) {
            throw new \InvalidArgumentException('Maximum users must be zero or a positive integer.');
        }
        if (clean_param($theme, PARAM_THEME) !== $theme || core_component::get_plugin_directory('theme', $theme) === null) {
            throw new \InvalidArgumentException('The requested theme is not installed.');
        }

        $parentid = 0;
        if ($parent !== '') {
            if ($parent === $shortname) {
                throw new \InvalidArgumentException('A tenant cannot be its own parent.');
            }
            $parentrecord = $DB->get_record(
                'local_iomad_companies',
                ['shortname' => $parent],
                'id',
                IGNORE_MISSING
            );
            if (!$parentrecord) {
                throw new \InvalidArgumentException('Parent tenant shortname was not found.');
            }
            $parentid = (int)$parentrecord->id;
        }

        $data = (object)[
            'name' => clean_param($name, PARAM_NOTAGS),
            'shortname' => $shortname,
            'city' => clean_param($city, PARAM_NOTAGS),
            'country' => $country,
            'theme' => $theme,
            'hostname' => $hostname,
            'companydomains' => $emaildomain,
            'maxusers' => $maxusers,
            'parentid' => $parentid,
            'customcss' => (string)($input['customcss'] ?? ''),
            'code' => trim((string)($input['externalid'] ?? '')),
        ];

        if (strlen($data->customcss) > 65535) {
            throw new \InvalidArgumentException('Tenant CSS must not exceed 65535 bytes.');
        }
        if (strlen($data->code) > 255) {
            throw new \InvalidArgumentException('External ID must not exceed 255 characters.');
        }

        return $data;
    }

    /**
     * Canonicalise a hostname or email domain.
     *
     * @param string $value Raw value.
     * @param bool $optional Whether an empty value is accepted.
     * @return string
     */
    private function normalise_domain(string $value, bool $optional): string {
        $value = strtolower(rtrim(trim($value), '.'));
        if ($value === '' && $optional) {
            return '';
        }
        if (
            $value === ''
            || filter_var($value, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false
            || str_contains($value, '/')
            || str_contains($value, ':')
        ) {
            throw new \InvalidArgumentException('Hostnames and email domains must be bare DNS names.');
        }
        return $value;
    }

    /**
     * Reject routing values already assigned to another company.
     *
     * @param \stdClass $data Canonical company data.
     */
    private function validate_unique_routing(\stdClass $data): void {
        global $DB;

        if (
            $data->hostname !== ''
            && $DB->record_exists('local_iomad_companies', ['hostname' => $data->hostname])
        ) {
            throw new \InvalidArgumentException('The hostname is already assigned to another tenant.');
        }
        if (
            $data->companydomains !== ''
            && $DB->record_exists_sql(
                "SELECT 1
                   FROM {local_iomad_company_domains}
                  WHERE " . $DB->sql_compare_text('domain') . ' = :domain',
                ['domain' => $data->companydomains]
            )
        ) {
            throw new \InvalidArgumentException('The email domain is already assigned to another tenant.');
        }
    }

    /**
     * Compare stable, operator-controlled fields.
     *
     * @param \stdClass $existing Existing company.
     * @param \stdClass $requested Requested company.
     * @return array
     */
    private function differences(\stdClass $existing, \stdClass $requested): array {
        global $DB;

        $actualdomain = '';
        $domains = $DB->get_fieldset_select(
            'local_iomad_company_domains',
            'domain',
            'companyid = :companyid',
            ['companyid' => $existing->id]
        );
        if ($domains) {
            sort($domains);
            $actualdomain = implode("\n", $domains);
        }

        $actual = [
            'name' => (string)$existing->name,
            'city' => (string)$existing->city,
            'country' => (string)$existing->country,
            'theme' => (string)$existing->theme,
            'hostname' => (string)$existing->hostname,
            'email_domain' => $actualdomain,
            'max_users' => (int)$existing->maxusers,
            'parent_id' => (int)$existing->parentid,
            'external_id' => (string)$existing->code,
            'custom_css_sha256' => hash('sha256', (string)$existing->customcss),
        ];
        $wanted = $this->public_data($requested);
        unset($wanted['shortname']);

        $differences = [];
        foreach ($wanted as $field => $value) {
            if ($actual[$field] !== $value) {
                $differences[$field] = [
                    'actual' => $actual[$field],
                    'requested' => $value,
                ];
            }
        }
        return $differences;
    }

    /**
     * Return non-sensitive data suitable for logs.
     *
     * @param \stdClass $data Canonical company data.
     * @return array
     */
    private function public_data(\stdClass $data): array {
        return [
            'shortname' => $data->shortname,
            'name' => $data->name,
            'city' => $data->city,
            'country' => $data->country,
            'theme' => $data->theme,
            'hostname' => $data->hostname,
            'email_domain' => $data->companydomains,
            'max_users' => (int)$data->maxusers,
            'parent_id' => (int)$data->parentid,
            'external_id' => $data->code,
            'custom_css_sha256' => hash('sha256', $data->customcss),
        ];
    }
}
