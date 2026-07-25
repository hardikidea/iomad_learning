<?php
// This file is part of Moodle - http://moodle.org/

namespace tool_iomadmonitor\local;

/**
 * Throttled health-alert delivery.
 *
 * @package    tool_iomadmonitor
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class alert_service {
    /**
     * Deliver a changed failure state to explicitly authorized recipients.
     *
     * @param array $report Health report.
     */
    public function deliver(array $report): void {
        $failures = array_values(array_filter(
            $report['checks'] ?? [],
            static fn(array $check): bool => ($check['status'] ?? '') === 'fail',
        ));
        if (!$failures) {
            set_config('lastfingerprint', '', 'tool_iomadmonitor');
            return;
        }
        $fingerprint = hash('sha256', json_encode(array_column($failures, 'id'), JSON_THROW_ON_ERROR));
        $lastfingerprint = (string)get_config('tool_iomadmonitor', 'lastfingerprint');
        $lastalert = (int)get_config('tool_iomadmonitor', 'lastalert');
        $cooldown = (int)(get_config('tool_iomadmonitor', 'alertcooldown') ?: 3600);
        if ($fingerprint === $lastfingerprint && time() - $lastalert < $cooldown) {
            return;
        }
        $lines = [get_string('alertbody', 'tool_iomadmonitor')];
        foreach ($failures as $failure) {
            $lines[] = $failure['label'] . ': ' . $failure['summary'];
        }
        $context = \context_system::instance();
        $users = get_users_by_capability(
            $context,
            'tool/iomadmonitor:receivealerts',
            'u.*',
            'u.id ASC',
            '',
            '',
            '',
            '',
            false,
        );
        foreach ($users as $user) {
            email_to_user(
                $user,
                \core_user::get_noreply_user(),
                get_string('alertsubject', 'tool_iomadmonitor'),
                implode(PHP_EOL, $lines),
            );
        }
        set_config('lastfingerprint', $fingerprint, 'tool_iomadmonitor');
        set_config('lastalert', time(), 'tool_iomadmonitor');
    }
}
