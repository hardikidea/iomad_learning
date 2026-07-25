<?php
// This file is part of IOMAD - http://www.iomad.org/

namespace local_iomadpagebuilder;

use context_system;
use local_iomad\custom_context\context_company;
use local_iomad\iomad;

defined('MOODLE_INTERNAL') || die();

/**
 * Resolves and authorises IOMAD company scope.
 */
final class tenant_context {
    /**
     * Resolve the active company without trusting a request parameter.
     */
    public static function companyid(bool $required = true): int {
        $companyid = iomad::get_my_companyid(context_system::instance(), false);
        if ($required && $companyid <= 0 && !is_siteadmin()) {
            throw new \moodle_exception('pleaseselect', 'block_iomad_company_admin');
        }
        return max(0, $companyid);
    }

    /**
     * Require a capability in the active company context.
     */
    public static function require_capability(string $capability, ?int $companyid = null): void {
        $companyid = $companyid ?? self::companyid();
        if (is_siteadmin()) {
            return;
        }
        if ($companyid <= 0) {
            throw new \required_capability_exception(
                context_system::instance(),
                $capability,
                'nopermissions',
                ''
            );
        }
        iomad::require_capability($capability, context_company::instance($companyid), $companyid);
    }

    /**
     * Return the context used by company-level page builder screens.
     */
    public static function context(?int $companyid = null): \context {
        $companyid = $companyid ?? self::companyid(false);
        return $companyid > 0 ? context_company::instance($companyid) : context_system::instance();
    }
}
