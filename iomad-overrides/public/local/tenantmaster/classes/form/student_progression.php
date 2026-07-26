<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\form;

use local_tenantmaster\local\student_progression_service;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Reviewed school student progression plan form.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class student_progression extends \moodleform {
    /**
     * Define form.
     */
    protected function definition(): void {
        $mform = $this->_form;
        $mform->addElement(
            'select',
            'sourceplaceid',
            get_string('sourceplacement', 'local_tenantmaster'),
            $this->_customdata['placements'],
        );
        $mform->addRule('sourceplaceid', null, 'required');
        $mform->addElement(
            'select',
            'toyearid',
            get_string('toyear', 'local_tenantmaster'),
            $this->_customdata['years'],
        );
        $mform->addRule('toyearid', null, 'required');
        $decisions = array_combine(
            student_progression_service::DECISIONS,
            array_map(
                static fn(string $decision): string => get_string('decision_' . $decision, 'local_tenantmaster'),
                student_progression_service::DECISIONS,
            ),
        );
        $mform->addElement('select', 'decision', get_string('progressiondecision', 'local_tenantmaster'), $decisions);
        $mform->addElement(
            'select',
            'targetgradeid',
            get_string('targetgrade', 'local_tenantmaster'),
            [0 => get_string('notapplicable', 'local_tenantmaster')] + $this->_customdata['grades'],
        );
        $mform->addElement(
            'select',
            'targetstreamid',
            get_string('targetstream', 'local_tenantmaster'),
            [0 => get_string('notapplicable', 'local_tenantmaster')] + $this->_customdata['streams'],
        );
        $mform->addElement(
            'select',
            'targetdivisionid',
            get_string('targetdivision', 'local_tenantmaster'),
            [0 => get_string('notapplicable', 'local_tenantmaster')] + $this->_customdata['divisions'],
        );
        $mform->addElement('textarea', 'reason', get_string('reason', 'local_tenantmaster'), [
            'rows' => 3,
            'cols' => 60,
        ]);
        $mform->setType('reason', PARAM_TEXT);
        $mform->addElement('submit', 'submitprogression', get_string('createprogressionplan', 'local_tenantmaster'));
    }
}
