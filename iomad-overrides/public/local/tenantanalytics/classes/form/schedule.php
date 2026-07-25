<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantanalytics\form;

use local_tenantanalytics\local\report_catalog;
use local_tenantanalytics\local\schedule_repository;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Owner-only scheduled report form.
 *
 * @package    local_tenantanalytics
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class schedule extends \moodleform {
    /**
     * Define form.
     */
    protected function definition(): void {
        $mform = $this->_form;
        $options = $this->_customdata['options'];

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('select', 'reportkey', get_string('report', 'local_tenantanalytics'), report_catalog::all());
        $mform->setType('reportkey', PARAM_ALPHANUMEXT);
        $mform->addElement(
            'select',
            'dataformat',
            get_string('exportformat', 'local_tenantanalytics'),
            report_catalog::formats()
        );
        $mform->setType('dataformat', PARAM_ALPHANUMEXT);
        $mform->addElement(
            'select',
            'frequency',
            get_string('frequency', 'local_tenantanalytics'),
            schedule_repository::frequencies()
        );
        $mform->setType('frequency', PARAM_ALPHA);
        $mform->addElement(
            'select',
            'lookbackdays',
            get_string('lookback', 'local_tenantanalytics'),
            [
                7 => get_string('lastndays', 'local_tenantanalytics', 7),
                30 => get_string('lastndays', 'local_tenantanalytics', 30),
                90 => get_string('lastndays', 'local_tenantanalytics', 90),
                365 => get_string('lastndays', 'local_tenantanalytics', 365),
            ]
        );
        $mform->setType('lookbackdays', PARAM_INT);
        $mform->addElement('select', 'courseid', get_string('course'), $options->courses());
        $mform->setType('courseid', PARAM_INT);
        $mform->addElement('select', 'cohortid', get_string('cohort', 'cohort'), $options->cohorts());
        $mform->setType('cohortid', PARAM_INT);
        $mform->addElement('select', 'groupid', get_string('group'), $options->groups());
        $mform->setType('groupid', PARAM_INT);
        $mform->addElement('advcheckbox', 'enabled', get_string('enabled', 'local_tenantanalytics'));
        $this->add_action_buttons(true, get_string('saveschedule', 'local_tenantanalytics'));

        $this->set_data([
            'reportkey' => 'course_engagement',
            'dataformat' => 'csv',
            'frequency' => 'weekly',
            'lookbackdays' => 30,
            'enabled' => 1,
        ]);
    }
}
