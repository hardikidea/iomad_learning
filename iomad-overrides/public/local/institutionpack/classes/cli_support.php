<?php
// This file is part of IOMAD - http://www.iomad.org/

namespace local_institutionpack;

defined('MOODLE_INTERNAL') || die();

/**
 * Shared controls for project-owned administrative CLI entry points.
 */
final class cli_support {
    /**
     * Run CLI APIs as the configured site administrator.
     */
    public static function require_site_admin(): void {
        $admin = get_admin();
        \core\session\manager::set_user($admin);
        if (!is_siteadmin()) {
            throw new \moodle_exception('A configured site administrator is required.');
        }
    }

    /**
     * Write deterministic JSON output.
     *
     * @param mixed $result Output value.
     */
    public static function output($result): void {
        cli_writeln(json_encode(
            $result,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ));
    }

    /**
     * Output a redacted command failure.
     *
     * @param \Throwable $exception Failure.
     */
    public static function failure(\Throwable $exception): void {
        self::output([
            'ok' => false,
            'error' => clean_param($exception->getMessage(), PARAM_TEXT),
            'exception' => get_class($exception),
        ]);
    }
}
