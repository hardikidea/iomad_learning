<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\form;

use local_tenantmaster\local\student_placement_service;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Native-backed school class placement form.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class student_placement extends \moodleform {
    /**
     * Define form.
     */
    protected function definition(): void {
        $mform = $this->_form;
        $editing = !empty($this->_customdata['editing']);
        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('select', 'userid', get_string('student', 'local_tenantmaster'), $this->_customdata['users']);
        $mform->addRule('userid', null, 'required');
        $mform->addElement(
            'select',
            'acadyearid',
            get_string('academicyear', 'local_tenantmaster'),
            $this->_customdata['years'],
        );
        $mform->addRule('acadyearid', null, 'required');
        if ($editing) {
            $mform->hardFreeze(['userid', 'acadyearid']);
        }
        foreach (
            [
                'boardid' => ['board', false],
                'mediumid' => ['medium', false],
                'gradeid' => ['grade', true],
                'streamid' => ['stream', false],
                'divisionid' => ['division', true],
            ] as $field => [$type, $required]
        ) {
            $options = $this->_customdata[$type . 's'];
            if (!$required) {
                $options = [0 => get_string('notapplicable', 'local_tenantmaster')] + $options;
            }
            $mform->addElement(
                'select',
                $field,
                get_string('mastertype_' . $type, 'local_tenantmaster'),
                $options,
            );
            if ($required) {
                $mform->addRule($field, null, 'required');
            }
        }
        $mform->addElement('text', 'rollnumber', get_string('rollnumber', 'local_tenantmaster'), ['size' => 20]);
        $mform->setType('rollnumber', PARAM_TEXT);
        $mform->addElement(
            'select',
            'status',
            get_string('status'),
            array_combine(
                student_placement_service::STATUSES,
                array_map(
                    static fn(string $status): string => get_string('placementstatus_' . $status, 'local_tenantmaster'),
                    student_placement_service::STATUSES,
                ),
            ),
        );
        $mform->addElement('date_selector', 'startdate', get_string('startdate'));
        $mform->addElement('submit', 'submitplacement', get_string('saveplacement', 'local_tenantmaster'));
    }
}
