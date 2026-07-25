<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_tenantform\local;

use core\dataformat;

/**
 * Stream tenant form entries through Moodle dataformat writers.
 *
 * @package    mod_tenantform
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class entry_exporter {
    /** @var array Supported export formats. */
    public const FORMATS = ['csv', 'excel', 'ods', 'pdf'];

    /**
     * Download entries.
     *
     * @param object $form Form.
     * @param array $definition Definition.
     * @param array $entries Entries.
     * @param string $format Data format.
     */
    public function download(object $form, array $definition, array $entries, string $format): void {
        [$columns, $rows] = $this->data($definition, $entries);
        $this->validate_format($format);
        dataformat::download_data(
            clean_filename('tenant-form-' . $form->id . '-entries'),
            $format,
            $columns,
            $rows,
            [self::class, 'escape_row'],
        );
    }

    /**
     * Write entries to a temporary export file.
     *
     * @param object $form Form.
     * @param array $definition Definition.
     * @param array $entries Entries.
     * @param string $format Data format.
     * @return string
     */
    public function write(object $form, array $definition, array $entries, string $format): string {
        [$columns, $rows] = $this->data($definition, $entries);
        $this->validate_format($format);
        return dataformat::write_data(
            clean_filename('tenant-form-' . $form->id . '-entries'),
            $format,
            $columns,
            $rows,
            [self::class, 'escape_row'],
        );
    }

    /**
     * Build export columns and rows.
     *
     * @param array $definition Definition.
     * @param array $entries Entries.
     * @return array
     */
    private function data(array $definition, array $entries): array {
        $columns = [
            'entryid' => get_string('entryid', 'mod_tenantform'),
            'status' => get_string('status', 'mod_tenantform'),
            'userid' => get_string('userid', 'mod_tenantform'),
            'submitted' => get_string('submitted', 'mod_tenantform'),
        ];
        foreach ($definition['pages'] as $page) {
            foreach ($page['fields'] as $field) {
                if ($field['type'] !== 'heading') {
                    $columns[$field['id']] = $field['label'];
                }
            }
        }
        $rows = [];
        foreach ($entries as $entry) {
            $values = json_decode($entry->datajson, true, 64, JSON_THROW_ON_ERROR);
            $row = [
                'entryid' => $entry->id,
                'status' => get_string('status' . $entry->status, 'mod_tenantform'),
                'userid' => $entry->userid ?: '',
                'submitted' => userdate($entry->timecreated),
            ];
            foreach (array_keys($columns) as $column) {
                if (!array_key_exists($column, $row)) {
                    $row[$column] = $values[$column] ?? '';
                }
            }
            $rows[] = $row;
        }
        return [$columns, $rows];
    }

    /**
     * Escape spreadsheet formula prefixes.
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

    /**
     * Validate a dataformat key.
     *
     * @param string $format Format.
     */
    private function validate_format(string $format): void {
        if (!in_array($format, self::FORMATS, true)) {
            throw new \invalid_parameter_exception('Unsupported tenant form export format.');
        }
    }
}
