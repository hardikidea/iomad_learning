<?php
// This file is part of Moodle - http://moodle.org/

namespace tool_iomadmonitor;

use tool_iomadmonitor\local\health_service;

/**
 * Aggregate site-health tests.
 *
 * @package    tool_iomadmonitor
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_iomadmonitor\local\health_service
 */
final class health_service_test extends \advanced_testcase {
    /**
     * The report has a stable non-personal contract.
     */
    public function test_report_contract_contains_operational_checks(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $report = (new health_service())->run(false);
        $ids = array_column($report['checks'], 'id');

        $this->assertArrayHasKey('ok', $report);
        $this->assertContains('database', $ids);
        $this->assertContains('cron', $ids);
        $this->assertContains('redis', $ids);
        $this->assertContains('storage', $ids);
        $this->assertContains('tasks', $ids);
        $this->assertContains('backup', $ids);
        $this->assertContains('security', $ids);
        $this->assertContains('integrations', $ids);
        foreach ($report['checks'] as $check) {
            $this->assertContains($check['status'], ['pass', 'warn', 'fail']);
            $this->assertArrayHasKey('metric', $check);
            $this->assertArrayHasKey('durationms', $check);
            $this->assertGreaterThanOrEqual(0, $check['durationms']);
        }
    }

    /**
     * Deep mode includes the tenant isolation audit.
     */
    public function test_deep_report_includes_tenant_isolation(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $report = (new health_service())->run(true);

        $this->assertContains('isolation', array_column($report['checks'], 'id'));
    }
}
