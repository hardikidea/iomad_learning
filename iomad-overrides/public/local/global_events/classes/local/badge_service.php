<?php
// This file is part of Moodle - http://moodle.org/

namespace local_global_events\local;

/**
 * Apply tenant-owned badge threshold rules using the core badge API.
 *
 * @package local_global_events
 */
final class badge_service {
    /**
     * Create or update one company-owned XP threshold rule.
     *
     * @param tenant_scope $scope Company scope.
     * @param int $badgeid Core badge ID.
     * @param int $minpoints Minimum XP.
     * @return object
     */
    public function upsert_threshold_rule(tenant_scope $scope, int $badgeid, int $minpoints): object {
        global $DB;

        if ($badgeid <= 0 || $minpoints < 0) {
            throw new \invalid_parameter_exception('Invalid badge threshold rule.');
        }
        $badge = $DB->get_record('badge', ['id' => $badgeid], 'id,courseid', MUST_EXIST);
        if (!empty($badge->courseid) && !$scope->contains_course((int)$badge->courseid)) {
            throw new \invalid_parameter_exception('The badge course is outside the company scope.');
        }
        $conditions = [
            'companyid' => $scope->companyid(),
            'badgeid' => $badgeid,
        ];
        $record = $DB->get_record('local_ge_badgerule', $conditions);
        if ($record) {
            $record->minpoints = $minpoints;
            $record->enabled = 1;
            $DB->update_record('local_ge_badgerule', $record);
            return $record;
        }
        $record = (object)($conditions + [
            'minpoints' => $minpoints,
            'enabled' => 1,
        ]);
        $record->id = $DB->insert_record('local_ge_badgerule', $record);
        return $record;
    }

    /**
     * Return recent badges mapped to this company through threshold rules.
     *
     * @param tenant_scope $scope Scope.
     * @param int $userid Learner.
     * @param int $limit Maximum results.
     * @return array
     */
    public function earned(tenant_scope $scope, int $userid, int $limit = 6): array {
        global $CFG, $DB;

        if (!$scope->contains_user($userid)) {
            throw new \invalid_parameter_exception('The learner is outside the company scope.');
        }
        $ruleids = $DB->get_fieldset_select(
            'local_ge_badgerule',
            'badgeid',
            'companyid = :companyid AND enabled = 1',
            ['companyid' => $scope->companyid()],
        );
        if (!$ruleids) {
            return [];
        }
        require_once($CFG->libdir . '/badgeslib.php');
        $allowed = array_fill_keys(array_map('intval', $ruleids), true);
        $earned = [];
        foreach (badges_get_user_badges($userid, 0, 0, 100) as $badge) {
            if (!isset($allowed[(int)$badge->id])) {
                continue;
            }
            $earned[] = [
                'id' => (int)$badge->id,
                'name' => (string)$badge->name,
                'dateissued' => (int)$badge->dateissued,
            ];
            if (count($earned) >= max(1, min(100, $limit))) {
                break;
            }
        }
        return $earned;
    }

    /**
     * Issue newly earned badges.
     *
     * @param int $companyid Company.
     * @param int $userid Learner.
     * @param int $points New XP total.
     * @return int[] Issued badge IDs.
     */
    public function issue_earned(int $companyid, int $userid, int $points): array {
        global $DB;

        $rules = $DB->get_records_select(
            'local_ge_badgerule',
            'companyid = :companyid AND enabled = 1 AND minpoints <= :points',
            ['companyid' => $companyid, 'points' => $points],
            'minpoints ASC, id ASC',
        );
        $issued = [];
        foreach ($rules as $rule) {
            if (!$DB->record_exists('badge', ['id' => $rule->badgeid])) {
                continue;
            }
            $badge = new \core_badges\badge((int)$rule->badgeid);
            if (!$badge->is_issued($userid)) {
                $badge->issue($userid);
                $issued[] = (int)$rule->badgeid;
            }
        }
        return $issued;
    }
}
