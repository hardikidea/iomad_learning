<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Native enrolment and optional course-group membership form.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class access_assignment extends \moodleform {
    /**
     * Define form.
     */
    protected function definition(): void {
        $mform = $this->_form;
        $mform->addElement('select', 'accessuserid', get_string('user'), $this->_customdata['users']);
        $mform->addElement('select', 'accesscourseid', get_string('course'), $this->_customdata['courses']);
        $mform->addElement('select', 'accessrolekey', get_string('businessrole', 'local_tenantmaster'), [
            'student_learner' => get_string('role_student_learner', 'local_tenantmaster'),
            'teacher_faculty' => get_string('role_teacher_faculty', 'local_tenantmaster'),
        ]);
        $mform->addElement('select', 'accessgroupid', get_string('group'), $this->_customdata['groups']);
        $mform->addElement('submit', 'submitaccessassignment', get_string('enrolnativeuser', 'local_tenantmaster'));
    }
}
