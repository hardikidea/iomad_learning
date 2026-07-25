<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_tenantform\local;

/**
 * Strict validator and normaliser for tenant form definitions.
 *
 * @package    mod_tenantform
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class definition_validator {
    /** @var array Supported field types. */
    private const TYPES = [
        'text', 'textarea', 'email', 'number', 'select', 'radio',
        'checkbox', 'date', 'file', 'consent', 'heading',
    ];

    /** @var array Supported condition operators. */
    private const OPERATORS = ['equals', 'not_equals', 'contains', 'empty', 'not_empty'];

    /**
     * Decode and validate JSON.
     *
     * @param string $json JSON.
     * @return array Canonical definition.
     */
    public function from_json(string $json): array {
        try {
            $definition = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \invalid_parameter_exception('Invalid JSON: ' . $exception->getMessage());
        }
        if (!is_array($definition)) {
            throw new \invalid_parameter_exception('The form definition must be an object.');
        }
        return $this->validate($definition);
    }

    /**
     * Validate and return a canonical definition.
     *
     * @param array $definition Definition.
     * @return array
     */
    public function validate(array $definition): array {
        if (($definition['schema_version'] ?? null) !== 1) {
            throw new \invalid_parameter_exception('schema_version must be 1.');
        }
        $pages = $definition['pages'] ?? null;
        if (!is_array($pages) || !$pages || count($pages) > 20) {
            throw new \invalid_parameter_exception('pages must contain between 1 and 20 pages.');
        }

        $result = ['schema_version' => 1, 'pages' => []];
        $pageids = [];
        $fieldids = [];
        $fieldcount = 0;
        foreach ($pages as $page) {
            if (!is_array($page)) {
                throw new \invalid_parameter_exception('Each page must be an object.');
            }
            $pageid = $this->id($page['id'] ?? '', 'page');
            if (isset($pageids[$pageid])) {
                throw new \invalid_parameter_exception("Duplicate page id: {$pageid}.");
            }
            $pageids[$pageid] = true;
            $title = $this->label($page['title'] ?? '', 'page title');
            $fields = $page['fields'] ?? null;
            if (!is_array($fields) || !$fields) {
                throw new \invalid_parameter_exception("Page {$pageid} must contain fields.");
            }
            $cleanfields = [];
            foreach ($fields as $field) {
                $fieldcount++;
                if ($fieldcount > 100) {
                    throw new \invalid_parameter_exception('A form cannot contain more than 100 fields.');
                }
                $cleanfields[] = $this->field($field, $fieldids);
                $fieldids[end($cleanfields)['id']] = true;
            }
            $result['pages'][] = ['id' => $pageid, 'title' => $title, 'fields' => $cleanfields];
        }
        return $result;
    }

    /**
     * Validate one field.
     *
     * @param mixed $field Field.
     * @param array $previousfields Previously defined IDs.
     * @return array
     */
    private function field(mixed $field, array $previousfields): array {
        if (!is_array($field)) {
            throw new \invalid_parameter_exception('Each field must be an object.');
        }
        $id = $this->id($field['id'] ?? '', 'field');
        if (isset($previousfields[$id])) {
            throw new \invalid_parameter_exception("Duplicate field id: {$id}.");
        }
        $type = (string)($field['type'] ?? '');
        if (!in_array($type, self::TYPES, true)) {
            throw new \invalid_parameter_exception("Unsupported field type: {$type}.");
        }
        $clean = [
            'id' => $id,
            'type' => $type,
            'label' => $this->label($field['label'] ?? '', 'field label'),
            'required' => (bool)($field['required'] ?? false),
        ];
        if (isset($field['help'])) {
            $clean['help'] = $this->label($field['help'], 'field help', 500);
        }
        if (in_array($type, ['select', 'radio'], true)) {
            $options = $field['options'] ?? null;
            if (!is_array($options) || count($options) < 1 || count($options) > 50) {
                throw new \invalid_parameter_exception("Field {$id} requires 1 to 50 options.");
            }
            $clean['options'] = [];
            foreach ($options as $option) {
                $value = $this->label($option, 'option', 200);
                if (in_array($value, $clean['options'], true)) {
                    throw new \invalid_parameter_exception("Field {$id} contains duplicate options.");
                }
                $clean['options'][] = $value;
            }
        }
        if (isset($field['min'])) {
            $clean['min'] = (float)$field['min'];
        }
        if (isset($field['max'])) {
            $clean['max'] = (float)$field['max'];
        }
        if (isset($clean['min'], $clean['max']) && $clean['min'] > $clean['max']) {
            throw new \invalid_parameter_exception("Field {$id} has an invalid numeric range.");
        }
        if (isset($field['condition'])) {
            if (!is_array($field['condition'])) {
                throw new \invalid_parameter_exception("Field {$id} has an invalid condition.");
            }
            $conditionfield = $this->id($field['condition']['field'] ?? '', 'condition field');
            if (!isset($previousfields[$conditionfield])) {
                throw new \invalid_parameter_exception(
                    "Field {$id} condition must reference an earlier field."
                );
            }
            $operator = (string)($field['condition']['operator'] ?? '');
            if (!in_array($operator, self::OPERATORS, true)) {
                throw new \invalid_parameter_exception("Field {$id} has an unsupported condition operator.");
            }
            $clean['condition'] = [
                'field' => $conditionfield,
                'operator' => $operator,
                'value' => $this->label($field['condition']['value'] ?? '', 'condition value', 500, true),
            ];
        }
        return $clean;
    }

    /**
     * Validate a stable identifier.
     *
     * @param mixed $value Value.
     * @param string $name Name.
     * @return string
     */
    private function id(mixed $value, string $name): string {
        $value = (string)$value;
        if (!preg_match('/^[a-z][a-z0-9_]{0,63}$/', $value)) {
            throw new \invalid_parameter_exception("Invalid {$name} id: {$value}.");
        }
        return $value;
    }

    /**
     * Validate plain display text.
     *
     * @param mixed $value Value.
     * @param string $name Name.
     * @param int $maxlength Maximum length.
     * @param bool $allowempty Allow empty.
     * @return string
     */
    private function label(
        mixed $value,
        string $name,
        int $maxlength = 255,
        bool $allowempty = false
    ): string {
        $value = trim(clean_param((string)$value, PARAM_TEXT));
        if ((!$allowempty && $value === '') || \core_text::strlen($value) > $maxlength) {
            throw new \invalid_parameter_exception("Invalid {$name}.");
        }
        return $value;
    }
}
