<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Tenant form block configuration.
 *
 * @package    block_tenantform
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Select a tenant form activity to embed.
 */
final class block_tenantform_edit_form extends block_edit_form {
    /**
     * Add block fields.
     *
     * @param \MoodleQuickForm $mform Form.
     */
    protected function specific_definition($mform): void {
        global $DB;

        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));
        $companyid = max(
            0,
            \local_iomad\iomad::get_my_companyid(\context_system::instance(), false),
        );
        $params = ['module' => 'tenantform'];
        $companysql = $companyid ? 'AND tf.companyid = :companyid' : '';
        if ($companyid) {
            $params['companyid'] = $companyid;
        }
        $forms = $DB->get_records_sql_menu(
            "SELECT cm.id, " . $DB->sql_concat('c.shortname', "' / '", 'tf.name') . "
               FROM {tenantform} tf
               JOIN {course_modules} cm ON cm.instance = tf.id
               JOIN {modules} m ON m.id = cm.module AND m.name = :module
               JOIN {course} c ON c.id = tf.course
              WHERE cm.deletioninprogress = 0
                    {$companysql}
           ORDER BY c.shortname, tf.name",
            $params,
        );
        $mform->addElement(
            'autocomplete',
            'config_cmid',
            get_string('formactivity', 'block_tenantform'),
            ['0' => get_string('choose')] + $forms,
        );
        $mform->setType('config_cmid', PARAM_INT);
    }

    /**
     * Open configuration immediately.
     *
     * @return bool
     */
    public static function display_form_when_adding(): bool {
        return true;
    }
}
