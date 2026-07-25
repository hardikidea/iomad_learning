<?php
// This file is part of Moodle - http://moodle.org/

namespace tool_iomadmonitor\local;

/**
 * Small fail-open OTLP/HTTP JSON exporter for project-owned operations.
 *
 * This is intentionally bounded and does not replace a full auto-instrumented
 * OpenTelemetry SDK.
 *
 * @package tool_iomadmonitor
 */
final class otlp_exporter implements telemetry_exporter_interface {
    /** @var string[] Attribute allowlist. */
    private const ATTRIBUTE_ALLOWLIST = [
        'component', 'deployment.environment', 'error.category', 'event',
        'operation', 'request.id', 'service.name', 'status',
    ];

    /** @var string OTLP base endpoint. */
    private string $endpoint;

    /**
     * Constructor.
     *
     * @param string|null $endpoint Base OTLP endpoint.
     */
    public function __construct(?string $endpoint = null) {
        $this->endpoint = rtrim($endpoint ?? (getenv('OTEL_EXPORTER_OTLP_ENDPOINT') ?: ''), '/');
    }

    /**
     * Whether export is securely configured.
     *
     * @return bool
     */
    public function enabled(): bool {
        if (!filter_var($this->endpoint, FILTER_VALIDATE_URL)) {
            return false;
        }
        $local = (getenv('IOMAD_ENVIRONMENT') ?: '') === 'local';
        return str_starts_with($this->endpoint, 'https://')
            || ($local && str_starts_with($this->endpoint, 'http://'));
    }

    /**
     * Export one sanitized log record.
     *
     * @param string $event Stable event code.
     * @param string $severity Severity.
     * @param array $attributes Bounded attributes.
     * @return bool
     */
    public function log(string $event, string $severity, array $attributes = []): bool {
        $severity = strtoupper($severity);
        if (
            !preg_match('/^[a-z][a-z0-9_.-]{2,80}$/', $event)
            || !in_array($severity, ['DEBUG', 'INFO', 'WARN', 'ERROR'], true)
        ) {
            return false;
        }
        $record = [
            'timeUnixNano' => (string)((int)(microtime(true) * 1000000000)),
            'severityText' => $severity,
            'body' => ['stringValue' => $event],
            'attributes' => $this->attributes($attributes),
        ];
        return $this->send('/v1/logs', [
            'resourceLogs' => [[
                'resource' => ['attributes' => $this->resource_attributes()],
                'scopeLogs' => [['scope' => ['name' => 'tool_iomadmonitor'], 'logRecords' => [$record]]],
            ]],
        ]);
    }

    /**
     * Export one completed operation span.
     *
     * @param string $operation Operation.
     * @param int $startnano Start time.
     * @param int $endnano End time.
     * @param string $status ok or error.
     * @param array $attributes Bounded attributes.
     * @return bool
     */
    public function span(
        string $operation,
        int $startnano,
        int $endnano,
        string $status,
        array $attributes = [],
    ): bool {
        if (
            !preg_match('/^[a-z][a-z0-9_.-]{2,80}$/', $operation)
            || !in_array($status, ['ok', 'error'], true)
            || $startnano <= 0
            || $endnano < $startnano
        ) {
            return false;
        }
        $attributes['operation'] = $operation;
        $attributes['status'] = $status;
        $context = trace_context::resolve();
        $spanid = bin2hex(random_bytes(8));
        $span = [
            'traceId' => $context->traceid,
            'spanId' => $spanid,
            'name' => $operation,
            'kind' => 1,
            'startTimeUnixNano' => (string)$startnano,
            'endTimeUnixNano' => (string)$endnano,
            'attributes' => $this->attributes($attributes),
            'status' => ['code' => $status === 'ok' ? 1 : 2],
        ];
        if ($context->parentspanid !== '') {
            $span['parentSpanId'] = $context->parentspanid;
        }
        return $this->send('/v1/traces', [
            'resourceSpans' => [[
                'resource' => ['attributes' => $this->resource_attributes()],
                'scopeSpans' => [[
                    'scope' => ['name' => 'tool_iomadmonitor'],
                    'spans' => [$span],
                ]],
            ]],
        ]);
    }

    /**
     * Allowlisted attributes.
     *
     * @param array $attributes Input.
     * @return array
     */
    public function attributes(array $attributes): array {
        $result = [];
        foreach ($attributes as $key => $value) {
            if (!in_array($key, self::ATTRIBUTE_ALLOWLIST, true) || !is_scalar($value)) {
                continue;
            }
            $result[] = [
                'key' => $key,
                'value' => ['stringValue' => mb_substr((string)$value, 0, 120)],
            ];
        }
        return $result;
    }

    /**
     * Resource attributes.
     *
     * @return array
     */
    private function resource_attributes(): array {
        return $this->attributes([
            'service.name' => 'iomad-learning',
            'deployment.environment' => getenv('IOMAD_ENVIRONMENT') ?: 'unknown',
        ]);
    }

    /**
     * Send with a short timeout and no exception propagation.
     *
     * @param string $path OTLP path.
     * @param array $payload Payload.
     * @return bool
     */
    private function send(string $path, array $payload): bool {
        if (!$this->enabled() || !function_exists('curl_init')) {
            return false;
        }
        $handle = curl_init($this->endpoint . $path);
        if ($handle === false) {
            return false;
        }
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT_MS => 100,
            CURLOPT_TIMEOUT_MS => 250,
            CURLOPT_NOSIGNAL => true,
        ]);
        try {
            curl_exec($handle);
            $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            return $status >= 200 && $status < 300;
        } catch (\Throwable) {
            return false;
        } finally {
            curl_close($handle);
        }
    }
}
