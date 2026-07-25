<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\local;

/**
 * Deterministic JSON and hashing helpers.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class json {
    /**
     * Encode data with deterministic object key ordering.
     *
     * @param mixed $value Data.
     * @return string
     */
    public static function encode(mixed $value): string {
        $normalised = self::normalise($value);
        return json_encode(
            $normalised,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    /**
     * Decode a JSON object.
     *
     * @param string $value JSON.
     * @return array<string, mixed>
     */
    public static function decode_object(string $value): array {
        if ($value === '') {
            return [];
        }
        $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new \invalid_parameter_exception('Expected a JSON object.');
        }
        return $decoded;
    }

    /**
     * Calculate a stable SHA-256 hash.
     *
     * @param mixed $value Data.
     * @return string
     */
    public static function hash(mixed $value): string {
        return hash('sha256', self::encode($value));
    }

    /**
     * Normalise recursively.
     *
     * @param mixed $value Data.
     * @return mixed
     */
    private static function normalise(mixed $value): mixed {
        if (is_object($value)) {
            $value = get_object_vars($value);
        }
        if (!is_array($value)) {
            return $value;
        }
        if (!array_is_list($value)) {
            ksort($value, SORT_STRING);
        }
        foreach ($value as $key => $item) {
            $value[$key] = self::normalise($item);
        }
        return $value;
    }
}
