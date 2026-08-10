<?php
// This file is part of Moodle - https://moodle.org/

namespace local_orgprofile\form;

use local_orgprofile\local\service\profile_service;
use local_orgprofile\local\service\validation_service;

/** Dynamic company-scoped profile form. */
final class profile_form extends \moodleform {

    protected function definition(): void {
        $mform = $this->_form;
        $profile = $this->_customdata['profile'];
        $canedit = !empty($this->_customdata['canedit']);
        $caneditsensitive = !empty($this->_customdata['caneditsensitive']);
        $profileservice = new profile_service();
        $validator = new validation_service();
        $defaults = ['userid' => $profile->user->id, 'companyid' => $profile->company->id];

        $mform->addElement('hidden', 'userid');
        $mform->setType('userid', PARAM_INT);
        $mform->addElement('hidden', 'companyid');
        $mform->setType('companyid', PARAM_INT);

        foreach ($profile->categories as $category) {
            if (!$category->fields) {
                continue;
            }
            $mform->addElement('header', 'category_' . $category->id, format_string($category->name));
            $mform->setExpanded('category_' . $category->id, empty($category->collapsed));
            foreach ($category->fields as $field) {
                $name = $profileservice->element_name($field);
                $label = format_string($field->name);
                $attributes = ['maxlength' => 1333, 'size' => 50];
                if (($field->corefield ?? '') === 'country') {
                    $countries = ['' => get_string('selectacountry')] + get_string_manager()->get_list_of_countries();
                    $mform->addElement('select', $name, $label, $countries);
                } else {
                    switch ($field->datatype) {
                        case 'textarea':
                            $mform->addElement('textarea', $name, $label, ['rows' => 5, 'cols' => 60]);
                            $mform->setType($name, PARAM_TEXT);
                            break;
                        case 'menu':
                            $choices = ['' => get_string('choose')] + $validator->menu_options($field);
                            $mform->addElement('select', $name, $label, $choices);
                            break;
                        case 'checkbox':
                        case 'boolean':
                            $mform->addElement('advcheckbox', $name, $label);
                            break;
                        case 'date':
                            $mform->addElement('date_selector', $name, $label, ['optional' => true]);
                            break;
                        case 'datetime':
                            $mform->addElement('date_time_selector', $name, $label, ['optional' => true]);
                            break;
                        default:
                            $mform->addElement('text', $name, $label, $attributes);
                            $mform->setType($name, $this->param_type($field->datatype));
                    }
                }
                if (!empty($field->description)) {
                    $mform->addElement('static', $name . '_description', '', format_text($field->description, FORMAT_HTML));
                }
                $editablefield = $canedit && empty($field->effective_readonly) &&
                    (empty($field->sensitive) || $caneditsensitive);
                if (!$editablefield) {
                    $mform->freeze($name);
                }
                if (!empty($field->effective_required) && $editablefield) {
                    $mform->addRule($name, get_string('requiredfield', 'local_orgprofile'), 'required', null, 'client');
                }
                $defaults[$name] = $validator->form_value($field, $field->currentvalue);
            }
        }
        if ($canedit) {
            $this->add_action_buttons(false, get_string('savechanges'));
        }
        $this->set_data($defaults);
    }

    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        $errors += (new profile_service())->validate_submission(
            (int) $this->_customdata['profile']->user->id,
            (int) $this->_customdata['profile']->company->id,
            $data
        );
        unset($errors['_profile']);
        return $errors;
    }

    /** Return a conservative Moodle parameter type for a rendered text input. */
    private function param_type(string $datatype): string {
        return match ($datatype) {
            'email' => PARAM_EMAIL,
            'integer' => PARAM_INT,
            'url' => PARAM_URL,
            default => PARAM_TEXT,
        };
    }
}
