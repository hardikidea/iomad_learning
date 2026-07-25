<?php
// This file is part of Moodle - http://moodle.org/

namespace tool_iomadmonitor;

use tool_iomadmonitor\local\service_descriptor;
use tool_iomadmonitor\local\service_registry;
use tool_iomadmonitor\local\service_visibility_policy;

/**
 * Service registry tests.
 *
 * @package tool_iomadmonitor
 * @covers \tool_iomadmonitor\local\service_registry
 * @covers \tool_iomadmonitor\local\service_descriptor
 * @covers \tool_iomadmonitor\local\service_visibility_policy
 */
final class service_registry_test extends \basic_testcase {
    /**
     * Dependencies are returned before their consumers.
     */
    public function test_orders_valid_dependency_graph(): void {
        $registry = new service_registry();
        $registry->add(new service_descriptor('app.web', 'Web', 'test', 'runtime', 'critical', ['app.db']));
        $registry->add(new service_descriptor('app.db', 'Database', 'test', 'storage', 'critical'));

        $this->assertSame(['app.db', 'app.web'], array_map(
            static fn(service_descriptor $service): string => $service->id(),
            $registry->ordered(),
        ));
    }

    /**
     * Duplicate IDs are rejected.
     */
    public function test_rejects_duplicate_ids(): void {
        $registry = new service_registry();
        $registry->add(new service_descriptor('app.db', 'Database', 'test', 'storage', 'critical'));

        $this->expectException(\InvalidArgumentException::class);
        $registry->add(new service_descriptor('app.db', 'Other', 'test', 'storage', 'critical'));
    }

    /**
     * Missing dependencies are rejected.
     */
    public function test_rejects_missing_dependency(): void {
        $registry = new service_registry();
        $registry->add(new service_descriptor('app.web', 'Web', 'test', 'runtime', 'critical', ['app.db']));

        $this->expectException(\InvalidArgumentException::class);
        $registry->ordered();
    }

    /**
     * Cycles are rejected.
     */
    public function test_rejects_dependency_cycle(): void {
        $registry = new service_registry();
        $registry->add(new service_descriptor('app.one', 'One', 'test', 'runtime', 'critical', ['app.two']));
        $registry->add(new service_descriptor('app.two', 'Two', 'test', 'runtime', 'critical', ['app.one']));

        $this->expectException(\InvalidArgumentException::class);
        $registry->ordered();
    }

    /**
     * Operator records require authentication, capability, and company context.
     */
    public function test_visibility_policy_enforces_capability_and_scope(): void {
        $service = new service_descriptor(
            'app.tenant',
            'Tenant service',
            'test',
            'application',
            'important',
            metadata: [
                'visibility' => 'operator',
                'capability' => 'tool/iomadmonitor:view',
                'companyscope' => 'current',
            ],
        );
        $policy = new service_visibility_policy();

        $this->assertFalse($policy->can_view($service, false, [], false));
        $this->assertFalse($policy->can_view($service, true, ['tool/iomadmonitor:view'], false));
        $this->assertTrue($policy->can_view($service, true, ['tool/iomadmonitor:view'], true));
    }
}
