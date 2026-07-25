<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantanalytics\local;

use core\dataformat;

/**
 * Moodle-core dataformat adapter with spreadsheet formula escaping.
 *
 * @package    local_tenantanalytics
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class exporter {
    /**
     * Send a report download.
     *
     * @param string $filename Base filename.
     * @param string $format Core dataformat name.
     * @param report_result $result Report.
     */
    public function download(string $filename, string $format, report_result $result): void {
        $this->validate_format($format);
        dataformat::download_data(
            clean_filename($filename),
            $format,
            $result->get_columns(),
            $result->get_rows(),
            [self::class, 'escape_row'],
        );
    }

    /**
     * Write a temporary report file.
     *
     * @param string $filename Base filename.
     * @param string $format Core dataformat name.
     * @param report_result $result Report.
     * @return string
     */
    public function write(string $filename, string $format, report_result $result): string {
        $this->validate_format($format);
        return dataformat::write_data(
            clean_filename($filename),
            $format,
            $result->get_columns(),
            $result->get_rows(),
            [self::class, 'escape_row'],
        );
    }

    /**
     * Escape potential spreadsheet formulas in every scalar cell.
     *
     * @param array|object $row Row.
     * @param bool $supportshtml Whether writer supports HTML.
     * @return array
     */
    public static function escape_row(array|object $row, bool $supportshtml): array {
        unset($supportshtml);
        return array_map(
            static fn(mixed $value): mixed => dataformat::escape_spreadsheet_formula($value),
            (array)$row,
        );
    }

    /**
     * Reject unknown data formats.
     *
     * @param string $format Format.
     */
    private function validate_format(string $format): void {
        if (!array_key_exists($format, report_catalog::formats())) {
            throw new \invalid_parameter_exception('Unsupported report export format.');
        }
    }
}
