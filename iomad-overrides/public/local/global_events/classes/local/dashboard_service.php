<?php
// This file is part of Moodle - http://moodle.org/

namespace local_global_events\local;

/**
 * Role-safe dashboard projections.
 *
 * @package local_global_events
 */
final class dashboard_service {
    /**
     * Learner-owned projection.
     *
     * @param tenant_scope $scope Scope.
     * @param int $userid Learner.
     * @return array
     */
    public function learner(tenant_scope $scope, int $userid): array {
        return [
            'profile' => 'learner',
            'progress' => (new gamification_service())->progress($scope, $userid),
            'badges' => (new badge_service())->earned($scope, $userid),
            'events' => array_map(static fn(object $event): array => [
                'id' => (int)$event->id,
                'name' => (string)$event->name,
                'courseid' => (int)$event->courseid,
                'timestart' => (int)$event->timestart,
                'timeend' => (int)$event->timeend,
            ], (new event_repository())->available($scope, 10)),
        ];
    }

    /**
     * Company/parent projection containing aggregate metrics only.
     *
     * @param tenant_scope $scope Scope.
     * @param bool $includechildren Include children.
     * @return array
     */
    public function manager(tenant_scope $scope, bool $includechildren = false): array {
        global $DB;

        $context = \local_iomad\custom_context\context_company::instance($scope->companyid());
        if (!is_siteadmin()) {
            \local_iomad\iomad::require_capability(
                'local/global_events:viewreports',
                $context,
                $scope->companyid(),
            );
        }
        $companyids = $scope->report_companyids($includechildren);
        [$insql, $params] = $DB->get_in_or_equal($companyids, SQL_PARAMS_NAMED, 'membercompany');
        $companyrecords = $DB->get_records_list(
            'local_iomad_companies',
            'id',
            $companyids,
            '',
            'id,name',
        );
        $members = $DB->get_records_sql(
            "SELECT companyid, COUNT(DISTINCT userid) AS members
               FROM {local_iomad_company_users}
              WHERE companyid {$insql}
                AND suspended = 0
           GROUP BY companyid",
            $params,
        );
        $totals = [];
        foreach ((new ledger_repository())->company_totals($companyids) as $row) {
            $totals[$row['companyid']] = $row;
        }
        $companies = [];
        foreach ($companyids as $companyid) {
            $row = $totals[$companyid] ?? [
                'companyid' => $companyid,
                'points' => 0,
                'activelearners' => 0,
                'awards' => 0,
            ];
            $row['members'] = isset($members[$companyid]) ? (int)$members[$companyid]->members : 0;
            $row['name'] = isset($companyrecords[$companyid])
                ? (string)$companyrecords[$companyid]->name
                : get_string('unknown');
            $companies[] = $row;
        }
        return ['profile' => $includechildren ? 'parent-manager' : 'company-manager', 'companies' => $companies];
    }
}
