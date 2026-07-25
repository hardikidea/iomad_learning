<?php
// This file is part of Moodle - http://moodle.org/

namespace local_global_events\local;

use local_iomad\company;
use local_iomad\iomad;

/**
 * Resolve and enforce one IOMAD company boundary.
 *
 * @package local_global_events
 */
final class tenant_scope {
    /**
     * Constructor.
     *
     * @param int $companyid Company ID.
     */
    private function __construct(private readonly int $companyid) {
    }

    /**
     * Resolve an explicitly authorized system operation.
     *
     * @param int $companyid Company.
     * @return self
     */
    public static function system(int $companyid): self {
        if (
            !defined('CLI_SCRIPT')
            && !defined('PHPUNIT_TEST')
            && !is_siteadmin()
        ) {
            throw new \required_capability_exception(
                \context_system::instance(),
                'local/global_events:manage',
                'nopermissions',
                '',
            );
        }
        self::require_company($companyid);
        return new self($companyid);
    }

    /**
     * Build a scope after an authenticated integration has supplied a company.
     *
     * @param int $companyid Company.
     * @param int $userid User expected in the company.
     * @return self
     */
    public static function verified_membership(int $companyid, int $userid): self {
        self::require_company($companyid);
        $scope = new self($companyid);
        if (!$scope->contains_user($userid)) {
            throw new \invalid_parameter_exception('The user is outside the company scope.');
        }
        return $scope;
    }

    /**
     * Resolve the authenticated user's active company.
     *
     * @param int $requested Explicit site-administrator company.
     * @return self
     */
    public static function current(int $requested = 0): self {
        global $DB;

        if (is_siteadmin() && $requested > 0) {
            self::require_company($requested);
            return new self($requested);
        }
        $companyid = iomad::get_my_companyid(\context_system::instance(), false);
        if ($companyid <= 0 || ($requested > 0 && $requested !== $companyid)) {
            throw new \required_capability_exception(
                \context_system::instance(),
                'local/global_events:view',
                'nopermissions',
                '',
            );
        }
        if (
            !$DB->record_exists('local_iomad_company_users', [
                'companyid' => $companyid,
                'userid' => (int)$GLOBALS['USER']->id,
            ])
        ) {
            throw new \required_capability_exception(
                \context_system::instance(),
                'local/global_events:view',
                'nopermissions',
                '',
            );
        }
        return new self($companyid);
    }

    /**
     * Resolve an event actor to a company that owns the user and course.
     *
     * @param int $userid User.
     * @param int $courseid Course, zero for a site event.
     * @return self
     */
    public static function for_learning_event(int $userid, int $courseid = 0): self {
        global $DB, $USER;

        $preferred = 0;
        if ((int)($USER->id ?? 0) === $userid) {
            $preferred = iomad::get_my_companyid(\context_system::instance(), false);
        }
        $memberships = $DB->get_records(
            'local_iomad_company_users',
            ['userid' => $userid, 'suspended' => 0],
            'id ASC',
            'id,companyid',
        );
        if (!$memberships) {
            throw new \invalid_parameter_exception('The learner has no active company membership.');
        }
        $companyids = array_values(array_unique(array_map(
            static fn(object $membership): int => (int)$membership->companyid,
            $memberships,
        )));
        if ($preferred > 0 && in_array($preferred, $companyids, true)) {
            $scope = new self($preferred);
            if ($courseid === 0 || $scope->contains_course($courseid)) {
                return $scope;
            }
        }
        foreach ($companyids as $companyid) {
            $scope = new self($companyid);
            if ($courseid === 0 || $scope->contains_course($courseid)) {
                return $scope;
            }
        }
        throw new \invalid_parameter_exception('The learning activity is outside the learner company scope.');
    }

    /**
     * Company ID.
     *
     * @return int
     */
    public function companyid(): int {
        return $this->companyid;
    }

    /**
     * Verify an exact company membership.
     *
     * @param int $userid User.
     * @return bool
     */
    public function contains_user(int $userid): bool {
        global $DB;

        return $DB->record_exists('local_iomad_company_users', [
            'companyid' => $this->companyid,
            'userid' => $userid,
            'suspended' => 0,
        ]);
    }

    /**
     * Verify that a course is assigned or shared to the company.
     *
     * @param int $courseid Course.
     * @return bool
     */
    public function contains_course(int $courseid): bool {
        $courses = (new company($this->companyid))->get_menu_courses(
            shared: true,
            default: false,
            includehidden: true,
        );
        return array_key_exists($courseid, $courses);
    }

    /**
     * Company IDs visible to an explicitly authorized parent manager.
     *
     * @param bool $includechildren Include child companies.
     * @return int[]
     */
    public function report_companyids(bool $includechildren): array {
        $ids = [$this->companyid];
        if ($includechildren) {
            $ids = array_merge(
                $ids,
                array_map('intval', array_keys((new company($this->companyid))->get_child_companies_recursive())),
            );
        }
        return array_values(array_unique($ids));
    }

    /**
     * Require an existing company.
     *
     * @param int $companyid Company.
     */
    private static function require_company(int $companyid): void {
        global $DB;

        if (!$DB->record_exists('local_iomad_companies', ['id' => $companyid])) {
            throw new \invalid_parameter_exception('The company does not exist.');
        }
    }
}
