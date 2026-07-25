<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Native cohort form.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class native_cohort extends \moodleform {
    /**
     * Define form.
     */
    protected function definition(): void {
        $mform = $this->_form;
        $mform->addElement('text', 'cohortexternalid', get_string('externalid', 'local_tenantmaster'), ['size' => 35]);
        $mform->setType('cohortexternalid', PARAM_TEXT);
        $mform->addRule('cohortexternalid', null, 'required');
        $mform->addElement('text', 'cohortname', get_string('name'), ['size' => 50]);
        $mform->setType('cohortname', PARAM_TEXT);
        $mform->addRule('cohortname', null, 'required');
        $mform->addElement('textarea', 'cohortdescription', get_string('description'), ['rows' => 3, 'cols' => 60]);
        $mform->setType('cohortdescription', PARAM_TEXT);
        $mform->addElement('submit', 'submitnativecohort', get_string('savecohort', 'local_tenantmaster'));
    }
}
