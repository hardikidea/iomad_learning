<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * User-free tenant course content copy form.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class course_copy extends \moodleform {
    /**
     * Define form.
     */
    protected function definition(): void {
        $mform = $this->_form;
        $mform->addElement(
            'select',
            'sourcecourseid',
            get_string('sourcecourse', 'local_tenantmaster'),
            $this->_customdata['courses'],
        );
        $mform->addRule('sourcecourseid', null, 'required');
        $mform->addElement(
            'select',
            'targetcourseid',
            get_string('targetcourse', 'local_tenantmaster'),
            $this->_customdata['courses'],
        );
        $mform->addRule('targetcourseid', null, 'required');
        $mform->addElement(
            'advcheckbox',
            'replacecontent',
            get_string('replaceemptytarget', 'local_tenantmaster'),
        );
        $mform->addElement(
            'advcheckbox',
            'confirmcopy',
            get_string('confirmcoursecopy', 'local_tenantmaster'),
        );
        $mform->addRule('confirmcopy', null, 'required');
        $mform->addElement('submit', 'submitcoursecopy', get_string('copycoursecontent', 'local_tenantmaster'));
    }

    /**
     * Validate source and target.
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if ((int)$data['sourcecourseid'] === (int)$data['targetcourseid']) {
            $errors['targetcourseid'] = get_string('sourceandtargetdifferent', 'local_tenantmaster');
        }
        return $errors;
    }
}
