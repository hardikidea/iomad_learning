<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantanalytics\form;

use local_tenantanalytics\local\report_catalog;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Interactive report filter and export form.
 *
 * @package    local_tenantanalytics
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class report_filter extends \moodleform {
    /**
     * Define form.
     */
    protected function definition(): void {
        $mform = $this->_form;
        $options = $this->_customdata['options'];

        $mform->addElement('select', 'reportkey', get_string('report', 'local_tenantanalytics'), report_catalog::all());
        $mform->setType('reportkey', PARAM_ALPHANUMEXT);
        $mform->addElement('date_time_selector', 'since', get_string('from', 'local_tenantanalytics'));
        $mform->addElement('date_time_selector', 'until', get_string('to', 'local_tenantanalytics'));
        $mform->addElement('select', 'courseid', get_string('course'), $options->courses());
        $mform->setType('courseid', PARAM_INT);
        $mform->addElement('select', 'cohortid', get_string('cohort', 'cohort'), $options->cohorts());
        $mform->setType('cohortid', PARAM_INT);
        $mform->addElement('select', 'groupid', get_string('group'), $options->groups());
        $mform->setType('groupid', PARAM_INT);
        $mform->addElement(
            'select',
            'dataformat',
            get_string('exportformat', 'local_tenantanalytics'),
            report_catalog::formats()
        );
        $mform->setType('dataformat', PARAM_ALPHANUMEXT);

        $buttons = [];
        $buttons[] = $mform->createElement('submit', 'viewbutton', get_string('viewreport', 'local_tenantanalytics'));
        $buttons[] = $mform->createElement('submit', 'downloadbutton', get_string('download'));
        $mform->addGroup($buttons, 'actions', get_string('actions'), [' '], false);
        $mform->closeHeaderBefore('actions');

        $now = time();
        $this->set_data([
            'reportkey' => 'course_engagement',
            'since' => $now - (30 * DAYSECS),
            'until' => $now,
            'dataformat' => 'csv',
        ]);
    }
}
