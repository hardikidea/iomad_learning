<?php
// This file is part of IOMAD - http://www.iomad.org/

namespace local_institutionpack;

defined('MOODLE_INTERNAL') || die();

/**
 * Writes protected, append-only operational reports.
 */
final class audit_log {
    /**
     * Write a report below the protected dataroot.
     *
     * @param string $type Report type.
     * @param array $report Report payload.
     * @return string Absolute report path.
     */
    public static function write(string $type, array $report): string {
        global $CFG;

        $type = clean_param($type, PARAM_FILE);
        if ($type === '') {
            throw new \invalid_parameter_exception('Audit report type is invalid.');
        }

        $directory = $CFG->dataroot . '/local_institutionpack/audit';
        make_writable_directory($directory, true);

        $filename = sprintf(
            '%s-%s-%s.json',
            gmdate('Ymd-His'),
            $type,
            bin2hex(random_bytes(6))
        );
        $path = $directory . '/' . $filename;
        $stream = fopen($path, 'x');
        if ($stream === false) {
            throw new \moodle_exception('Unable to create an immutable audit report.');
        }

        try {
            $encoded = json_encode(
                $report,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
            if (fwrite($stream, $encoded . PHP_EOL) === false) {
                throw new \moodle_exception('Unable to write the audit report.');
            }
        } finally {
            fclose($stream);
        }

        chmod($path, 0600);
        return $path;
    }
}
