<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\local;

use context_system;
use local_iomad\custom_context\context_company;
use local_iomad\iomad;

/**
 * Resolve one authorised Tenant Master company boundary.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class access {
    /**
     * Constructor.
     *
     * @param int $companyid Company ID.
     * @param \context $context Authorisation context.
     */
    private function __construct(
        private readonly int $companyid,
        private readonly \context $context,
    ) {
    }

    /**
     * Resolve current company, allowing an explicit site-administrator selection.
     *
     * @param int $requestedcompanyid Requested company.
     * @param string $capability Required capability.
     * @return self
     */
    public static function resolve(
        int $requestedcompanyid = 0,
        string $capability = 'local/tenantmaster:view',
    ): self {
        global $DB;

        $systemcontext = context_system::instance();
        if (is_siteadmin() && $requestedcompanyid > 0) {
            if (!$DB->record_exists('local_iomad_companies', ['id' => $requestedcompanyid])) {
                throw new \invalid_parameter_exception('The requested company does not exist.');
            }
            return new self($requestedcompanyid, context_company::instance($requestedcompanyid));
        }

        $companyid = iomad::get_my_companyid($systemcontext, false);
        if ($companyid <= 0) {
            if (is_siteadmin()) {
                return new self(0, $systemcontext);
            }
            throw new \required_capability_exception($systemcontext, $capability, 'nopermissions', '');
        }

        $context = context_company::instance($companyid);
        if (!is_siteadmin()) {
            iomad::require_capability($capability, $context, $companyid);
        }
        return new self($companyid, $context);
    }

    /**
     * Company ID, or zero for an unselected site administrator.
     *
     * @return int
     */
    public function companyid(): int {
        return $this->companyid;
    }

    /**
     * Authorisation context.
     *
     * @return \context
     */
    public function context(): \context {
        return $this->context;
    }

    /**
     * Require an additional tenant capability.
     *
     * @param string $capability Capability.
     */
    public function require(string $capability): void {
        if (is_siteadmin()) {
            return;
        }
        if ($this->companyid <= 0) {
            throw new \required_capability_exception($this->context, $capability, 'nopermissions', '');
        }
        iomad::require_capability($capability, $this->context, $this->companyid);
    }
}
