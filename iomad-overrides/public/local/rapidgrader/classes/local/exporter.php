<?php
// This file is part of Moodle - http://moodle.org/

namespace local_rapidgrader\local;

use core\dataformat;

/**
 * Export tenant-filtered grade matrices through core dataformats.
 *
 * @package    local_rapidgrader
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class exporter {
    /** @var array Supported formats. */
    public const FORMATS = ['csv', 'excel', 'ods', 'pdf'];

    /**
     * Stream a grade report.
     *
     * @param object $course Course.
     * @param gradebook_service $service Gradebook.
     * @param string $format Format.
     */
    public function download(object $course, gradebook_service $service, string $format): void {
        if (!in_array($format, self::FORMATS, true)) {
            throw new \invalid_parameter_exception('Unsupported grade export format.');
        }
        $items = $service->items();
        $columns = [
            'userid' => get_string('userid', 'local_rapidgrader'),
            'learner' => get_string('learner', 'local_rapidgrader'),
            'idnumber' => get_string('idnumber'),
        ];
        foreach ($items as $item) {
            $columns['grade_' . $item->id] = $item->get_name();
        }
        $rows = [];
        foreach ($service->learners() as $learner) {
            $row = [
                'userid' => $learner->id,
                'learner' => fullname($learner),
                'idnumber' => $learner->idnumber,
            ];
            foreach ($items as $item) {
                $grade = $service->grade($item, (int)$learner->id);
                $row['grade_' . $item->id] = $grade === null ? '' : round($grade, 5);
            }
            $rows[] = $row;
        }
        dataformat::download_data(
            clean_filename($course->shortname . '-grades-' . gmdate('Ymd-His')),
            $format,
            $columns,
            $rows,
            [self::class, 'escape_row'],
        );
    }

    /**
     * Escape values that spreadsheet applications could execute.
     *
     * @param array|object $row Row.
     * @param bool $supportshtml Supports HTML.
     * @return array
     */
    public static function escape_row(array|object $row, bool $supportshtml): array {
        unset($supportshtml);
        return array_map(
            static fn(mixed $value): mixed => dataformat::escape_spreadsheet_formula($value),
            (array)$row,
        );
    }
}
