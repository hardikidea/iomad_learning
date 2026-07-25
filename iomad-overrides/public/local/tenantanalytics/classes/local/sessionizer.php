<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantanalytics\local;

/**
 * Deterministic active-time estimator for standard-log events.
 *
 * Consecutive events contribute their elapsed seconds capped at the configured
 * inactivity gap. The last event contributes zero seconds.
 *
 * @package    local_tenantanalytics
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class sessionizer {
    /**
     * Create active-time estimator.
     *
     * @param int $gapcap Maximum seconds credited between events.
     */
    public function __construct(private readonly int $gapcap = 1800) {
        if ($this->gapcap < 60 || $this->gapcap > 14400) {
            throw new \invalid_parameter_exception('Session gap must be between 60 and 14400 seconds.');
        }
    }

    /**
     * Aggregate time by scalar event dimensions.
     *
     * @param array $events Events with timecreated and dimension keys.
     * @param string[] $dimensions Grouping fields.
     * @return array<string,array{seconds:int,events:int,first:int,last:int}>
     */
    public function aggregate(array $events, array $dimensions): array {
        $groups = [];
        foreach ($events as $event) {
            $parts = [];
            foreach ($dimensions as $dimension) {
                $parts[] = (string)($event[$dimension] ?? 0);
            }
            $key = implode(':', $parts);
            $groups[$key][] = (int)$event['timecreated'];
        }

        $result = [];
        foreach ($groups as $key => $times) {
            sort($times, SORT_NUMERIC);
            $seconds = 0;
            for ($i = 0; $i < count($times) - 1; $i++) {
                $delta = max(0, $times[$i + 1] - $times[$i]);
                $seconds += min($delta, $this->gapcap);
            }
            $result[$key] = [
                'seconds' => $seconds,
                'events' => count($times),
                'first' => reset($times),
                'last' => end($times),
            ];
        }
        return $result;
    }
}
