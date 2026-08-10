<?php
// This file is part of Moodle - https://moodle.org/

namespace local_orgprofile\form;

use local_orgprofile\local\service\authorization_service;
use local_orgprofile\local\service\profile_service;
use local_orgprofile\local\service\provisioning_service;
use local_orgprofile\local\service\validation_service;

/** Moodle account and resolved organization-profile form for a new IOMAD company user. */
final class company_user_create_form extends \moodleform {

    /** Render the account fields followed by the resolved dynamic profile. */
    protected function definition(): void {
        $mform = $this->_form;
        $definition = $this->_customdata['definition'];
        $required = get_string('required');
        $profiles = new profile_service();
        $validator = new validation_service();
        $caneditsensitive = (new authorization_service())->can_edit_sensitive((int) $definition->company->id);
        $defaults = [
            'companyid' => $definition->company->id,
            'usertypeid' => $definition->usertype->id,
            'use_email_as_username' => !empty(get_config('local_iomad', 'use_email_as_username')) ? 1 : 0,
            'preference_auth_forcepasswordchange' => 1,
            'sendnewpasswordemails' => 0,
        ];

        $mform->addElement('hidden', 'companyid');
        $mform->setType('companyid', PARAM_INT);
        $mform->addElement('hidden', 'usertypeid');
        $mform->setType('usertypeid', PARAM_INT);
        $mform->addElement('static', 'companydisplay', get_string('company', 'local_orgprofile'),
            format_string($definition->company->name));
        $mform->addElement('static', 'orgprofiledisplay', get_string('profileform', 'local_orgprofile'),
            format_string($definition->form->name) . ' — ' . format_string($definition->usertype->name));
        $mform->addElement('static', 'workflowstatus', '', '');

        $mform->addElement('header', 'account', get_string('accountdetails', 'local_orgprofile'));
        $mform->setExpanded('account', true, true);
        foreach (['firstname', 'lastname'] as $field) {
            $name = 'core_' . $field;
            $mform->addElement('text', $name, get_string($field), ['maxlength' => 100, 'size' => 40]);
            $mform->setType($name, PARAM_NOTAGS);
            $mform->addRule($name, $required, 'required', null, 'server');
        }
        $mform->addElement('text', 'core_email', get_string('email'), ['maxlength' => 100, 'size' => 40]);
        $mform->setType('core_email', PARAM_EMAIL);
        $mform->addRule('core_email', $required, 'required', null, 'server');
        $mform->addElement('text', 'username', get_string('username'), ['maxlength' => 100, 'size' => 30]);
        $mform->setType('username', PARAM_RAW_TRIMMED);
        $mform->disabledIf('username', 'use_email_as_username', 'checked');
        $mform->addElement('advcheckbox', 'use_email_as_username',
            get_string('iomad_use_email_as_username', 'local_iomad'));
        $mform->addElement('passwordunmask', 'newpassword', get_string('newpassword'), ['size' => 30]);
        $mform->setType('newpassword', PARAM_RAW);
        $mform->addElement('advcheckbox', 'preference_auth_forcepasswordchange', get_string('forcepasswordchange'));
        $mform->addElement('advcheckbox', 'sendnewpasswordemails',
            get_string('sendnewpasswordemails', 'block_iomad_company_admin'));

        foreach ($definition->categories as $category) {
            $fields = array_filter($category->fields, static fn($field): bool =>
                !in_array($field->corefield ?? '', ['firstname', 'lastname', 'email'], true));
            if (!$fields) {
                continue;
            }
            $header = 'category_' . $category->id;
            $mform->addElement('header', $header, format_string($category->name));
            $mform->setExpanded($header, false, true);
            foreach ($fields as $field) {
                $name = $profiles->element_name($field);
                $this->add_dynamic_element($mform, $name, $field, $validator);
                if (!empty($field->description)) {
                    $mform->addElement('static', $name . '_description', '',
                        format_text($field->description, FORMAT_HTML));
                }
                $editable = empty($field->effective_readonly) && (empty($field->sensitive) || $caneditsensitive);
                if (!$editable) {
                    $mform->freeze($name);
                } else if (!empty($field->effective_required)) {
                    $mform->addRule($name, get_string('requiredfield', 'local_orgprofile'), 'required', null, 'server');
                }
                $defaults[$name] = $validator->form_value($field, $field->defaultvalue ?? '');
            }
        }
        $this->add_action_buttons(true, get_string('createprofileduser', 'local_orgprofile'));
        $this->set_data($defaults);
    }

    /** Delegate all security and configured-rule validation to the provisioning service. */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        $definition = $this->_customdata['definition'];
        $errors += (new provisioning_service())->validate_company_user(
            (int) $definition->company->id,
            (int) $definition->usertype->id,
            $data
        );
        return $errors;
    }

    /** Add one configured dynamic field using safe Moodle form controls. */
    private function add_dynamic_element(\MoodleQuickForm $mform, string $name, object $field,
            validation_service $validator): void {
        $label = format_string($field->name);
        if (($field->corefield ?? '') === 'country') {
            $countries = ['' => get_string('selectacountry')] + get_string_manager()->get_list_of_countries();
            $mform->addElement('select', $name, $label, $countries);
            return;
        }
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
                $mform->addElement('text', $name, $label, ['maxlength' => 1333, 'size' => 50]);
                $mform->setType($name, match ($field->datatype) {
                    'email' => PARAM_EMAIL,
                    'integer' => PARAM_INT,
                    'url' => PARAM_URL,
                    default => PARAM_TEXT,
                });
        }
    }
}
