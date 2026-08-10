<?php
// This file is part of Moodle - https://moodle.org/

namespace local_orgprofile\local\service;

use invalid_parameter_exception;
use stdClass;

/**
 * Validates field configuration and submitted profile values.
 *
 * @package local_orgprofile
 */
final class validation_service {

    /** @var string[] Supported plugin field types. */
    public const FIELD_TYPES = [
        'text', 'textarea', 'email', 'integer', 'decimal', 'date', 'datetime',
        'menu', 'checkbox', 'boolean', 'phone', 'url',
    ];

    /** @var string[] Moodle user fields which can be placed on a form. */
    public const CORE_FIELDS = ['firstname', 'lastname', 'email', 'phone1', 'phone2', 'city', 'country'];

    /** @var string[] Supported uniqueness scopes. */
    public const UNIQUE_SCOPES = ['none', 'company', 'site'];

    /** @var string[] Supported validation rule names. */
    private const VALIDATION_RULES = [
        'required', 'minlength', 'maxlength', 'min', 'max', 'email', 'url', 'integer', 'date', 'phone', 'regex',
    ];

    /** @var array<string, string> Required datatype for each supported core user field. */
    private const CORE_FIELD_TYPES = [
        'firstname' => 'text',
        'lastname' => 'text',
        'email' => 'email',
        'phone1' => 'phone',
        'phone2' => 'phone',
        'city' => 'text',
        'country' => 'menu',
    ];

    /**
     * Decode a JSON configuration object.
     *
     * @param string|null $json JSON input.
     * @param bool $allowlist Whether a top-level list is allowed.
     * @return array
     */
    public function decode_json(?string $json, bool $allowlist = false): array {
        if ($json === null || trim($json) === '') {
            return [];
        }
        try {
            $value = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new invalid_parameter_exception(get_string('invalidjson', 'local_orgprofile'));
        }
        if (!is_array($value) || (!$allowlist && array_is_list($value))) {
            throw new invalid_parameter_exception(get_string('invalidjson', 'local_orgprofile'));
        }
        return $value;
    }

    /**
     * Return field configuration errors keyed for a Moodle form.
     *
     * @param stdClass $field Field configuration.
     * @return array
     */
    public function configuration_errors(stdClass $field): array {
        $errors = [];
        if (!in_array($field->datatype ?? '', self::FIELD_TYPES, true)) {
            $errors['datatype'] = get_string('invalidvalue', 'local_orgprofile');
        }
        if (!in_array($field->uniquescope ?? 'none', self::UNIQUE_SCOPES, true)) {
            $errors['uniquescope'] = get_string('invalidvalue', 'local_orgprofile');
        }
        if (!empty($field->corefield) && !in_array($field->corefield, self::CORE_FIELDS, true)) {
            $errors['corefield'] = get_string('invalidvalue', 'local_orgprofile');
        }
        if (!empty($field->corefield) && isset(self::CORE_FIELD_TYPES[$field->corefield]) &&
                self::CORE_FIELD_TYPES[$field->corefield] !== ($field->datatype ?? '')) {
            $errors['datatype'] = get_string('invalidconfiguration', 'local_orgprofile',
                'The selected Moodle core field requires datatype ' . self::CORE_FIELD_TYPES[$field->corefield] . '.');
        }
        if (!empty($field->corefield) && ($field->uniquescope ?? 'none') !== 'none') {
            $errors['uniquescope'] = get_string('invalidconfiguration', 'local_orgprofile',
                'Core fields use Moodle core uniqueness rules.');
        }

        try {
            $options = $this->decode_json($field->optionsjson ?? null, true);
            if (($field->datatype ?? '') === 'menu' && ($field->corefield ?? '') !== 'country') {
                if ($options === []) {
                    $errors['optionsjson'] = get_string('invalidconfiguration', 'local_orgprofile',
                        'Menu fields require at least one option.');
                } else {
                    foreach ($options as $key => $label) {
                        if (is_array($label) || is_object($label) || (!is_string($label) && !is_numeric($label))) {
                            $errors['optionsjson'] = get_string('invalidconfiguration', 'local_orgprofile',
                                'Menu labels must be strings.');
                            break;
                        }
                        if (!is_int($key) && clean_param((string) $key, PARAM_TEXT) !== (string) $key) {
                            $errors['optionsjson'] = get_string('invalidconfiguration', 'local_orgprofile',
                                'Menu keys must be plain text.');
                            break;
                        }
                    }
                }
            }
        } catch (invalid_parameter_exception $exception) {
            $errors['optionsjson'] = $exception->getMessage();
        }

        try {
            $rules = $this->decode_json($field->validationjson ?? null);
            foreach ($rules as $name => $rulevalue) {
                if (!in_array($name, self::VALIDATION_RULES, true)) {
                    $errors['validationjson'] = get_string('invalidconfiguration', 'local_orgprofile',
                        'Unsupported validation rule: ' . clean_param((string) $name, PARAM_TEXT));
                    break;
                }
                if (in_array($name, ['minlength', 'maxlength'], true) &&
                        (filter_var($rulevalue, FILTER_VALIDATE_INT) === false || (int) $rulevalue < 0)) {
                    $errors['validationjson'] = get_string('invalidconfiguration', 'local_orgprofile',
                        $name . ' must be a non-negative integer.');
                    break;
                }
                if (in_array($name, ['min', 'max'], true) && !is_numeric($rulevalue)) {
                    $errors['validationjson'] = get_string('invalidconfiguration', 'local_orgprofile',
                        $name . ' must be numeric.');
                    break;
                }
                if (in_array($name, ['required', 'email', 'url', 'integer', 'date', 'phone'], true) &&
                        !is_bool($rulevalue)) {
                    $errors['validationjson'] = get_string('invalidconfiguration', 'local_orgprofile',
                        $name . ' must be a JSON boolean.');
                    break;
                }
                if ($name === 'regex' && !$this->valid_regex($rulevalue)) {
                    $errors['validationjson'] = get_string('invalidconfiguration', 'local_orgprofile',
                        'regex must be a valid delimited PCRE pattern no longer than 255 characters.');
                    break;
                }
            }
        } catch (invalid_parameter_exception $exception) {
            $errors['validationjson'] = $exception->getMessage();
        }
        if (!$errors && isset($field->defaultvalue) && trim((string) $field->defaultvalue) !== '') {
            try {
                $default = $this->normalize_value($field, $field->defaultvalue);
                if ($this->validate_value($field, $default)) {
                    $errors['defaultvalue'] = get_string('invalidvalue', 'local_orgprofile');
                }
            } catch (invalid_parameter_exception $exception) {
                $errors['defaultvalue'] = $exception->getMessage();
            }
        }
        return $errors;
    }

    /**
     * Normalize a submitted value to its canonical storage representation.
     *
     * @param stdClass $field Field definition.
     * @param mixed $value Submitted value.
     * @return string
     */
    public function normalize_value(stdClass $field, mixed $value): string {
        if (in_array($field->datatype, ['checkbox', 'boolean'], true)) {
            return empty($value) ? '0' : '1';
        }
        if (is_array($value) || is_object($value)) {
            throw new invalid_parameter_exception(get_string('invalidvalue', 'local_orgprofile'));
        }
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        if (in_array($field->datatype, ['date', 'datetime'], true) && $value === '0') {
            return '';
        }
        if (($field->corefield ?? '') === 'country') {
            if (!array_key_exists($value, get_string_manager()->get_list_of_countries())) {
                throw new invalid_parameter_exception(get_string('invalidvalue', 'local_orgprofile'));
            }
            return $value;
        }
        return match ($field->datatype) {
            'integer' => filter_var($value, FILTER_VALIDATE_INT) === false
                ? throw new invalid_parameter_exception(get_string('invalidvalue', 'local_orgprofile'))
                : (string) (int) $value,
            'decimal' => !is_numeric($value)
                ? throw new invalid_parameter_exception(get_string('invalidvalue', 'local_orgprofile'))
                : $this->normalize_decimal($value),
            'email' => !validate_email($value)
                ? throw new invalid_parameter_exception(get_string('invalidvalue', 'local_orgprofile'))
                : \core_text::strtolower($value),
            'url' => filter_var($value, FILTER_VALIDATE_URL) === false
                ? throw new invalid_parameter_exception(get_string('invalidvalue', 'local_orgprofile'))
                : $value,
            'date', 'datetime' => $this->normalize_date($value),
            'menu' => $this->normalize_menu($field, $value),
            default => clean_param($value, PARAM_TEXT),
        };
    }

    /**
     * Convert a canonical stored/default value to the type expected by Moodle form controls.
     *
     * Date selectors require an integer timestamp even when optional. Passing an empty string
     * reaches PHP's getdate() and raises a TypeError on supported PHP versions.
     *
     * @param stdClass $field Field definition
     * @param mixed $value Canonical stored or configured default value
     * @return mixed Value suitable for moodleform::set_data()
     */
    public function form_value(stdClass $field, mixed $value): mixed {
        if (!in_array($field->datatype, ['date', 'datetime'], true)) {
            return $value;
        }
        if ($value === null || $value === 0 || $value === '0'
                || (is_string($value) && trim($value) === '')) {
            return 0;
        }
        return (int) $this->normalize_value($field, $value);
    }

    /**
     * Validate a canonical value using field and form-field rules.
     *
     * @param stdClass $field Field definition with effective_required when applicable.
     * @param string $value Canonical value.
     * @return string|null Error string, or null when valid.
     */
    public function validate_value(stdClass $field, string $value): ?string {
        $rules = $this->decode_json($field->validationjson ?? null);
        $required = !empty($field->effective_required) || !empty($field->required) || !empty($rules['required']);
        if ($required && $value === '') {
            return get_string('requiredfield', 'local_orgprofile');
        }
        if ($required && in_array($field->datatype, ['checkbox', 'boolean'], true) && $value !== '1') {
            return get_string('requiredfield', 'local_orgprofile');
        }
        if ($value === '') {
            return null;
        }
        if (isset($rules['minlength']) && \core_text::strlen($value) < (int) $rules['minlength']) {
            return get_string('invalidvalue', 'local_orgprofile');
        }
        if (isset($rules['maxlength']) && \core_text::strlen($value) > (int) $rules['maxlength']) {
            return get_string('invalidvalue', 'local_orgprofile');
        }
        if (isset($rules['min']) && (!is_numeric($value) || (float) $value < (float) $rules['min'])) {
            return get_string('invalidvalue', 'local_orgprofile');
        }
        if (isset($rules['max']) && (!is_numeric($value) || (float) $value > (float) $rules['max'])) {
            return get_string('invalidvalue', 'local_orgprofile');
        }
        if (!empty($rules['email']) && !validate_email($value)) {
            return get_string('invalidvalue', 'local_orgprofile');
        }
        if (!empty($rules['url']) && filter_var($value, FILTER_VALIDATE_URL) === false) {
            return get_string('invalidvalue', 'local_orgprofile');
        }
        if (!empty($rules['integer']) && filter_var($value, FILTER_VALIDATE_INT) === false) {
            return get_string('invalidvalue', 'local_orgprofile');
        }
        if (!empty($rules['date'])) {
            try {
                $this->normalize_date($value);
            } catch (invalid_parameter_exception $exception) {
                return get_string('invalidvalue', 'local_orgprofile');
            }
        }
        if (($field->datatype === 'phone' || !empty($rules['phone'])) &&
                !preg_match('/^[0-9+().\-\s]+$/u', $value)) {
            return get_string('invalidvalue', 'local_orgprofile');
        }
        if (!empty($rules['regex']) && preg_match($rules['regex'], $value) !== 1) {
            return get_string('invalidvalue', 'local_orgprofile');
        }
        return null;
    }

    /**
     * Build a collision-resistant key used by the database uniqueness index.
     *
     * @param stdClass $field Field definition.
     * @param int $companyid IOMAD company ID.
     * @param string $value Canonical value.
     * @return string|null
     */
    public function unique_key(stdClass $field, int $companyid, string $value): ?string {
        if ($value === '' || ($field->uniquescope ?? 'none') === 'none') {
            return null;
        }
        $hash = hash('sha256', \core_text::strtolower($value));
        return $field->uniquescope === 'site' ? 's:' . $hash : 'c' . $companyid . ':' . $hash;
    }

    /** @return array Menu choices keyed by stored value. */
    public function menu_options(stdClass $field): array {
        $options = $this->decode_json($field->optionsjson ?? null, true);
        if (array_is_list($options)) {
            $result = [];
            foreach ($options as $option) {
                $result[(string) $option] = (string) $option;
            }
            return $result;
        }
        return array_map('strval', $options);
    }

    /** @return string Canonical decimal. */
    private function normalize_decimal(string $value): string {
        $normalized = str_contains($value, '.') ? rtrim(rtrim($value, '0'), '.') : $value;
        return in_array($normalized, ['', '-0'], true) ? '0' : $normalized;
    }

    /** @return string Canonical Unix timestamp. */
    private function normalize_date(string $value): string {
        if (ctype_digit($value)) {
            return (string) (int) $value;
        }
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            throw new invalid_parameter_exception(get_string('invalidvalue', 'local_orgprofile'));
        }
        return (string) $timestamp;
    }

    /** @return string Validated menu value. */
    private function normalize_menu(stdClass $field, string $value): string {
        if (!array_key_exists($value, $this->menu_options($field))) {
            throw new invalid_parameter_exception(get_string('invalidmenuoption', 'local_orgprofile'));
        }
        return $value;
    }

    /** @return bool Whether a trusted-admin regex is syntactically valid. */
    private function valid_regex(mixed $regex): bool {
        if (!is_string($regex) || $regex === '' || \core_text::strlen($regex) > 255) {
            return false;
        }
        set_error_handler(static fn(): bool => true);
        try {
            return preg_match($regex, '') !== false;
        } finally {
            restore_error_handler();
        }
    }
}
