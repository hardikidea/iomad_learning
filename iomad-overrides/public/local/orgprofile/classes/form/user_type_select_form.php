<?php
// This file is part of Moodle - https://moodle.org/

namespace local_orgprofile\form;

/** First step of company user creation: select a business user type. */
final class user_type_select_form extends \moodleform {

    /** Define a company-locked user-type selector. */
    protected function definition(): void {
        global $DB;
        $mform = $this->_form;
        $mapping = $this->_customdata['mapping'];
        $company = $this->_customdata['company'];
        $mform->addElement('static', 'companydisplay', get_string('company', 'local_orgprofile'),
            format_string($company->name));
        $types = $DB->get_records_menu('local_orgprofile_usertype', [
            'orgtypeid' => $mapping->orgtypeid,
            'enabled' => 1,
        ], 'sortorder ASC, name ASC', 'id,name');
        $mform->addElement('select', 'usertypeid', get_string('usertype', 'local_orgprofile'),
            ['' => get_string('choose')] + $types);
        $mform->addRule('usertypeid', get_string('required'), 'required', null, 'server');
        $mform->addElement('hidden', 'companyid', $company->id);
        $mform->setType('companyid', PARAM_INT);
        $this->add_action_buttons(true, get_string('continue'));
    }

    /** Reject a crafted user type outside the company's immutable organization type. */
    public function validation($data, $files): array {
        global $DB;
        $errors = parent::validation($data, $files);
        $mapping = $this->_customdata['mapping'];
        if (empty($data['usertypeid']) || !$DB->record_exists('local_orgprofile_usertype', [
            'id' => (int) $data['usertypeid'],
            'orgtypeid' => (int) $mapping->orgtypeid,
            'enabled' => 1,
        ])) {
            $errors['usertypeid'] = get_string('invalidrelationship', 'local_orgprofile');
        }
        return $errors;
    }
}
