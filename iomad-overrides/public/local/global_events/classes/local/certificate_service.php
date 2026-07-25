<?php
// This file is part of Moodle - http://moodle.org/

namespace local_global_events\local;

/**
 * Privacy-safe projection of official IOMAD certificate issues.
 *
 * @package local_global_events
 */
final class certificate_service {
    /**
     * Count certificates earned in courses visible to the active company.
     *
     * Certificate codes remain in the authenticated certificate module and are
     * never copied into chat messages or the project plugin.
     *
     * @param tenant_scope $scope Scope.
     * @param int $userid Learner.
     * @return int
     */
    public function count_earned(tenant_scope $scope, int $userid): int {
        global $DB;

        if (!$scope->contains_user($userid)) {
            throw new \invalid_parameter_exception('The learner is outside the company scope.');
        }
        if (!$DB->get_manager()->table_exists('iomadcertificate_issues')) {
            return 0;
        }
        $records = $DB->get_records_sql(
            "SELECT issues.id, certificate.course
               FROM {iomadcertificate_issues} issues
               JOIN {iomadcertificate} certificate
                 ON certificate.id = issues.iomadcertificateid
              WHERE issues.userid = :userid
           ORDER BY issues.id DESC",
            ['userid' => $userid],
            0,
            100,
        );
        $count = 0;
        foreach ($records as $record) {
            if ($scope->contains_course((int)$record->course)) {
                $count++;
            }
        }
        return $count;
    }
}
