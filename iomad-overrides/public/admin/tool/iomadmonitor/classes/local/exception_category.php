<?php
// This file is part of Moodle - http://moodle.org/

namespace tool_iomadmonitor\local;

/**
 * Stable privacy-safe exception catalogue.
 *
 * @package tool_iomadmonitor
 */
final class exception_category {
    /** @var array<string, array<string, mixed>> Category definitions. */
    private const DEFINITIONS = [
        'validation_error' => [422, 'warning', false, true, 'Validation failed'],
        'malformed_request' => [400, 'warning', false, true, 'Malformed request'],
        'authentication_required' => [401, 'warning', false, true, 'Authentication required'],
        'authorisation_denied' => [403, 'warning', false, true, 'Access denied'],
        'company_access_denied' => [403, 'warning', false, true, 'Company access denied'],
        'tenant_resolution_failed' => [403, 'error', false, true, 'Tenant could not be resolved'],
        'resource_not_found' => [404, 'warning', false, true, 'Resource not found'],
        'company_not_found' => [404, 'warning', false, true, 'Company not found'],
        'user_not_found' => [404, 'warning', false, true, 'User not found'],
        'course_not_available' => [404, 'warning', false, true, 'Course not available'],
        'method_not_allowed' => [405, 'warning', false, true, 'Method not allowed'],
        'licence_unavailable' => [409, 'warning', false, true, 'Licence unavailable'],
        'licence_expired' => [409, 'warning', false, true, 'Licence expired'],
        'licence_conflict' => [409, 'warning', false, true, 'Licence conflict'],
        'enrolment_failed' => [409, 'error', false, true, 'Enrolment failed'],
        'payment_rejected' => [409, 'warning', false, true, 'Payment rejected'],
        'unsupported_media_type' => [415, 'warning', false, true, 'Unsupported media type'],
        'rate_limited' => [429, 'warning', true, true, 'Too many requests'],
        'completion_processing_failed' => [500, 'error', true, false, 'Completion processing failed'],
        'compliance_processing_failed' => [500, 'error', true, false, 'Compliance processing failed'],
        'report_generation_failed' => [500, 'error', true, false, 'Report generation failed'],
        'sso_configuration_error' => [500, 'error', false, false, 'SSO configuration error'],
        'scheduled_task_failed' => [500, 'error', true, false, 'Scheduled task failed'],
        'configuration_error' => [500, 'critical', false, false, 'Configuration error'],
        'internal_error' => [500, 'critical', false, false, 'Internal error'],
        'database_unavailable' => [503, 'critical', true, false, 'Database unavailable'],
        'identity_provider_unavailable' => [503, 'error', true, true, 'Identity provider unavailable'],
        'payment_provider_unavailable' => [503, 'error', true, true, 'Payment provider unavailable'],
        'external_dependency_failed' => [503, 'error', true, true, 'External dependency unavailable'],
        'external_response_invalid' => [502, 'error', true, true, 'Invalid dependency response'],
        'external_timeout' => [504, 'error', true, true, 'External dependency timed out'],
    ];

    /**
     * Return one validated definition.
     *
     * @param string $category Stable category.
     * @return array
     */
    public static function definition(string $category): array {
        if (!isset(self::DEFINITIONS[$category])) {
            $category = 'internal_error';
        }
        [$status, $severity, $retryable, $visible, $title] = self::DEFINITIONS[$category];
        return [
            'category' => $category,
            'status' => $status,
            'severity' => $severity,
            'retryable' => $retryable,
            'visible' => $visible,
            'expected' => $status < 500,
            'title' => $title,
            'detail' => $visible
                ? 'The request could not be completed. Review the request and your access.'
                : 'The request could not be completed.',
            'metric' => 'iomad_exception_total',
            'alert' => $status >= 500 ? 'IomadApplicationErrors' : '',
            'runbook' => self::runbook($category),
        ];
    }

    /**
     * Return all definitions for documentation and contract tests.
     *
     * @return array
     */
    public static function all(): array {
        $definitions = [];
        foreach (array_keys(self::DEFINITIONS) as $category) {
            $definitions[$category] = self::definition($category);
        }
        return $definitions;
    }

    /**
     * Resolve a category to an operational runbook.
     *
     * @param string $category Category.
     * @return string
     */
    private static function runbook(string $category): string {
        if ($category === 'database_unavailable') {
            return 'docs/12-runbooks/database-unavailable.md';
        }
        if (str_contains($category, 'payment')) {
            return 'docs/12-runbooks/payment-failure.md';
        }
        if (str_contains($category, 'licence')) {
            return 'docs/12-runbooks/licence-failure.md';
        }
        if ($category === 'enrolment_failed') {
            return 'docs/12-runbooks/enrolment-failure.md';
        }
        if ($category === 'report_generation_failed') {
            return 'docs/12-runbooks/report-failure.md';
        }
        if (
            str_contains($category, 'tenant_')
            || str_contains($category, 'company_')
        ) {
            return 'docs/12-runbooks/tenant-resolution-failure.md';
        }
        if (
            str_contains($category, 'identity_provider')
            || str_contains($category, 'sso_')
        ) {
            return 'docs/12-runbooks/sso-failure.md';
        }
        if ($category === 'scheduled_task_failed') {
            return 'docs/12-runbooks/scheduled-task-failure.md';
        }
        if (
            str_contains($category, 'external')
        ) {
            return 'docs/12-runbooks/external-integration-failure.md';
        }
        return 'docs/incident-runbook.md';
    }
}
