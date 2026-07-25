<?php
// This file is part of IOMAD - http://www.iomad.org/

namespace local_iomadpagebuilder\form;

use local_iomadpagebuilder\catalog;
use local_iomadpagebuilder\definition_validator;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Page metadata and visual component editor form.
 */
final class page extends \moodleform {
    protected function definition(): void {
        $mform = $this->_form;
        $templates = [];
        foreach (catalog::templates() as $key => $template) {
            $templates[$key] = $template['name'];
        }

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('text', 'name', get_string('name', 'local_iomadpagebuilder'), ['size' => 60]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addElement('text', 'slug', get_string('slug', 'local_iomadpagebuilder'), ['size' => 40]);
        $mform->setType('slug', PARAM_ALPHANUMEXT);
        $mform->addRule('slug', null, 'required', null, 'client');
        $mform->addElement('textarea', 'description', get_string('description', 'local_iomadpagebuilder'), [
            'rows' => 2,
            'cols' => 60,
        ]);
        $mform->setType('description', PARAM_TEXT);
        $mform->addElement('select', 'target', get_string('target', 'local_iomadpagebuilder'), [
            'frontpage' => get_string('frontpage', 'local_iomadpagebuilder'),
            'dashboard' => get_string('dashboard', 'local_iomadpagebuilder'),
            'custompage' => get_string('custompage', 'local_iomadpagebuilder'),
            'course' => get_string('course', 'local_iomadpagebuilder'),
        ]);
        $mform->addElement('text', 'targetid', get_string('targetid', 'local_iomadpagebuilder'), ['size' => 12]);
        $mform->setType('targetid', PARAM_INT);
        $mform->setDefault('targetid', 0);
        $mform->addElement('select', 'startertemplate', get_string('template', 'local_iomadpagebuilder'), $templates);
        $mform->setDefault('startertemplate', 'school_home');
        $mform->addElement('html', '<div class="iopb-editor-toolbar">'
            . '<label for="iopb-preset-select">' . get_string('component', 'local_iomadpagebuilder') . '</label>'
            . '<select id="iopb-preset-select" class="custom-select"></select>'
            . '<button type="button" id="iopb-add-component" class="btn btn-secondary">'
            . get_string('addcomponent', 'local_iomadpagebuilder') . '</button></div>'
            . '<div id="iopb-editor" class="iopb-editor" aria-live="polite"></div>');
        $mform->addElement('textarea', 'definition', get_string('definition', 'local_iomadpagebuilder'), [
            'id' => 'id_definition',
            'class' => 'iopb-definition-field',
            'rows' => 8,
            'cols' => 80,
        ]);
        $mform->setType('definition', PARAM_RAW);
        $mform->addRule('definition', null, 'required', null, 'client');
        $this->add_action_buttons(true, get_string('savechanges'));
    }

    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        try {
            (new definition_validator())->from_json((string)($data['definition'] ?? ''));
        } catch (\Throwable $exception) {
            $errors['definition'] = get_string('invaliddefinition', 'local_iomadpagebuilder', $exception->getMessage());
        }
        return $errors;
    }
}
