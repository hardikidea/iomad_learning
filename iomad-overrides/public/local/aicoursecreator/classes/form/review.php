<?php
// This file is part of IOMAD - http://www.iomad.org/

namespace local_aicoursecreator\form;

use local_aicoursecreator\course_schema_validator;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Human review form for the generated structured definition.
 */
final class review extends \moodleform {
    protected function definition(): void {
        $mform = $this->_form;
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('textarea', 'definition', get_string('definition', 'local_aicoursecreator'), [
            'rows' => 30,
            'cols' => 100,
            'spellcheck' => 'false',
        ]);
        $mform->setType('definition', PARAM_RAW);
        $mform->addRule('definition', null, 'required', null, 'client');
        $this->add_action_buttons(true, get_string('savechanges'));
    }

    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        try {
            (new course_schema_validator())->from_json((string)($data['definition'] ?? ''));
        } catch (\Throwable $exception) {
            $errors['definition'] = get_string(
                'invaliddefinition',
                'local_aicoursecreator',
                $exception->getMessage()
            );
        }
        return $errors;
    }
}
