<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Native course group form.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class native_group extends \moodleform {
    /**
     * Define form.
     */
    protected function definition(): void {
        $mform = $this->_form;
        $mform->addElement('select', 'groupcourseid', get_string('course'), $this->_customdata['courses']);
        $mform->addElement('text', 'groupexternalid', get_string('externalid', 'local_tenantmaster'), ['size' => 35]);
        $mform->setType('groupexternalid', PARAM_TEXT);
        $mform->addRule('groupexternalid', null, 'required');
        $mform->addElement('text', 'groupname', get_string('name'), ['size' => 50]);
        $mform->setType('groupname', PARAM_TEXT);
        $mform->addRule('groupname', null, 'required');
        $mform->addElement('submit', 'submitnativegroup', get_string('savegroup', 'local_tenantmaster'));
    }
}
