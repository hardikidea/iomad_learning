<?php
// This file is part of Moodle - http://moodle.org/

namespace tool_iomadmonitor;

use tool_iomadmonitor\local\correlation_id;
use tool_iomadmonitor\local\exception_category;
use tool_iomadmonitor\local\metrics_renderer;
use tool_iomadmonitor\local\noop_telemetry_exporter;
use tool_iomadmonitor\local\otlp_exporter;
use tool_iomadmonitor\local\problem_details;
use tool_iomadmonitor\local\redactor;
use tool_iomadmonitor\local\trace_context;

/**
 * Privacy and wire-contract tests.
 *
 * @package tool_iomadmonitor
 * @covers \tool_iomadmonitor\local\correlation_id
 * @covers \tool_iomadmonitor\local\exception_category
 * @covers \tool_iomadmonitor\local\exception_classifier
 * @covers \tool_iomadmonitor\local\metrics_renderer
 * @covers \tool_iomadmonitor\local\noop_telemetry_exporter
 * @covers \tool_iomadmonitor\local\otlp_exporter
 * @covers \tool_iomadmonitor\local\problem_details
 * @covers \tool_iomadmonitor\local\redactor
 * @covers \tool_iomadmonitor\local\trace_context
 */
final class operability_contract_test extends \basic_testcase {
    /**
     * Secrets and personal identifiers are removed recursively.
     */
    public function test_redacts_sensitive_context(): void {
        $clean = redactor::clean([
            'operation' => 'enrol',
            'token' => 'secret-value',
            'nested' => ['email' => 'learner@example.test'],
        ]);

        $this->assertSame('enrol', $clean['operation']);
        $this->assertSame('[redacted]', $clean['token']);
        $this->assertSame('[redacted]', $clean['nested']['email']);
    }

    /**
     * Public problems contain a request ID but no exception message.
     */
    public function test_problem_details_are_stable_and_non_sensitive(): void {
        correlation_id::reset();
        $problem = problem_details::from_exception(
            new \invalid_parameter_exception('do not expose'),
            correlation_id::get('request-12345678'),
        );

        $this->assertSame(422, $problem['status']);
        $this->assertSame('validation_error', $problem['code']);
        $this->assertSame('request-12345678', $problem['correlation_id']);
        $this->assertSame('request-12345678', $problem['request_id']);
        $this->assertStringNotContainsString('do not expose', json_encode($problem));
    }

    /**
     * Operational categories retain their documented HTTP and retry contracts.
     */
    public function test_exception_catalogue_has_required_status_contracts(): void {
        $expected = [
            'validation_error' => 422,
            'authentication_required' => 401,
            'authorisation_denied' => 403,
            'company_not_found' => 404,
            'licence_conflict' => 409,
            'rate_limited' => 429,
            'external_response_invalid' => 502,
            'database_unavailable' => 503,
            'external_timeout' => 504,
        ];
        foreach ($expected as $category => $status) {
            $definition = exception_category::definition($category);
            $this->assertSame($category, $definition['category']);
            $this->assertSame($status, $definition['status']);
        }
    }

    /**
     * Metrics expose only allowlisted aggregate labels.
     */
    public function test_metrics_drop_unbounded_check_ids(): void {
        $output = (new metrics_renderer())->render([
            'generated' => 123,
            'checks' => [
                ['id' => 'database', 'status' => 'pass'],
                ['id' => 'tenant@example.test', 'status' => 'fail'],
            ],
        ]);

        $this->assertStringContainsString('check="database"} 1', $output);
        $this->assertStringContainsString('iomad_health_check_duration_seconds', $output);
        $this->assertStringContainsString('iomad_exception_total', $output);
        $this->assertStringNotContainsString('tenant@example.test', $output);
    }

    /**
     * OTLP attributes drop personal and unbounded fields.
     */
    public function test_otlp_attributes_use_allowlist(): void {
        $attributes = (new otlp_exporter('https://collector.example.test'))->attributes([
            'component' => 'local_global_events',
            'user.id' => 99,
            'email' => 'learner@example.test',
        ]);
        $encoded = json_encode($attributes, JSON_THROW_ON_ERROR);

        $this->assertStringContainsString('local_global_events', $encoded);
        $this->assertStringNotContainsString('learner@example.test', $encoded);
        $this->assertStringNotContainsString('user.id', $encoded);
    }

    /**
     * Incoming W3C trace context is accepted only when IDs are valid.
     */
    public function test_trace_context_rejects_zero_ids_and_preserves_valid_trace(): void {
        $valid = trace_context::resolve(
            '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01',
        );
        $this->assertSame('4bf92f3577b34da6a3ce929d0e0e4736', $valid->traceid);
        $this->assertSame('00f067aa0ba902b7', $valid->parentspanid);
        $this->assertTrue($valid->sampled);

        $invalid = trace_context::resolve(
            '00-00000000000000000000000000000000-0000000000000000-01',
        );
        $this->assertNotSame(str_repeat('0', 32), $invalid->traceid);
        $this->assertSame('', $invalid->parentspanid);
    }

    /**
     * Disabled telemetry never affects application control flow.
     */
    public function test_noop_exporter_is_fail_open(): void {
        $exporter = new noop_telemetry_exporter();
        $this->assertFalse($exporter->log('application.test', 'INFO'));
        $this->assertFalse($exporter->span('application.test', 1, 2, 'ok'));
    }
}
