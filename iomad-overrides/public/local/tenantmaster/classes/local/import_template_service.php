<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\local;

/**
 * Build institution-specific, checksum-valid import starter files.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class import_template_service {
    /**
     * Build a complete starter ZIP.
     *
     * Root CSV files contain headers only and are safe to inspect as a no-op.
     * Sanitized examples are stored outside the manifest.
     *
     * @param object $tenant Tenant.
     * @return string ZIP bytes.
     */
    public function build_zip(object $tenant): string {
        global $CFG;

        if (!class_exists(\ZipArchive::class)) {
            throw new \moodle_exception('ziprequired', 'local_tenantmaster');
        }
        $path = tempnam($CFG->tempdir, 'tenantmaster-template-');
        if ($path === false) {
            throw new \moodle_exception('cannotcreatetempfile');
        }
        $zip = new \ZipArchive();
        $open = false;
        try {
            if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                throw new \moodle_exception('cannotcreatezipfile');
            }
            $open = true;
            $files = [];
            foreach (import_schema::entities() as $entity => $definition) {
                $filename = $entity . '.csv';
                $template = $this->csv(import_schema::columns($entity));
                $zip->addFromString($filename, $template);
                $zip->addFromString(
                    'examples/' . $filename,
                    $this->csv(import_schema::columns($entity), import_schema::example($entity)),
                );
                $files[] = [
                    'path' => $filename,
                    'entity' => $entity,
                    'rows' => 0,
                    'sha256' => hash('sha256', $template),
                ];
            }
            $manifest = [
                'schema_version' => import_schema::VERSION,
                'tenant' => ['trust_code' => (string)$tenant->trustcode],
                'files' => $files,
            ];
            $zip->addFromString(
                'manifest.json',
                json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n",
            );
            $zip->addFromString('field-guide.csv', $this->field_guide());
            $zip->addFromString('README.txt', $this->readme($tenant));
            $zip->close();
            $open = false;

            $content = file_get_contents($path);
            if ($content === false) {
                throw new \moodle_exception('invalidpackagefile', 'local_tenantmaster');
            }
            return $content;
        } finally {
            if ($open) {
                $zip->close();
            }
            @unlink($path);
        }
    }

    /**
     * Build one header-only CSV template.
     *
     * @param string $entity Entity.
     * @return string CSV.
     */
    public function build_csv(string $entity): string {
        return $this->csv(import_schema::columns($entity));
    }

    /**
     * Safe download filename.
     *
     * @param object $tenant Tenant.
     * @return string
     */
    public function zip_filename(object $tenant): string {
        return clean_filename(
            'tenantmaster-' . strtolower((string)$tenant->trustcode)
                . '-import-template-v' . import_schema::VERSION . '.zip',
        );
    }

    /**
     * Encode CSV data consistently.
     *
     * @param string[] $columns Columns.
     * @param array<string, string>|null $row Optional row.
     * @return string
     */
    private function csv(array $columns, ?array $row = null): string {
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new \coding_exception('Unable to create an import template stream.');
        }
        fputcsv($stream, $columns, ',', '"', '');
        if ($row !== null) {
            fputcsv(
                $stream,
                array_map(
                    static fn(string $column): string => (string)($row[$column] ?? ''),
                    $columns,
                ),
                ',',
                '"',
                '',
            );
        }
        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);
        if ($content === false) {
            throw new \coding_exception('Unable to read an import template stream.');
        }
        return $content;
    }

    /**
     * Build a machine-readable field guide.
     *
     * @return string CSV.
     */
    private function field_guide(): string {
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new \coding_exception('Unable to create a field-guide stream.');
        }
        fputcsv($stream, ['entity', 'column', 'requirement', 'example', 'notes'], ',', '"', '');
        foreach (import_schema::entities() as $entity => $definition) {
            foreach (import_schema::columns($entity) as $column) {
                fputcsv(
                    $stream,
                    [
                        $entity,
                        $column,
                        in_array($column, $definition['required'], true) ? 'required' : 'optional',
                        (string)($definition['example'][$column] ?? ''),
                        import_schema::field_note($column),
                    ],
                    ',',
                    '"',
                    '',
                );
            }
        }
        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);
        if ($content === false) {
            throw new \coding_exception('Unable to read a field-guide stream.');
        }
        return $content;
    }

    /**
     * Build package instructions.
     *
     * @param object $tenant Tenant.
     * @return string
     */
    private function readme(object $tenant): string {
        return implode("\n", [
            'TENANT MASTER IMPORT STARTER',
            '',
            'Selected trust code: ' . (string)$tenant->trustcode,
            'Schema version: ' . import_schema::VERSION,
            '',
            '1. Extract this ZIP into a private working directory.',
            '2. Add rows only to the root CSV files you need. Keep every header unchanged.',
            '3. Use examples/ only as a reference; those files are not imported.',
            '4. Update each manifest rows value and SHA-256 after editing a referenced CSV.',
            '5. Remove unused CSV entries from manifest.json, or leave them header-only with rows set to 0.',
            '6. Recreate the ZIP with manifest.json and referenced CSV files at the package root.',
            '7. Upload through Tenant Master > Imports. Inspect the plan before applying it.',
            '',
            'Required and optional fields are listed in field-guide.csv.',
            'Never add passwords, tokens, secrets or personal identity columns.',
            'Referenced users and courses must already belong to the selected IOMAD company.',
            'Use stable external IDs and native idnumbers, never display names, for relationships.',
            '',
        ]);
    }
}
