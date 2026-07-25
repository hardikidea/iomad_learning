<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Tenant form backup task.
 *
 * @package    mod_tenantform
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/tenantform/backup/moodle2/backup_tenantform_stepslib.php');

/**
 * Define tenant form activity backup.
 */
final class backup_tenantform_activity_task extends backup_activity_task {
    /**
     * No plugin-specific backup settings.
     */
    protected function define_my_settings(): void {
    }

    /**
     * Register structure step.
     */
    protected function define_my_steps(): void {
        $this->add_step(new backup_tenantform_activity_structure_step(
            'tenantform_structure',
            'tenantform.xml',
        ));
    }

    /**
     * Encode activity links.
     *
     * @param string $content Content.
     * @return string
     */
    public static function encode_content_links($content): string {
        global $CFG;

        $base = preg_quote($CFG->wwwroot, '/');
        return preg_replace(
            "/({$base}\\/mod\\/tenantform\\/view.php\\?id=)([0-9]+)/",
            '$@TENANTFORMVIEWBYID*$2@$',
            $content,
        );
    }
}
