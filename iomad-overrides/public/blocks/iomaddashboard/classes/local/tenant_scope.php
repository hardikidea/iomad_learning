<?php
// This file is part of Moodle - http://moodle.org/

namespace block_iomaddashboard\local;

use context_system;
use local_iomad\company;
use local_iomad\iomad;

/**
 * Resolves the active company boundary for dashboard data.
 *
 * @package    block_iomaddashboard
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class tenant_scope {
    /** @var int */
    private int $companyid;

    /** @var int[] */
    private array $companyids;

    /**
     * Resolve a supplied company or the current IOMAD company.
     *
     * @param int|null $companyid Explicit company for tested/internal calls.
     */
    public function __construct(?int $companyid = null) {
        if ($companyid === null) {
            $companyid = iomad::get_my_companyid(context_system::instance(), false);
        }
        $this->companyid = max(0, $companyid);
        $this->companyids = $this->companyid ? [$this->companyid] : [];
        if ($this->companyid) {
            $children = (new company($this->companyid))->get_child_companies_recursive();
            $this->companyids = array_values(array_unique(array_merge(
                $this->companyids,
                array_map('intval', array_keys($children)),
            )));
        }
    }

    /**
     * Return the active company.
     *
     * @return int
     */
    public function get_companyid(): int {
        return $this->companyid;
    }

    /**
     * Return active and child company IDs.
     *
     * @return int[]
     */
    public function get_companyids(): array {
        return $this->companyids;
    }

    /**
     * Check whether a user belongs to this company boundary.
     *
     * @param int $userid User ID.
     * @return bool
     */
    public function contains_user(int $userid): bool {
        global $DB;

        if (!$this->companyids) {
            return is_siteadmin();
        }
        [$insql, $params] = $DB->get_in_or_equal($this->companyids, SQL_PARAMS_NAMED, 'company');
        $params['userid'] = $userid;
        return $DB->record_exists_select(
            'local_iomad_company_users',
            "userid = :userid AND companyid {$insql} AND suspended = 0",
            $params,
        );
    }
}
