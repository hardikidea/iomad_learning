<?php
// This file is part of Moodle - http://moodle.org/

namespace local_global_events;

use local_global_events\local\gamification_service;
use local_global_events\local\tenant_scope;

/**
 * Trusted Moodle event adapters.
 *
 * @package local_global_events
 */
final class observer {
    /**
     * Quiz submitted.
     *
     * @param \mod_quiz\event\attempt_submitted $event Event.
     */
    public static function quiz_submitted(\mod_quiz\event\attempt_submitted $event): void {
        self::award($event, 10, 'quiz');
    }

    /**
     * Assignment submitted.
     *
     * @param \mod_assign\event\assessable_submitted $event Event.
     */
    public static function assignment_submitted(\mod_assign\event\assessable_submitted $event): void {
        self::award($event, 10, 'assign');
    }

    /**
     * Course completed.
     *
     * @param \core\event\course_completed $event Event.
     */
    public static function course_completed(\core\event\course_completed $event): void {
        self::award($event, 50, 'course');
    }

    /**
     * Fail-open adapter: a reward outage must not break learning state.
     *
     * @param \core\event\base $event Event.
     * @param int $points Points.
     * @param string $activitytype Activity type.
     */
    private static function award(\core\event\base $event, int $points, string $activitytype): void {
        $span = class_exists('\tool_iomadmonitor\local\operation_span')
            ? new \tool_iomadmonitor\local\operation_span('learning.reward', [
                'component' => 'local_global_events',
                'event' => $activitytype,
            ])
            : null;
        try {
            $data = $event->get_data();
            $userid = (int)($data['relateduserid'] ?: $data['userid']);
            $courseid = (int)($data['courseid'] ?? 0);
            $cmid = ($data['contextlevel'] ?? 0) === CONTEXT_MODULE
                ? (int)($data['contextinstanceid'] ?? 0)
                : 0;
            $eventidentity = (string)($data['id'] ?? '');
            if ($eventidentity === '') {
                $eventidentity = implode(':', [
                    $data['eventname'] ?? '',
                    $data['objectid'] ?? 0,
                    $data['timecreated'] ?? 0,
                    $userid,
                ]);
            }
            (new gamification_service())->award(
                tenant_scope::for_learning_event($userid, $courseid),
                $userid,
                $points,
                'local_global_events',
                (string)$data['eventname'],
                'moodle-event:' . $eventidentity,
                $courseid,
                $cmid,
                'xp',
                ['activitytype' => $activitytype],
            );
            $span?->finish('ok');
        } catch (\Throwable $exception) {
            $span?->finish('error');
            if (class_exists('\tool_iomadmonitor\local\error_reporter')) {
                (new \tool_iomadmonitor\local\error_reporter())->report(
                    'global_events.reward_failed',
                    $exception,
                    ['component' => 'local_global_events'],
                );
            } else {
                debugging('global_events_reward_failed', DEBUG_DEVELOPER);
            }
        }
    }
}
