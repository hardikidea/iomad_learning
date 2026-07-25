<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Existing native user role-assignment form.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class role_assignment extends \moodleform {
    /**
     * Define form.
     */
    protected function definition(): void {
        $mform = $this->_form;
        $mform->addElement('select', 'assignmentuserid', get_string('user'), $this->_customdata['users'] ?? []);
        $mform->addRule('assignmentuserid', null, 'required');
        $mform->addElement(
            'select',
            'assignmentrolekey',
            get_string('businessrole', 'local_tenantmaster'),
            $this->_customdata['roles'] ?? []
        );
        $mform->addElement(
            'select',
            'assignmentdepartmentid',
            get_string('department'),
            $this->_customdata['departments'] ?? []
        );
        $mform->addElement(
            'select',
            'assignmentcourseid',
            get_string('course'),
            $this->_customdata['courses'] ?? []
        );
        $mform->addElement('submit', 'submitroleassignment', get_string('assignrole', 'local_tenantmaster'));
    }
}
