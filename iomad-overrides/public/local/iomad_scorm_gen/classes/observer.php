<?php
// This file is part of Moodle - http://moodle.org/

namespace local_iomad_scorm_gen;

use local_global_events\local\gamification_service;
use local_global_events\local\tenant_scope;

/**
 * Convert trusted SCORM status submissions into idempotent rewards.
 *
 * @package local_iomad_scorm_gen
 */
final class observer {
    /**
     * Handle completed/passed statuses after core tracking and grade updates.
     *
     * @param \mod_scorm\event\status_submitted $event Event.
     */
    public static function status_submitted(\mod_scorm\event\status_submitted $event): void {
        try {
            $data = $event->get_data();
            $other = (array)$data['other'];
            $status = (string)$other['cmivalue'];
            $points = match ($status) {
                'completed' => 20,
                'passed' => 30,
                default => 0,
            };
            if ($points === 0) {
                return;
            }
            $span = class_exists('\tool_iomadmonitor\local\operation_span')
                ? new \tool_iomadmonitor\local\operation_span('learning.scorm_reward', [
                    'component' => 'local_iomad_scorm_gen',
                    'event' => $status,
                ])
                : null;
            $userid = (int)($data['relateduserid'] ?: $data['userid']);
            $courseid = (int)$data['courseid'];
            $cmid = (int)$data['contextinstanceid'];
            $identity = implode(':', [
                $userid,
                $cmid,
                (int)$other['attemptid'],
                (string)$other['cmielement'],
                $status,
            ]);
            (new gamification_service())->award(
                tenant_scope::for_learning_event($userid, $courseid),
                $userid,
                $points,
                'local_iomad_scorm_gen',
                'scorm.' . $status,
                'scorm-status:' . $identity,
                $courseid,
                $cmid,
                'xp',
                [
                    'activitytype' => 'scorm',
                    'attempt' => (int)$other['attemptid'],
                    'completionstate' => $status,
                ],
            );
            $span?->finish('ok');
        } catch (\Throwable $exception) {
            if (isset($span)) {
                $span?->finish('error');
            }
            if (class_exists('\tool_iomadmonitor\local\error_reporter')) {
                (new \tool_iomadmonitor\local\error_reporter())->report(
                    'scorm_bridge.reward_failed',
                    $exception,
                    ['component' => 'local_iomad_scorm_gen'],
                );
            } else {
                debugging('iomad_scorm_reward_failed', DEBUG_DEVELOPER);
            }
        }
    }
}
