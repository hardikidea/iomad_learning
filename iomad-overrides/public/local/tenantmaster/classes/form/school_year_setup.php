<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Generate one school-year academic hierarchy from shared masters.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class school_year_setup extends \moodleform {
    /**
     * Define form.
     */
    protected function definition(): void {
        $mform = $this->_form;
        $mform->addElement(
            'select',
            'setupyearid',
            get_string('academicyear', 'local_tenantmaster'),
            $this->_customdata['years'],
        );
        $mform->addRule('setupyearid', null, 'required');
        $mform->addElement(
            'select',
            'setupboardid',
            get_string('mastertype_board', 'local_tenantmaster'),
            $this->_customdata['boards'],
        );
        $mform->addRule('setupboardid', null, 'required');
        $mform->addElement(
            'select',
            'setupmediumid',
            get_string('mastertype_medium', 'local_tenantmaster'),
            $this->_customdata['mediums'],
        );
        $mform->addRule('setupmediumid', null, 'required');
        $mform->addElement(
            'select',
            'setupgradeids',
            get_string('mastertype_grade', 'local_tenantmaster'),
            $this->_customdata['grades'],
            ['multiple' => true, 'size' => 10],
        );
        $mform->addRule('setupgradeids', null, 'required');
        $mform->addElement(
            'select',
            'setupstreamid',
            get_string('optionalstream', 'local_tenantmaster'),
            [0 => get_string('notapplicable', 'local_tenantmaster')] + $this->_customdata['streams'],
        );
        $mform->addElement(
            'select',
            'setupsubjectids',
            get_string('mastertype_subject', 'local_tenantmaster'),
            $this->_customdata['subjects'],
            ['multiple' => true, 'size' => 12],
        );
        $mform->addRule('setupsubjectids', null, 'required');
        $mform->addElement('submit', 'submitschoolyearsetup', get_string('generateschoolyear', 'local_tenantmaster'));
    }
}
