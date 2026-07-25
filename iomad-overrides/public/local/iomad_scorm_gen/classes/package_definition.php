<?php
// This file is part of Moodle - http://moodle.org/

namespace local_iomad_scorm_gen;

/**
 * Validate a bounded, sanitized SCORM package definition.
 *
 * @package local_iomad_scorm_gen
 */
final class package_definition {
    /**
     * Validate and normalize.
     *
     * @param array $input Input.
     * @return array
     */
    public function validate(array $input): array {
        $idnumber = trim((string)($input['idnumber'] ?? ''));
        $title = trim((string)($input['title'] ?? ''));
        $sections = $input['sections'] ?? [];
        if (
            !preg_match('/^[A-Za-z0-9_.:-]{3,80}$/', $idnumber)
            || $title === ''
            || !is_array($sections)
            || !$sections
            || count($sections) > 100
        ) {
            throw new \invalid_parameter_exception('Invalid SCORM package definition.');
        }
        $normalised = [];
        $seen = [];
        foreach ($sections as $section) {
            if (!is_array($section)) {
                throw new \invalid_parameter_exception('Invalid SCORM section.');
            }
            $id = trim((string)($section['id'] ?? ''));
            $sectiontitle = trim((string)($section['title'] ?? ''));
            $body = trim((string)($section['body'] ?? ''));
            if (
                !preg_match('/^[A-Za-z][A-Za-z0-9_-]{1,39}$/', $id)
                || isset($seen[$id])
                || $sectiontitle === ''
                || strlen($body) > 20000
            ) {
                throw new \invalid_parameter_exception('Invalid or duplicate SCORM section.');
            }
            $seen[$id] = true;
            $normalised[] = [
                'id' => $id,
                'title' => mb_substr($sectiontitle, 0, 255),
                'body' => clean_text($body, FORMAT_PLAIN),
            ];
        }
        return [
            'idnumber' => $idnumber,
            'title' => mb_substr($title, 0, 255),
            'sections' => $normalised,
        ];
    }
}
