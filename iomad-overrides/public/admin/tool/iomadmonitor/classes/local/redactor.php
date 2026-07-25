<?php
// This file is part of Moodle - http://moodle.org/

namespace tool_iomadmonitor\local;

/**
 * Redact secrets and direct personal identifiers before logging.
 *
 * @package tool_iomadmonitor
 */
final class redactor {
    /** @var string[] Sensitive key fragments. */
    private const SECRET_KEYS = [
        'authorization', 'cookie', 'password', 'passwd', 'secret', 'sesskey',
        'token', 'webhook', 'email', 'phone', 'mobile', 'content', 'response',
    ];

    /**
     * Redact a bounded nested payload.
     *
     * @param mixed $value Value.
     * @param int $depth Current depth.
     * @return mixed
     */
    public static function clean(mixed $value, int $depth = 0): mixed {
        if ($depth > 5) {
            return '[truncated]';
        }
        if (is_array($value)) {
            $result = [];
            foreach (array_slice($value, 0, 50, true) as $key => $item) {
                $normalised = strtolower((string)$key);
                $secret = false;
                foreach (self::SECRET_KEYS as $candidate) {
                    if (str_contains($normalised, $candidate)) {
                        $secret = true;
                        break;
                    }
                }
                $result[$key] = $secret ? '[redacted]' : self::clean($item, $depth + 1);
            }
            return $result;
        }
        if (is_object($value)) {
            return self::clean(get_object_vars($value), $depth + 1);
        }
        if (is_string($value)) {
            $value = preg_replace('/[\r\n\t]+/', ' ', $value) ?? '';
            return mb_substr($value, 0, 512);
        }
        return is_scalar($value) || $value === null ? $value : get_debug_type($value);
    }
}
