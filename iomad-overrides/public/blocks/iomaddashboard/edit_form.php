<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Instance configuration for the IOMAD dashboard block.
 *
 * @package    block_iomaddashboard
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Dashboard widget selection form.
 */
class block_iomaddashboard_edit_form extends block_edit_form {
    /**
     * Add block-specific fields.
     *
     * @param \MoodleQuickForm $mform Form.
     */
    protected function specific_definition($mform): void {
        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));
        $mform->addElement(
            'select',
            'config_widget',
            get_string('widget', 'block_iomaddashboard'),
            \block_iomaddashboard\local\widget_catalog::all(),
        );
        $mform->setDefault('config_widget', 'courseprogress');
        $mform->setType('config_widget', PARAM_ALPHANUMEXT);

        $mform->addElement('select', 'config_limit', get_string('itemlimit', 'block_iomaddashboard'), [
            3 => 3,
            5 => 5,
            10 => 10,
            15 => 15,
            20 => 20,
        ]);
        $mform->setDefault('config_limit', 5);
        $mform->setType('config_limit', PARAM_INT);
    }

    /**
     * Show configuration as soon as a block is added.
     *
     * @return bool
     */
    public static function display_form_when_adding(): bool {
        return true;
    }
}
