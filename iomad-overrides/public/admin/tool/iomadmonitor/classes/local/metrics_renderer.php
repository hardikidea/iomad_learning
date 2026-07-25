<?php
// This file is part of Moodle - http://moodle.org/

namespace tool_iomadmonitor\local;

/**
 * Render bounded aggregate Prometheus metrics.
 *
 * @package tool_iomadmonitor
 */
final class metrics_renderer {
    /**
     * Render health and platform metadata without user or tenant labels.
     *
     * @param array $report Health report.
     * @return string
     */
    public function render(array $report): string {
        $lines = [
            '# HELP iomad_health_check Health state by allowlisted check.',
            '# TYPE iomad_health_check gauge',
        ];
        foreach ($report['checks'] ?? [] as $check) {
            $id = (string)($check['id'] ?? '');
            if (!preg_match('/^[a-z][a-z0-9_]{1,31}$/', $id)) {
                continue;
            }
            $status = (string)($check['status'] ?? 'fail');
            $value = $status === 'pass' ? 1 : ($status === 'warn' ? 0.5 : 0);
            $lines[] = sprintf('iomad_health_check{check="%s"} %s', $id, $value);
        }
        $lines[] = '# HELP iomad_health_check_duration_seconds Health-check execution duration.';
        $lines[] = '# TYPE iomad_health_check_duration_seconds gauge';
        foreach ($report['checks'] ?? [] as $check) {
            $id = (string)($check['id'] ?? '');
            if (!preg_match('/^[a-z][a-z0-9_]{1,31}$/', $id)) {
                continue;
            }
            $duration = max(0, (int)($check['durationms'] ?? 0)) / 1000;
            $lines[] = sprintf(
                'iomad_health_check_duration_seconds{check="%s"} %.3f',
                $id,
                $duration,
            );
        }
        $lines[] = '# HELP iomad_health_report Overall report state.';
        $lines[] = '# TYPE iomad_health_report gauge';
        $lines[] = 'iomad_health_report ' . (!empty($report['ok']) ? '1' : '0');
        $operational = [
            'cron' => [
                'iomad_cron_heartbeat_age_seconds',
                'Age of the latest recorded cron heartbeat.',
            ],
            'storage' => [
                'iomad_dataroot_free_percent',
                'Free dataroot capacity as a percentage.',
            ],
            'tasks' => [
                'iomad_failed_adhoc_tasks',
                'Current failed ad hoc task records.',
            ],
            'backup' => [
                'iomad_recovery_set_age_seconds',
                'Age of the latest verified matching recovery set.',
            ],
            'integrations' => [
                'iomad_integration_queue_problem_records',
                'Current failed or stale integration queue records.',
            ],
        ];
        foreach ($report['checks'] ?? [] as $check) {
            $id = (string)($check['id'] ?? '');
            if (!isset($operational[$id]) || !is_int($check['metric'] ?? null)) {
                continue;
            }
            [$name, $help] = $operational[$id];
            $lines[] = '# HELP ' . $name . ' ' . $help;
            $lines[] = '# TYPE ' . $name . ' gauge';
            $lines[] = $name . ' ' . max(0, $check['metric']);
        }
        $lines[] = '# HELP iomad_health_report_timestamp_seconds Last health report timestamp.';
        $lines[] = '# TYPE iomad_health_report_timestamp_seconds gauge';
        $lines[] = 'iomad_health_report_timestamp_seconds ' . (int)($report['generated'] ?? time());
        $lines[] = '# HELP iomad_exception_total Privacy-safe application exceptions by stable category.';
        $lines[] = '# TYPE iomad_exception_total counter';
        foreach (exception_counter::snapshot() as $category => $count) {
            $lines[] = sprintf(
                'iomad_exception_total{category="%s"} %d',
                $category,
                $count,
            );
        }
        return implode("\n", $lines) . "\n";
    }
}
