<?php
// This file is part of Moodle - http://moodle.org/

use mod_tenantform\local\definition_validator;
use mod_tenantform\local\template_catalog;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Activity settings form.
 *
 * @package    mod_tenantform
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class mod_tenantform_mod_form extends moodleform_mod {
    /**
     * Define settings.
     */
    public function definition(): void {
        $mform = $this->_form;
        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->addElement('text', 'name', get_string('tenantformname', 'mod_tenantform'), ['size' => 64]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $this->standard_intro_elements();
        $mform->addElement('select', 'formtype', get_string('formtemplate', 'mod_tenantform'), template_catalog::names());
        $mform->setType('formtype', PARAM_ALPHANUMEXT);
        $mform->addElement('textarea', 'definitionjson', get_string('definitionjson', 'mod_tenantform'), [
            'rows' => 22,
            'cols' => 100,
            'spellcheck' => 'false',
        ]);
        $mform->setType('definitionjson', PARAM_RAW);
        $mform->addHelpButton('definitionjson', 'definitionjson', 'mod_tenantform');

        $mform->addElement('header', 'workflow', get_string('workflow', 'mod_tenantform'));
        $mform->addElement('advcheckbox', 'allowguest', get_string('allowguest', 'mod_tenantform'));
        $mform->addElement('advcheckbox', 'notify', get_string('notifyreviewers', 'mod_tenantform'));
        $mform->setDefault('notify', 1);
        $mform->addElement('course', 'targetcourseid', get_string('targetcourse', 'mod_tenantform'));
        $mform->setType('targetcourseid', PARAM_INT);
        $mform->addElement('advcheckbox', 'autoenrol', get_string('autoenrol', 'mod_tenantform'));

        $mform->addElement('header', 'appearance', get_string('appearance'));
        $mform->addElement('text', 'accent', get_string('accent', 'mod_tenantform'));
        $mform->setType('accent', PARAM_TEXT);
        $mform->setDefault('accent', '#176b5b');
        $mform->addElement('select', 'density', get_string('density', 'mod_tenantform'), [
            'comfortable' => get_string('comfortable', 'mod_tenantform'),
            'compact' => get_string('compact', 'mod_tenantform'),
        ]);
        $mform->setType('density', PARAM_ALPHA);
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Load branding fields from stored JSON.
     *
     * @param array $defaultvalues Defaults.
     */
    public function data_preprocessing(&$defaultvalues): void {
        parent::data_preprocessing($defaultvalues);
        $branding = json_decode($defaultvalues['brandingjson'] ?? '{}', true);
        $defaultvalues['accent'] = $branding['accent'] ?? '#176b5b';
        $defaultvalues['density'] = $branding['density'] ?? 'comfortable';
    }

    /**
     * Validate schema and workflow settings.
     *
     * @param array $data Form data.
     * @param array $files Files.
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', (string)($data['accent'] ?? ''))) {
            $errors['accent'] = get_string('invalidaccent', 'mod_tenantform');
        }
        if (!empty($data['autoenrol']) && empty($data['targetcourseid'])) {
            $errors['targetcourseid'] = get_string('targetcourserequired', 'mod_tenantform');
        }
        $definition = trim((string)($data['definitionjson'] ?? ''));
        if ($definition !== '') {
            try {
                (new definition_validator())->from_json($definition);
            } catch (\Throwable $exception) {
                $errors['definitionjson'] = get_string('invaliddefinition', 'mod_tenantform', $exception->getMessage());
            }
        }
        return $errors;
    }
}
