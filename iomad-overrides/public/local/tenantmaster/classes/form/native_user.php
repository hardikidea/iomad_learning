<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Native Moodle/IOMAD user creation form.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class native_user extends \moodleform {
    /**
     * Define form.
     */
    protected function definition(): void {
        $mform = $this->_form;
        $mform->addElement('text', 'username', get_string('username'), ['size' => 30]);
        $mform->setType('username', PARAM_USERNAME);
        $mform->addRule('username', null, 'required');
        $mform->addElement('text', 'idnumber', get_string('idnumber'), ['size' => 30]);
        $mform->setType('idnumber', PARAM_TEXT);
        $mform->addRule('idnumber', null, 'required');
        $mform->addElement('text', 'firstname', get_string('firstname'), ['size' => 30]);
        $mform->setType('firstname', PARAM_NOTAGS);
        $mform->addRule('firstname', null, 'required');
        $mform->addElement('text', 'lastname', get_string('lastname'), ['size' => 30]);
        $mform->setType('lastname', PARAM_NOTAGS);
        $mform->addRule('lastname', null, 'required');
        $mform->addElement('text', 'email', get_string('email'), ['size' => 40]);
        $mform->setType('email', PARAM_EMAIL);
        $mform->addRule('email', null, 'required');
        $mform->addElement('text', 'city', get_string('city'), ['size' => 30]);
        $mform->setType('city', PARAM_NOTAGS);
        $mform->addElement('select', 'country', get_string('country'), get_string_manager()->get_list_of_countries());
        $mform->addElement(
            'select',
            'rolekey',
            get_string('businessrole', 'local_tenantmaster'),
            $this->_customdata['roles']
        );
        $mform->addElement(
            'select',
            'departmentid',
            get_string('department'),
            $this->_customdata['departments']
        );
        $mform->addElement(
            'select',
            'courseid',
            get_string('course'),
            $this->_customdata['courses']
        );
        $mform->addElement('submit', 'submitnativeuser', get_string('createnativeuser', 'local_tenantmaster'));
    }
}
