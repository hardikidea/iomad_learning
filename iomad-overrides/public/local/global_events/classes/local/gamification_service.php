<?php
// This file is part of Moodle - http://moodle.org/

namespace local_global_events\local;

/**
 * Tenant-safe gamification application service.
 *
 * @package local_global_events
 */
final class gamification_service {
    /** Maximum absolute points in one event. */
    private const MAX_EVENT_POINTS = 10000;

    /**
     * Constructor.
     *
     * @param ledger_repository $ledger Ledger.
     * @param badge_service $badges Badge service.
     */
    public function __construct(
        private readonly ledger_repository $ledger = new ledger_repository(),
        private readonly badge_service $badges = new badge_service(),
    ) {
    }

    /**
     * Award one immutable event.
     *
     * @param tenant_scope $scope Company scope.
     * @param int $userid Learner.
     * @param int $points Signed points.
     * @param string $sourcecomponent Moodle component.
     * @param string $sourceevent Stable event name.
     * @param string $idempotencykey Stable source identity.
     * @param int $courseid Course.
     * @param int $cmid Course module.
     * @param string $pointstype XP or grade ledger.
     * @param array $metadata Non-personal stable metadata; stored only as a hash.
     * @return array
     */
    public function award(
        tenant_scope $scope,
        int $userid,
        int $points,
        string $sourcecomponent,
        string $sourceevent,
        string $idempotencykey,
        int $courseid = 0,
        int $cmid = 0,
        string $pointstype = 'xp',
        array $metadata = [],
    ): array {
        if (
            $userid <= 0
            || $points === 0
            || abs($points) > self::MAX_EVENT_POINTS
            || !preg_match('/^[a-z][a-z0-9_]{1,99}$/', $sourcecomponent)
            || !preg_match('/^[A-Za-z0-9_.:\\-]{2,100}$/', $sourceevent)
            || strlen($idempotencykey) < 8
            || !in_array($pointstype, ['xp', 'grade'], true)
            || $courseid < 0
            || $cmid < 0
        ) {
            throw new \invalid_parameter_exception('Invalid gamification award.');
        }
        if (!$scope->contains_user($userid)) {
            throw new \required_capability_exception(
                \context_system::instance(),
                'local/global_events:award',
                'nopermissions',
                '',
            );
        }
        if ($courseid > 0 && !$scope->contains_course($courseid)) {
            throw new \required_capability_exception(
                \context_course::instance($courseid),
                'local/global_events:award',
                'nopermissions',
                '',
            );
        }
        $metadata = $this->normalise_metadata($metadata);
        $result = $this->ledger->insert_once([
            'companyid' => $scope->companyid(),
            'userid' => $userid,
            'courseid' => $courseid,
            'cmid' => $cmid,
            'pointstype' => $pointstype,
            'points' => $points,
            'sourcecomponent' => $sourcecomponent,
            'sourceevent' => $sourceevent,
            'idempotencykey' => hash('sha256', $idempotencykey),
            'metadatahash' => hash('sha256', json_encode($metadata, JSON_THROW_ON_ERROR)),
            'timecreated' => time(),
        ]);
        $total = $this->ledger->total($scope->companyid(), $userid);
        $issued = [];
        if ($result['inserted'] && $pointstype === 'xp') {
            try {
                $issued = $this->badges->issue_earned($scope->companyid(), $userid, $total);
            } catch (\Throwable) {
                debugging('global_events_badge_evaluation_failed', DEBUG_DEVELOPER);
            }
        }
        return [
            'record' => $result['record'],
            'awarded' => $result['inserted'],
            'total' => $total,
            'badges' => $issued,
        ];
    }

    /**
     * Own progress.
     *
     * @param tenant_scope $scope Scope.
     * @param int $userid User.
     * @return array
     */
    public function progress(tenant_scope $scope, int $userid): array {
        global $DB;

        if (!$scope->contains_user($userid)) {
            throw new \invalid_parameter_exception('The learner is outside the company scope.');
        }
        $points = $this->ledger->total($scope->companyid(), $userid);
        $levels = $DB->get_records_select(
            'local_ge_level',
            'companyid = :companyid AND minpoints <= :points',
            ['companyid' => $scope->companyid(), 'points' => $points],
            'minpoints DESC',
            '*',
            0,
            1,
        );
        $level = reset($levels);
        $nextlevels = $DB->get_records_select(
            'local_ge_level',
            'companyid = :companyid AND minpoints > :points',
            ['companyid' => $scope->companyid(), 'points' => $points],
            'minpoints ASC',
            '*',
            0,
            1,
        );
        $nextlevel = reset($nextlevels);
        $minimum = $level ? (int)$level->minpoints : 0;
        $target = $nextlevel ? (int)$nextlevel->minpoints : max($points, 1);
        $percentage = $nextlevel
            ? (int)floor(100 * max(0, $points - $minimum) / max(1, $target - $minimum))
            : 100;
        return [
            'points' => $points,
            'level' => $level ? (int)$level->levelnum : 0,
            'levelname' => $level ? (string)$level->name : '',
            'nextlevel' => $nextlevel ? (int)$nextlevel->levelnum : 0,
            'nextlevelname' => $nextlevel ? (string)$nextlevel->name : '',
            'nextpoints' => $nextlevel ? (int)$nextlevel->minpoints : $points,
            'percentage' => max(0, min(100, $percentage)),
        ];
    }

    /**
     * Keep metadata bounded and non-personal.
     *
     * @param array $metadata Metadata.
     * @return array
     */
    private function normalise_metadata(array $metadata): array {
        $allowed = ['activitytype', 'attempt', 'completionstate', 'verb'];
        $result = [];
        foreach ($metadata as $key => $value) {
            if (!in_array($key, $allowed, true) || (!is_scalar($value) && $value !== null)) {
                throw new \invalid_parameter_exception('Gamification metadata contains an unsupported field.');
            }
            $result[$key] = is_string($value) ? mb_substr($value, 0, 80) : $value;
        }
        ksort($result);
        return $result;
    }
}
