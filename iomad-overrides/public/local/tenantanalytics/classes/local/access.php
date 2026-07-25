<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantanalytics\local;

use context_system;
use local_iomad\company;
use local_iomad\custom_context\context_company;
use local_iomad\iomad;

/**
 * Resolves the active tenant and the narrowest authorised report boundary.
 *
 * @package    local_tenantanalytics
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class access {
    /**
     * Create resolved access state.
     *
     * @param int $companyid Active company.
     * @param \context $context Page context.
     * @param tenant_scope $scope Data scope.
     * @param bool $companyview Whether company reports are authorised.
     * @param bool $manageschedules Whether schedule management is authorised.
     */
    private function __construct(
        private readonly int $companyid,
        private readonly \context $context,
        private readonly tenant_scope $scope,
        private readonly bool $companyview,
        private readonly bool $manageschedules,
    ) {
    }

    /**
     * Resolve access for the current authenticated user.
     *
     * @return self
     */
    public static function resolve(): self {
        global $USER;

        $systemcontext = context_system::instance();
        $companyid = max(0, iomad::get_my_companyid($systemcontext, false));
        $context = $companyid > 0 ? context_company::instance($companyid) : $systemcontext;
        $companyview = $companyid > 0
            && (is_siteadmin() || iomad::has_capability('local/tenantanalytics:viewcompany', $context));

        if (!$companyview) {
            require_capability('local/tenantanalytics:viewown', $systemcontext);
            return new self(
                $companyid,
                $context,
                new tenant_scope(0, (int)$USER->id, true),
                false,
                false,
            );
        }

        $scope = self::company_scope_for_user($USER, $companyid, $context);
        $manageschedules = is_siteadmin()
            || iomad::has_capability('local/tenantanalytics:manageschedules', $context);
        return new self(
            $companyid,
            $context,
            $scope,
            true,
            $manageschedules,
        );
    }

    /**
     * Build a company or department scope for an authorised user.
     *
     * The caller must set the user as the current session user before using this
     * method from cron because IOMAD restrictions use global access data.
     *
     * @param object $user User.
     * @param int $companyid Company.
     * @param \context|null $context Company context.
     * @return tenant_scope
     */
    public static function company_scope_for_user(
        object $user,
        int $companyid,
        ?\context $context = null
    ): tenant_scope {
        $context = $context ?? context_company::instance($companyid);
        $departmentids = [];
        $canviewalldepartments = is_siteadmin($user)
            || iomad::has_capability('block/iomad_company_admin:edit_all_departments', $context, $companyid);
        if (!$canviewalldepartments) {
            $company = new company($companyid);
            $levels = $company->get_userlevel($user);
            foreach (array_keys($levels) as $departmentid) {
                $departments = company::get_all_subdepartments((int)$departmentid);
                $departmentids = array_merge($departmentids, array_map('intval', array_keys($departments)));
            }
            if (!$departmentids) {
                throw new \required_capability_exception(
                    $context,
                    'local/tenantanalytics:viewcompany',
                    'nopermissions',
                    ''
                );
            }
        }
        return new tenant_scope($companyid, (int)$user->id, false, $departmentids);
    }

    /**
     * Return active company ID.
     *
     * @return int
     */
    public function get_companyid(): int {
        return $this->companyid;
    }

    /**
     * Return the page context.
     *
     * @return \context
     */
    public function get_context(): \context {
        return $this->context;
    }

    /**
     * Return the authorised data scope.
     *
     * @return tenant_scope
     */
    public function get_scope(): tenant_scope {
        return $this->scope;
    }

    /**
     * Whether company reporting is authorised.
     *
     * @return bool
     */
    public function can_view_company(): bool {
        return $this->companyview;
    }

    /**
     * Whether schedule management is authorised.
     *
     * @return bool
     */
    public function can_manage_schedules(): bool {
        return $this->manageschedules;
    }
}
