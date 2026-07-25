<?php
// This file is part of IOMAD - http://www.iomad.org/

namespace local_institutionpack;

defined('MOODLE_INTERNAL') || die();

use core\exception\moodle_exception;

class pack {
    public const ENTITIES = [
        'institutions',
        'companies',
        'domains',
        'departments',
        'academic_years',
        'boards',
        'mediums',
        'programmes',
        'grades',
        'semesters',
        'streams',
        'subjects',
        'categories',
        'course_templates',
        'courses',
        'users',
        'roles',
        'cohorts',
        'groups',
        'enrolments',
        'parent_links',
        'policies',
        'licenses',
        'branding',
    ];

    public string $path;
    public array $manifest;

    public function __construct(string $path) {
        $realpath = realpath($path);
        if ($realpath === false || !is_dir($realpath)) {
            throw new moodle_exception('Invalid institution pack path: ' . $path);
        }
        $this->path = $realpath;
        $this->manifest = $this->read_manifest();
    }

    public function id(): string {
        return (string)($this->manifest['pack_id'] ?? basename($this->path));
    }

    public function schema_version(): int {
        return (int)($this->manifest['schema_version'] ?? 0);
    }

    public function rows(string $entity): array {
        $file = $this->file_for($entity);
        if ($file === null) {
            return [];
        }
        return $this->read_csv($file);
    }

    public function files(): array {
        $files = [];
        foreach (self::ENTITIES as $entity) {
            $file = $this->file_for($entity);
            if ($file !== null) {
                $files[$entity] = $file;
            }
        }
        return $files;
    }

    public function checksums(): array {
        $checksums = [];
        foreach ($this->files() as $entity => $file) {
            $checksums[$entity] = hash_file('sha256', $file);
        }
        return $checksums;
    }

    private function read_manifest(): array {
        foreach (['manifest.yml', 'manifest.yaml', 'manifest.json'] as $filename) {
            $path = $this->path . DIRECTORY_SEPARATOR . $filename;
            if (!is_file($path)) {
                continue;
            }
            if (str_ends_with($filename, '.json')) {
                $data = json_decode(file_get_contents($path), true);
            } else {
                $data = $this->parse_manifest_yaml($path);
            }
            if (!is_array($data)) {
                throw new moodle_exception('Unable to parse manifest: ' . $path);
            }
            return $data;
        }
        throw new moodle_exception('Missing manifest.yml in ' . $this->path);
    }

    private function parse_manifest_yaml(string $path): array {
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            throw new moodle_exception('Unable to read manifest: ' . $path);
        }

        $data = [];
        $section = null;
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            if (preg_match('/^([A-Za-z0-9_-]+):\s*$/', $line, $matches)) {
                $section = $matches[1];
                $data[$section] = [];
                continue;
            }

            if (preg_match('/^([A-Za-z0-9_-]+):\s*(.*?)\s*$/', $line, $matches)) {
                $section = null;
                $data[$matches[1]] = $this->parse_manifest_scalar($matches[2]);
                continue;
            }

            if ($section !== null && preg_match('/^\s+([A-Za-z0-9_-]+):\s*(.*?)\s*$/', $line, $matches)) {
                $data[$section][$matches[1]] = $this->parse_manifest_scalar($matches[2]);
                continue;
            }

            throw new moodle_exception('Unsupported manifest syntax in ' . $path . ': ' . $line);
        }

        return $data;
    }

    private function parse_manifest_scalar(string $value) {
        $value = trim($value);
        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        return preg_match('/^-?\d+$/', $value) ? (int)$value : $value;
    }

    private function file_for(string $entity): ?string {
        $files = $this->manifest['files'] ?? [];
        $relative = $files[$entity] ?? ($entity . '.csv');
        $path = $this->path . DIRECTORY_SEPARATOR . $relative;
        return is_file($path) ? $path : null;
    }

    private function read_csv(string $path): array {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new moodle_exception('Unable to read CSV: ' . $path);
        }

        $headers = fgetcsv($handle);
        if ($headers === false) {
            fclose($handle);
            return [];
        }
        $headers = array_map([$this, 'clean_header'], $headers);

        $rows = [];
        $line = 1;
        while (($data = fgetcsv($handle)) !== false) {
            $line++;
            if ($this->is_empty_row($data)) {
                continue;
            }
            $row = ['_line' => $line];
            foreach ($headers as $index => $header) {
                $row[$header] = isset($data[$index]) ? trim((string)$data[$index]) : '';
            }
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }

    private function clean_header(string $header): string {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header);
        return trim($header);
    }

    private function is_empty_row(array $row): bool {
        foreach ($row as $value) {
            if (trim((string)$value) !== '') {
                return false;
            }
        }
        return true;
    }
}
