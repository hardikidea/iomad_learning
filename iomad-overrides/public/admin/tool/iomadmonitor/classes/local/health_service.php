<?php
// This file is part of Moodle - http://moodle.org/

namespace tool_iomadmonitor\local;

/**
 * Aggregate non-sensitive platform health checks.
 *
 * @package    tool_iomadmonitor
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class health_service {
    /**
     * Run health checks.
     *
     * @param bool $deep Include tenant-isolation audit.
     * @return array
     */
    public function run(bool $deep = false): array {
        $checks = [
            $this->timed(fn(): array => $this->database()),
            $this->timed(fn(): array => $this->cron()),
            $this->timed(fn(): array => $this->redis()),
            $this->timed(fn(): array => $this->storage()),
            $this->timed(fn(): array => $this->failed_tasks()),
            $this->timed(fn(): array => $this->backup()),
            $this->timed(fn(): array => $this->security()),
            $this->timed(fn(): array => $this->integration_queues()),
        ];
        if ($deep) {
            $checks[] = $this->timed(fn(): array => $this->tenant_isolation());
        }
        return $this->report('full', $checks);
    }

    /**
     * Run only synchronous dependencies required to serve normal traffic.
     *
     * @return array
     */
    public function readiness(): array {
        return $this->report('readiness', [
            $this->timed(fn(): array => $this->database()),
            $this->timed(fn(): array => $this->redis()),
            $this->timed(fn(): array => $this->storage()),
            $this->timed(fn(): array => $this->security()),
        ]);
    }

    /**
     * Validate immutable source metadata and the service dependency graph.
     *
     * @return array
     */
    public function startup(): array {
        return $this->report('startup', [
            $this->timed(fn(): array => $this->source_pin()),
            $this->timed(fn(): array => $this->service_graph()),
            $this->timed(fn(): array => $this->database()),
        ]);
    }

    /**
     * Database connectivity.
     *
     * @return array
     */
    private function database(): array {
        global $DB;

        try {
            $value = (int)$DB->get_field_sql('SELECT 1');
            return $this->result('database', 'Database', $value === 1 ? 'pass' : 'fail', 'PostgreSQL query');
        } catch (\Throwable) {
            return $this->result('database', 'Database', 'fail', 'PostgreSQL query failed');
        }
    }

    /**
     * Cron heartbeat.
     *
     * @return array
     */
    private function cron(): array {
        $last = (int)get_config('tool_task', 'lastcronstart');
        $age = $last > 0 ? time() - $last : 0;
        $maxage = (int)(get_config('tool_iomadmonitor', 'cronmaxage') ?: 600);
        if ($last === 0) {
            return $this->result('cron', 'Cron', 'warn', 'No cron heartbeat recorded', 0);
        }
        return $this->result(
            'cron',
            'Cron',
            $age <= $maxage ? 'pass' : 'fail',
            'Heartbeat age in seconds',
            $age,
        );
    }

    /**
     * Redis session store.
     *
     * @return array
     */
    private function redis(): array {
        global $CFG;

        if (($CFG->session_handler_class ?? '') !== '\core\session\redis') {
            return $this->result('redis', 'Redis sessions', 'fail', 'Redis session handler is not configured');
        }
        if (!class_exists('\Redis')) {
            return $this->result('redis', 'Redis sessions', 'fail', 'PHP Redis extension is unavailable');
        }
        try {
            $redis = new \Redis();
            $host = (string)$CFG->session_redis_host;
            if (!empty($CFG->session_redis_encrypt) && !str_starts_with($host, 'tls://')) {
                $host = 'tls://' . $host;
            }
            $connected = $redis->connect(
                $host,
                (int)($CFG->session_redis_port ?? 6379),
                min(2.0, (float)($CFG->session_redis_connection_timeout ?? 2)),
            );
            $pong = $connected ? $redis->ping() : false;
            $redis->close();
            return $this->result(
                'redis',
                'Redis sessions',
                $pong !== false ? 'pass' : 'fail',
                'Redis ping',
            );
        } catch (\Throwable) {
            return $this->result('redis', 'Redis sessions', 'fail', 'Redis ping failed');
        }
    }

    /**
     * Dataroot free space.
     *
     * @return array
     */
    private function storage(): array {
        global $CFG;

        $total = @disk_total_space($CFG->dataroot);
        $free = @disk_free_space($CFG->dataroot);
        if (!is_float($total) || !is_float($free) || $total <= 0) {
            return $this->result('storage', 'Dataroot storage', 'warn', 'Free space is unavailable');
        }
        $percent = (int)floor($free * 100 / $total);
        $minimum = max(1, (int)(get_config('tool_iomadmonitor', 'minfreedisk') ?: 10));
        return $this->result(
            'storage',
            'Dataroot storage',
            $percent >= $minimum ? 'pass' : 'fail',
            'Free percent',
            $percent,
        );
    }

    /**
     * Failed adhoc tasks.
     *
     * @return array
     */
    private function failed_tasks(): array {
        global $DB;

        $count = $DB->count_records_select('task_adhoc', 'faildelay > 0');
        return $this->result(
            'tasks',
            'Background jobs',
            $count === 0 ? 'pass' : 'fail',
            'Failed adhoc tasks',
            $count,
        );
    }

    /**
     * Matching recovery-set freshness.
     *
     * @return array
     */
    private function backup(): array {
        $path = getenv('IOMAD_BACKUP_STATUS_FILE') ?: '/var/backups/iomad/latest.env';
        if (!is_readable($path)) {
            return $this->result('backup', 'Recovery set', 'warn', 'No verified recovery-set status file');
        }
        $status = parse_ini_file($path, false, INI_SCANNER_RAW);
        $created = (int)($status['CREATED_EPOCH'] ?? 0);
        $age = $created > 0 ? time() - $created : PHP_INT_MAX;
        $maxage = (int)(get_config('tool_iomadmonitor', 'backupmaxage') ?: 86400);
        $valid = ($status['STATUS'] ?? '') === 'complete'
            && preg_match('/^[a-f0-9]{40}$/', (string)($status['IOMAD_COMMIT'] ?? ''))
            && preg_match('/^[a-f0-9]{64}$/', (string)($status['MANIFEST_SHA256'] ?? ''));
        return $this->result(
            'backup',
            'Recovery set',
            $valid && $age <= $maxage ? 'pass' : 'fail',
            'Verified recovery-set age in seconds',
            $age === PHP_INT_MAX ? 0 : $age,
        );
    }

    /**
     * Security posture.
     *
     * @return array
     */
    private function security(): array {
        global $CFG;

        $local = (getenv('IOMAD_ENVIRONMENT') ?: '') === 'local';
        $https = str_starts_with((string)$CFG->wwwroot, 'https://');
        $safe = (int)$CFG->debug === DEBUG_NONE
            && empty($CFG->debugdisplay)
            && ($https || $local)
            && !str_starts_with(realpath($CFG->dataroot) ?: $CFG->dataroot, realpath($CFG->dirroot) ?: $CFG->dirroot);
        return $this->result(
            'security',
            'Runtime security',
            $safe ? 'pass' : 'fail',
            'Debug, HTTPS, and dataroot boundary',
        );
    }

    /**
     * Commerce and connector queue state.
     *
     * @return array
     */
    private function integration_queues(): array {
        global $DB;

        $failed = 0;
        $stale = 0;
        $manager = $DB->get_manager();
        if ($manager->table_exists('local_iomadconnect_event')) {
            $failed += $DB->count_records('local_iomadconnect_event', ['status' => 'failed']);
        }
        if ($manager->table_exists('local_iomadcommerce_order')) {
            $stale += $DB->count_records_select(
                'local_iomadcommerce_order',
                'status = :status AND timecreated < :cutoff',
                ['status' => 'pending', 'cutoff' => time() - HOURSECS],
            );
        }
        if ($manager->table_exists('local_ge_message')) {
            $failed += $DB->count_records('local_ge_message', ['status' => 'failed']);
            $stale += $DB->count_records_select(
                'local_ge_message',
                'status = :status AND nextattempt < :cutoff',
                ['status' => 'pending', 'cutoff' => time() - HOURSECS],
            );
        }
        return $this->result(
            'integrations',
            'Integration queues',
            $failed === 0 && $stale === 0 ? 'pass' : 'fail',
            'Failed or stale records',
            $failed + $stale,
        );
    }

    /**
     * Deep tenant boundary audit.
     *
     * @return array
     */
    private function tenant_isolation(): array {
        if (!class_exists('\local_institutionpack\tenant_auditor')) {
            return $this->result('isolation', 'Tenant isolation', 'fail', 'Isolation auditor is unavailable');
        }
        try {
            $report = (new \local_institutionpack\tenant_auditor())->run(10, false);
            $anomalies = array_sum(array_map(
                static fn(array $check): int => (int)($check['anomalies'] ?? 0),
                (array)($report['checks'] ?? []),
            ));
            return $this->result(
                'isolation',
                'Tenant isolation',
                !empty($report['ok']) ? 'pass' : 'fail',
                'Aggregate anomalies',
                $anomalies,
            );
        } catch (\Throwable) {
            return $this->result('isolation', 'Tenant isolation', 'fail', 'Isolation audit failed');
        }
    }

    /**
     * Immutable source metadata.
     *
     * @return array
     */
    private function source_pin(): array {
        $expected = getenv('IOMAD_COMMIT') ?: '';
        $path = dirname(__DIR__, 6) . '/.iomad-source.env';
        $actual = '';
        if (is_readable($path)) {
            $metadata = parse_ini_file($path, false, INI_SCANNER_RAW);
            $actual = (string)($metadata['IOMAD_COMMIT'] ?? '');
        }
        $valid = preg_match('/^[a-f0-9]{40}$/', $actual)
            && ($expected === '' || hash_equals($expected, $actual));
        return $this->result(
            'source_pin',
            'Immutable IOMAD source',
            $valid ? 'pass' : 'fail',
            $valid ? 'Pinned source metadata verified' : 'Pinned source metadata is missing or mismatched',
        );
    }

    /**
     * Service dependency graph validity.
     *
     * @return array
     */
    private function service_graph(): array {
        try {
            $count = count(service_catalogue::build()->ordered());
            return $this->result('service_graph', 'Service registry', 'pass', 'Validated service count', $count);
        } catch (\Throwable) {
            return $this->result('service_graph', 'Service registry', 'fail', 'Service dependency graph is invalid');
        }
    }

    /**
     * Build a stable aggregate report.
     *
     * @param string $contract Contract name.
     * @param array $checks Checks.
     * @return array
     */
    private function report(string $contract, array $checks): array {
        $statuses = array_column($checks, 'status');
        $ok = !in_array('fail', $statuses, true);
        $state = $ok
            ? (in_array('warn', $statuses, true) ? 'degraded' : 'healthy')
            : 'unhealthy';
        return [
            'contract' => $contract,
            'ok' => $ok,
            'status' => $state,
            'generated' => time(),
            'checks' => $checks,
        ];
    }

    /**
     * Add a bounded duration to one check without changing its result contract.
     *
     * @param callable $check Check callback.
     * @return array
     */
    private function timed(callable $check): array {
        $started = hrtime(true);
        $result = $check();
        $result['durationms'] = max(0, (int)round((hrtime(true) - $started) / 1000000));
        return $result;
    }

    /**
     * Build one status record.
     *
     * @param string $id ID.
     * @param string $label Label.
     * @param string $status Status.
     * @param string $summary Summary.
     * @param int|null $metric Aggregate metric.
     * @return array
     */
    private function result(
        string $id,
        string $label,
        string $status,
        string $summary,
        ?int $metric = null,
    ): array {
        return compact('id', 'label', 'status', 'summary', 'metric');
    }
}
