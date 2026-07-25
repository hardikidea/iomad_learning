<?php
// This file is part of IOMAD - http://www.iomad.org/

namespace local_aicoursecreator\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Captures a provider-neutral course brief.
 */
final class request extends \moodleform {
    protected function definition(): void {
        $mform = $this->_form;
        $mform->addElement('text', 'title', get_string('title', 'local_aicoursecreator'), ['size' => 60]);
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', null, 'required', null, 'client');
        $mform->addElement('textarea', 'brief', get_string('brief', 'local_aicoursecreator'), [
            'rows' => 10,
            'cols' => 80,
        ]);
        $mform->setType('brief', PARAM_RAW);
        $mform->addRule('brief', null, 'required', null, 'client');
        $mform->addElement('text', 'audience', get_string('audience', 'local_aicoursecreator'), ['size' => 60]);
        $mform->setType('audience', PARAM_TEXT);
        $mform->addElement('select', 'tone', get_string('tone', 'local_aicoursecreator'), [
            'professional' => 'Professional',
            'academic' => 'Academic',
            'supportive' => 'Supportive',
            'concise' => 'Concise',
        ]);
        $mform->addElement('select', 'sectioncount', get_string('sectioncount', 'local_aicoursecreator'), [
            3 => 3,
            5 => 5,
            8 => 8,
            10 => 10,
            12 => 12,
        ]);
        $mform->setDefault('sectioncount', 5);
        $this->add_action_buttons(true, get_string('newdraft', 'local_aicoursecreator'));
    }
}
