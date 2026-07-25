<?php
// This file is part of Moodle - http://moodle.org/

namespace tool_iomadmonitor\local;

/**
 * Central privacy-safe error reporting for repository-owned components.
 *
 * @package tool_iomadmonitor
 */
final class error_reporter {
    /** @var array<string, true> Exceptions already reported in this request. */
    private static array $reported = [];

    /**
     * Report without exposing messages, traces, or context values.
     *
     * @param string $event Stable event code.
     * @param \Throwable $exception Exception.
     * @param array $context Context.
     */
    public function report(string $event, \Throwable $exception, array $context = []): void {
        $fingerprint = $event . ':' . spl_object_id($exception);
        if (isset(self::$reported[$fingerprint])) {
            return;
        }
        self::$reported[$fingerprint] = true;
        $classification = exception_classifier::classify($exception);
        exception_counter::increment($classification['category']);
        $requestid = correlation_id::get();
        $safe = [
            'event' => $event,
            'category' => $classification['category'],
            'request_id' => $requestid,
            'context' => redactor::clean($context),
        ];
        debugging(
            (string)json_encode($safe, JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR),
            DEBUG_MINIMAL,
        );
        (new otlp_exporter())->log($event, 'ERROR', [
            'error.category' => $classification['category'],
            'request.id' => $requestid,
            'component' => (string)($context['component'] ?? ''),
            'event' => $event,
        ]);
    }

    /**
     * Reset request-local deduplication for tests.
     */
    public static function reset(): void {
        self::$reported = [];
    }
}
