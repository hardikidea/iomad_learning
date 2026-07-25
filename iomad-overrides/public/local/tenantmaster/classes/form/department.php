<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Native IOMAD department form.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class department extends \moodleform {
    /**
     * Define form.
     */
    protected function definition(): void {
        $mform = $this->_form;
        $editing = !empty($this->_customdata['editing']);
        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('text', 'name', get_string('departmentname', 'local_tenantmaster'), ['size' => 50]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required');
        $mform->addElement('text', 'shortname', get_string('shortname'), ['size' => 30]);
        $mform->setType('shortname', PARAM_ALPHANUMEXT);
        $mform->addRule('shortname', null, 'required');
        if ($editing) {
            $mform->freeze('shortname');
        }
        $mform->addElement(
            'select',
            'parentid',
            get_string('parent', 'local_tenantmaster'),
            $this->_customdata['parents'] ?? []
        );
        $mform->addElement('submit', 'submitdepartment', get_string('savedepartment', 'local_tenantmaster'));
    }
}
