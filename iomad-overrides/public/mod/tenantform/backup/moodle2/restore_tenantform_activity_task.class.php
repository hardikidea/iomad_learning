<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Tenant form restore task.
 *
 * @package    mod_tenantform
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/tenantform/backup/moodle2/restore_tenantform_stepslib.php');

/**
 * Define tenant form activity restore.
 */
final class restore_tenantform_activity_task extends restore_activity_task {
    /**
     * No plugin-specific settings.
     */
    protected function define_my_settings(): void {
    }

    /**
     * Register structure step.
     */
    protected function define_my_steps(): void {
        $this->add_step(new restore_tenantform_activity_structure_step(
            'tenantform_structure',
            'tenantform.xml',
        ));
    }

    /**
     * Content fields for decoding.
     *
     * @return array
     */
    public static function define_decode_contents(): array {
        return [new restore_decode_content('tenantform', ['intro'], 'tenantform')];
    }

    /**
     * Link decode rules.
     *
     * @return array
     */
    public static function define_decode_rules(): array {
        return [new restore_decode_rule(
            'TENANTFORMVIEWBYID',
            '/mod/tenantform/view.php?id=$1',
            'course_module',
        )];
    }
}
