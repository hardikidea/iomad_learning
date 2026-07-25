<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_tenantform\local;

/**
 * Evaluate conditional form fields consistently on the server.
 *
 * @package    mod_tenantform
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class condition_evaluator {
    /**
     * Is a field visible for the supplied normalised values?
     *
     * @param array $field Field definition.
     * @param array $values Current values.
     * @return bool
     */
    public static function is_visible(array $field, array $values): bool {
        if (empty($field['condition'])) {
            return true;
        }
        $condition = $field['condition'];
        $actual = (string)($values[$condition['field']] ?? '');
        $expected = (string)($condition['value'] ?? '');
        return match ($condition['operator']) {
            'equals' => $actual === $expected,
            'not_equals' => $actual !== $expected,
            'contains' => $expected !== '' && str_contains($actual, $expected),
            'empty' => $actual === '',
            'not_empty' => $actual !== '',
            default => false,
        };
    }
}
