<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_tenantform\local;

/**
 * Form validation failed with field-addressable messages.
 *
 * @package    mod_tenantform
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class submission_validation_exception extends \moodle_exception {
    /**
     * Constructor.
     *
     * @param array $errors Field ID to message.
     */
    public function __construct(private readonly array $errors) {
        parent::__construct('validationfailed', 'mod_tenantform');
    }

    /**
     * Return field errors.
     *
     * @return array
     */
    public function get_errors(): array {
        return $this->errors;
    }
}
