<?php
// This file is part of Moodle - https://moodle.org/

namespace local_orgprofile\form;

use local_orgprofile\local\service\validation_service;

/** IOMAD company mapping form. */
final class company_mapping_form extends \moodleform {
    protected function definition(): void {
        global $DB;
        $mform = $this->_form;
        $record = $this->_customdata['record'] ?? null;
        $forcedcompanyid = (int) ($this->_customdata['companyid'] ?? 0);
        $companies = $forcedcompanyid
            ? $DB->get_records_menu('local_iomad_companies', ['id' => $forcedcompanyid], 'name ASC', 'id,name')
            : $DB->get_records_menu('local_iomad_companies', [], 'name ASC', 'id,name');
        $mform->addElement('select', 'companyid', get_string('company', 'local_orgprofile'),
            $companies);
        if ($record || $forcedcompanyid) {
            $mform->freeze('companyid');
        }
        $mform->addElement('select', 'orgtypeid', get_string('orgtype', 'local_orgprofile'),
            $DB->get_records_menu('local_orgprofile_orgtype', ['enabled' => 1], 'sortorder ASC, name ASC', 'id,name'));
        $mform->addRule('orgtypeid', get_string('required'), 'required', null, 'server');
        if ($record) {
            $mform->freeze('orgtypeid');
            $mform->addHelpButton('orgtypeid', 'orgtypeimmutable', 'local_orgprofile');
        }
        $mform->addElement('select', 'defaultformid', get_string('defaultform', 'local_orgprofile'),
            [0 => get_string('none')] + $DB->get_records_menu('local_orgprofile_form', ['enabled' => 1], 'name ASC', 'id,name'));
        $mform->addElement('textarea', 'configjson', get_string('configjson', 'local_orgprofile'),
            ['rows' => 5, 'cols' => 70]);
        $mform->setType('configjson', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('configjson', 'configjson', 'local_orgprofile');
        $this->add_action_buttons(true);
        if ($record) {
            $this->set_data($record);
        } else if ($forcedcompanyid) {
            $this->set_data(['companyid' => $forcedcompanyid]);
        }
    }

    public function validation($data, $files): array {
        global $DB;
        $errors = parent::validation($data, $files);
        if (!empty($data['defaultformid'])) {
            $form = $DB->get_record('local_orgprofile_form', ['id' => $data['defaultformid']]);
            if (!$form || (int) $form->orgtypeid !== (int) $data['orgtypeid']) {
                $errors['defaultformid'] = get_string('invalidrelationship', 'local_orgprofile');
            }
        }
        try {
            (new validation_service())->decode_json($data['configjson'] ?? null);
        } catch (\invalid_parameter_exception $exception) {
            $errors['configjson'] = $exception->getMessage();
        }
        return $errors;
    }
}
