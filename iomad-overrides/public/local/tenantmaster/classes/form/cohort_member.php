<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Native cohort membership form.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class cohort_member extends \moodleform {
    /**
     * Define form.
     */
    protected function definition(): void {
        $mform = $this->_form;
        $mform->addElement(
            'select',
            'cohortid',
            get_string('cohort', 'local_tenantmaster'),
            $this->_customdata['cohorts'],
        );
        $mform->addElement('select', 'cohortuserid', get_string('user'), $this->_customdata['users']);
        $mform->addElement('submit', 'submitcohortmember', get_string('addcohortmember', 'local_tenantmaster'));
    }
}
