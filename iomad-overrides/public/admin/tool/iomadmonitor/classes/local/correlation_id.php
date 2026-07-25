<?php
// This file is part of Moodle - http://moodle.org/

namespace tool_iomadmonitor\local;

/**
 * Request correlation identifier helper.
 *
 * @package tool_iomadmonitor
 */
final class correlation_id {
    /** @var string|null Current request ID. */
    private static ?string $current = null;

    /**
     * Resolve a safe request ID.
     *
     * @param string|null $incoming Incoming X-Request-ID value.
     * @return string
     */
    public static function get(?string $incoming = null): string {
        if (self::$current !== null) {
            return self::$current;
        }
        $incoming ??= $_SERVER['HTTP_X_REQUEST_ID'] ?? '';
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{7,63}$/', $incoming)) {
            self::$current = $incoming;
        } else {
            self::$current = bin2hex(random_bytes(16));
        }
        return self::$current;
    }

    /**
     * Reset for tests.
     */
    public static function reset(): void {
        self::$current = null;
    }
}
