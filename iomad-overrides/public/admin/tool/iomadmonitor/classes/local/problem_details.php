<?php
// This file is part of Moodle - http://moodle.org/

namespace tool_iomadmonitor\local;

/**
 * Minimal RFC 9457-shaped error response.
 *
 * @package tool_iomadmonitor
 */
final class problem_details {
    /**
     * Build a privacy-safe response.
     *
     * @param \Throwable $exception Exception.
     * @param string $requestid Correlation ID.
     * @param string|null $category Trusted operation-specific category.
     * @return array
     */
    public static function from_exception(
        \Throwable $exception,
        string $requestid,
        ?string $category = null,
    ): array {
        $classification = exception_classifier::classify($exception, $category);
        return [
            'type' => '/admin/tool/iomadmonitor/problems/' . $classification['category'],
            'title' => $classification['title'],
            'status' => $classification['status'],
            'code' => $classification['category'],
            'detail' => $classification['detail'],
            'retryable' => $classification['retryable'],
            'correlation_id' => $requestid,
            'request_id' => $requestid,
        ];
    }
}
