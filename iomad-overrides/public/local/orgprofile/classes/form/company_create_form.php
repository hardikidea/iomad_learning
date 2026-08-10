<?php
// This file is part of Moodle - https://moodle.org/

namespace local_orgprofile\form;

/** Form for creating an IOMAD company with a required organization type. */
final class company_create_form extends \moodleform {

    /** Define the company and immutable organization-type fields. */
    protected function definition(): void {
        global $DB;
        $mform = $this->_form;
        $required = get_string('required');
        $orgtypes = $DB->get_records_menu(
            'local_orgprofile_orgtype',
            ['enabled' => 1],
            'sortorder ASC, name ASC',
            'id,name'
        );
        $mform->addElement('select', 'orgtypeid', get_string('orgtype', 'local_orgprofile'),
            ['' => get_string('choose')] + $orgtypes);
        $mform->addRule('orgtypeid', $required, 'required', null, 'server');
        $mform->addHelpButton('orgtypeid', 'companyorgtype', 'local_orgprofile');

        $mform->addElement('text', 'name', get_string('companyname', 'block_iomad_company_admin'),
            ['maxlength' => 50, 'size' => 50]);
        $mform->setType('name', PARAM_NOTAGS);
        $mform->addRule('name', $required, 'required', null, 'server');
        $mform->addElement('text', 'shortname', get_string('companyshortname', 'block_iomad_company_admin'),
            ['maxlength' => 25, 'size' => 30]);
        $mform->setType('shortname', PARAM_NOTAGS);
        $mform->addRule('shortname', $required, 'required', null, 'server');
        $mform->addElement('text', 'code', get_string('companycode', 'block_iomad_company_admin'),
            ['maxlength' => 255, 'size' => 30]);
        $mform->setType('code', PARAM_NOTAGS);
        $mform->addElement('textarea', 'address', get_string('address'), ['rows' => 3, 'cols' => 50]);
        $mform->setType('address', PARAM_TEXT);
        $mform->addElement('text', 'city', get_string('city'), ['maxlength' => 120, 'size' => 40]);
        $mform->setType('city', PARAM_TEXT);
        $mform->addRule('city', $required, 'required', null, 'server');
        $mform->addElement('text', 'region', get_string('region', 'local_orgprofile'), ['maxlength' => 120, 'size' => 40]);
        $mform->setType('region', PARAM_TEXT);
        $mform->addElement('text', 'postcode', get_string('postcode', 'local_orgprofile'),
            ['maxlength' => 20, 'size' => 20]);
        $mform->setType('postcode', PARAM_TEXT);
        $countries = ['' => get_string('selectacountry')] + get_string_manager()->get_list_of_countries();
        $mform->addElement('select', 'country', get_string('country'), $countries);
        $mform->addRule('country', $required, 'required', null, 'server');
        $mform->addElement('text', 'maxusers', get_string('maxusers', 'block_iomad_company_admin'));
        $mform->setType('maxusers', PARAM_INT);
        $mform->setDefault('maxusers', 0);
        $this->add_action_buttons(true, get_string('createcompanyprofiled', 'local_orgprofile'));
    }

    /** Enforce IOMAD company identity constraints before calling the API. */
    public function validation($data, $files): array {
        global $DB;
        $errors = parent::validation($data, $files);
        if (empty($data['orgtypeid']) || !$DB->record_exists('local_orgprofile_orgtype', [
            'id' => (int) $data['orgtypeid'],
            'enabled' => 1,
        ])) {
            $errors['orgtypeid'] = get_string('required');
        }
        if (!preg_match('/^[A-Za-z0-9_]+$/', trim((string) ($data['shortname'] ?? '')))) {
            $errors['shortname'] = get_string('invalidcompanyshortname', 'local_orgprofile');
        }
        foreach (['name', 'shortname', 'code'] as $field) {
            $value = trim((string) ($data[$field] ?? ''));
            if ($value !== '' && $DB->record_exists('local_iomad_companies', [$field => $value])) {
                $errors[$field] = get_string('companyfieldexists', 'local_orgprofile');
            }
        }
        if (!empty($data['country']) &&
                !array_key_exists($data['country'], get_string_manager()->get_list_of_countries())) {
            $errors['country'] = get_string('invalidvalue', 'local_orgprofile');
        }
        return $errors;
    }
}
