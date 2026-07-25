<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Academic-year form.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class academic_year extends \moodleform {
    /**
     * Define form.
     */
    protected function definition(): void {
        $mform = $this->_form;
        $mform->addElement('hidden', 'yearid', 0);
        $mform->setType('yearid', PARAM_INT);
        $mform->addElement('text', 'yearexternalid', get_string('externalid', 'local_tenantmaster'), ['size' => 30]);
        $mform->setType('yearexternalid', PARAM_TEXT);
        $mform->addRule('yearexternalid', null, 'required');
        $mform->addElement('text', 'yearcode', get_string('code', 'local_tenantmaster'), ['size' => 20]);
        $mform->setType('yearcode', PARAM_TEXT);
        $mform->addRule('yearcode', null, 'required');
        $mform->addElement('text', 'yearname', get_string('name'), ['size' => 30]);
        $mform->setType('yearname', PARAM_TEXT);
        $mform->addRule('yearname', null, 'required');
        $mform->addElement('date_selector', 'yearstartdate', get_string('startdate'));
        $mform->addElement('date_selector', 'yearenddate', get_string('enddate'));
        $mform->addElement('advcheckbox', 'yeariscurrent', get_string('currentacademicyear', 'local_tenantmaster'));
        $mform->addElement('submit', 'submitacademicyear', get_string('saveacademicyear', 'local_tenantmaster'));
    }
}
