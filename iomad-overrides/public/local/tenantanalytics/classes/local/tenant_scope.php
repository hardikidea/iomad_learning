<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantanalytics\local;

use local_iomad\company;

/**
 * Explicit company and child-company report boundary.
 *
 * @package    local_tenantanalytics
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class tenant_scope {
    /** @var int[] */
    private array $companyids = [];

    /** @var int[] */
    private array $departmentids = [];

    /**
     * Create a report boundary.
     *
     * @param int $companyid Active company, or zero for own-data mode.
     * @param int $requesterid Requesting user.
     * @param bool $ownonly Force data to requester.
     * @param int[] $departmentids Optional department-manager boundary.
     * @param bool $canviewpii Whether learner identity fields may be returned.
     */
    public function __construct(
        private readonly int $companyid,
        private readonly int $requesterid,
        private readonly bool $ownonly,
        array $departmentids = [],
        private readonly bool $canviewpii = true,
    ) {
        $this->departmentids = array_values(array_unique(array_filter(array_map('intval', $departmentids))));
        if ($companyid > 0) {
            $children = (new company($companyid))->get_child_companies_recursive();
            $this->companyids = array_values(array_unique(array_merge(
                [$companyid],
                array_map('intval', array_keys($children)),
            )));
        }
    }

    /**
     * Add a tenant/owner predicate to SQL.
     *
     * @param string $userfield SQL field containing a user ID.
     * @param array $params Existing named parameters.
     * @return array{0:string,1:array}
     */
    public function user_predicate(string $userfield, array $params = []): array {
        global $DB;

        if ($this->ownonly || !$this->companyids) {
            $params['scopeuserid'] = $this->requesterid;
            return ["{$userfield} = :scopeuserid", $params];
        }
        [$insql, $inparams] = $DB->get_in_or_equal($this->companyids, SQL_PARAMS_NAMED, 'scopecompany');
        $params = array_merge($params, $inparams);
        $departmentsql = '';
        if ($this->departmentids) {
            [$deptinsql, $deptparams] = $DB->get_in_or_equal(
                $this->departmentids,
                SQL_PARAMS_NAMED,
                'scopedepartment'
            );
            $departmentsql = " AND scu.departmentid {$deptinsql}";
            $params = array_merge($params, $deptparams);
        }
        return [
            "EXISTS (
                SELECT 1
                  FROM {local_iomad_company_users} scu
                 WHERE scu.userid = {$userfield}
                   AND scu.companyid {$insql}
                   AND scu.suspended = 0
                   {$departmentsql}
            )",
            $params,
        ];
    }

    /**
     * Add a company predicate for records that already carry a company ID.
     *
     * Own-data mode has no company predicate because a learner can legitimately
     * hold records in more than one company. The user predicate remains mandatory.
     *
     * @param string $companyfield SQL field containing a company ID.
     * @param array $params Existing named parameters.
     * @return array{0:string,1:array}
     */
    public function company_predicate(string $companyfield, array $params = []): array {
        global $DB;

        if ($this->ownonly || !$this->companyids) {
            return ['1 = 1', $params];
        }
        [$insql, $inparams] = $DB->get_in_or_equal($this->companyids, SQL_PARAMS_NAMED, 'recordcompany');
        return ["{$companyfield} {$insql}", array_merge($params, $inparams)];
    }

    /**
     * Restrict event-log courses to courses assigned to the company tree.
     *
     * Site-level events use course ID zero and remain in scope for tenant users.
     *
     * @param string $coursefield SQL field containing a course ID.
     * @param array $params Existing named parameters.
     * @return array{0:string,1:array}
     */
    public function course_predicate(string $coursefield, array $params = []): array {
        global $DB;

        if ($this->ownonly || !$this->companyids) {
            return ['1 = 1', $params];
        }
        [$insql, $inparams] = $DB->get_in_or_equal($this->companyids, SQL_PARAMS_NAMED, 'coursecompany');
        $params = array_merge($params, $inparams);
        return [
            "({$coursefield} = 0 OR EXISTS (
                SELECT 1
                  FROM {local_iomad_company_courses} scc
                 WHERE scc.courseid = {$coursefield}
                   AND scc.companyid {$insql}
            ))",
            $params,
        ];
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
     * Return requesting user ID.
     *
     * @return int
     */
    public function get_requesterid(): int {
        return $this->requesterid;
    }

    /**
     * Whether scope is limited to the requester.
     *
     * @return bool
     */
    public function is_ownonly(): bool {
        return $this->ownonly;
    }

    /**
     * Return company IDs included in scope.
     *
     * @return int[]
     */
    public function get_companyids(): array {
        return $this->companyids;
    }

    /**
     * Return department IDs included in scope.
     *
     * @return int[]
     */
    public function get_departmentids(): array {
        return $this->departmentids;
    }

    /**
     * Whether a department boundary applies.
     *
     * @return bool
     */
    public function has_department_restriction(): bool {
        return !empty($this->departmentids);
    }

    /**
     * Whether learner identity fields may be returned by reports.
     *
     * @return bool
     */
    public function can_view_pii(): bool {
        return $this->canviewpii;
    }
}
