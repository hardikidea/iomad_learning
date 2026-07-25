<?php
// This file is part of Moodle - http://moodle.org/

namespace local_iomad_h5p_bridge;

use local_global_events\local\gamification_service;
use local_global_events\local\tenant_scope;

/**
 * Convert validated core H5P statements into idempotent rewards.
 *
 * @package local_iomad_h5p_bridge
 */
final class observer {
    /**
     * Handle the event after Moodle's H5P handler has validated and stored it.
     *
     * @param \mod_h5pactivity\event\statement_received $event Event.
     */
    public static function statement_received(\mod_h5pactivity\event\statement_received $event): void {
        try {
            $data = $event->get_data();
            $other = (array)($data['other'] ?? []);
            $verbid = (string)($other['verb']['id'] ?? '');
            $verb = strtolower((string)basename(parse_url($verbid, PHP_URL_PATH) ?: ''));
            $success = $other['result']['success'] ?? null;
            if ($verb === 'answered' && $success !== true) {
                return;
            }
            $points = match ($verb) {
                'answered' => 5,
                'completed' => 10,
                default => 0,
            };
            if ($points === 0) {
                return;
            }
            $span = class_exists('\tool_iomadmonitor\local\operation_span')
                ? new \tool_iomadmonitor\local\operation_span('learning.h5p_reward', [
                    'component' => 'local_iomad_h5p_bridge',
                    'event' => $verb,
                ])
                : null;
            $userid = (int)$data['userid'];
            $courseid = (int)$data['courseid'];
            $cmid = (int)$data['contextinstanceid'];
            $identity = (string)($data['id'] ?? '');
            if ($identity === '') {
                $identity = hash('sha256', json_encode([
                    'userid' => $userid,
                    'cmid' => $cmid,
                    'other' => $other,
                    'timecreated' => $data['timecreated'] ?? 0,
                ], JSON_THROW_ON_ERROR));
            }
            (new gamification_service())->award(
                tenant_scope::for_learning_event($userid, $courseid),
                $userid,
                $points,
                'local_iomad_h5p_bridge',
                'h5p.' . $verb,
                'h5p-statement:' . $identity,
                $courseid,
                $cmid,
                'xp',
                ['activitytype' => 'h5p', 'verb' => $verb],
            );
            $span?->finish('ok');
        } catch (\Throwable $exception) {
            if (isset($span)) {
                $span?->finish('error');
            }
            if (class_exists('\tool_iomadmonitor\local\error_reporter')) {
                (new \tool_iomadmonitor\local\error_reporter())->report(
                    'h5p_bridge.reward_failed',
                    $exception,
                    ['component' => 'local_iomad_h5p_bridge'],
                );
            } else {
                debugging('iomad_h5p_reward_failed', DEBUG_DEVELOPER);
            }
        }
    }
}
