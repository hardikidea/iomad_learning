<?php
// This file is part of Moodle - http://moodle.org/

namespace tool_iomadmonitor\local;

/**
 * Map exceptions to stable public categories and HTTP status codes.
 *
 * @package tool_iomadmonitor
 */
final class exception_classifier {
    /**
     * Classify an exception without exposing its message.
     *
     * @param \Throwable $exception Exception.
     * @param string|null $category Trusted operation-specific category.
     * @return array
     */
    public static function classify(\Throwable $exception, ?string $category = null): array {
        if ($category !== null) {
            return exception_category::definition($category);
        }
        if ($exception instanceof \required_capability_exception) {
            return exception_category::definition('authorisation_denied');
        }
        if ($exception instanceof \invalid_parameter_exception) {
            return exception_category::definition('validation_error');
        }
        if ($exception instanceof \dml_exception) {
            return exception_category::definition('database_unavailable');
        }
        if ($exception instanceof \moodle_exception) {
            return self::moodle_category($exception);
        }
        return exception_category::definition('internal_error');
    }

    /**
     * Classify known core exception codes without exposing parameters.
     *
     * @param \moodle_exception $exception Exception.
     * @return array
     */
    private static function moodle_category(\moodle_exception $exception): array {
        $notfound = [
            'invalidcourseid', 'invalidrecord', 'missingcourse', 'nosuchcourse',
            'usernotfound', 'unknownuser',
        ];
        if (in_array($exception->errorcode, $notfound, true)) {
            return exception_category::definition('resource_not_found');
        }
        if (in_array($exception->errorcode, ['requireloginerror', 'sessionerroruser'], true)) {
            return exception_category::definition('authentication_required');
        }
        if (in_array($exception->errorcode, ['nopermissions', 'notlocalisederrormessage'], true)) {
            return exception_category::definition('authorisation_denied');
        }
        return exception_category::definition('internal_error');
    }
}
