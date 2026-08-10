<?php
// This file is part of Moodle - https://moodle.org/

namespace local_orgprofile\form;

use local_orgprofile\local\service\validation_service;

/** Forms API editor for configuration library entities. */
final class entity_form extends \moodleform {

    protected function definition(): void {
        global $DB;
        $mform = $this->_form;
        $entity = $this->_customdata['entity'];
        $record = $this->_customdata['record'] ?? null;
        $mform->addElement('hidden', 'entity', $entity);
        $mform->setType('entity', PARAM_ALPHA);
        $mform->addElement('hidden', 'id', $record->id ?? 0);
        $mform->setType('id', PARAM_INT);

        if (in_array($entity, ['usertype', 'form'], true)) {
            $mform->addElement('select', 'orgtypeid', get_string('orgtype', 'local_orgprofile'),
                $DB->get_records_menu('local_orgprofile_orgtype', [], 'sortorder ASC, name ASC', 'id,name'));
        }
        if ($entity === 'form') {
            $mform->addElement('select', 'usertypeid', get_string('usertype', 'local_orgprofile'),
                [0 => get_string('none')] +
                $DB->get_records_menu('local_orgprofile_usertype', [], 'sortorder ASC, name ASC', 'id,name'));
        }
        if ($entity === 'category') {
            $mform->addElement('select', 'formid', get_string('profileform', 'local_orgprofile'),
                $DB->get_records_menu('local_orgprofile_form', [], 'name ASC', 'id,name'));
        }
        if (in_array($entity, ['orgtype', 'usertype', 'form', 'category', 'field'], true)) {
            $mform->addElement('text', 'name', get_string('name', 'local_orgprofile'), ['maxlength' => 255]);
            $mform->setType('name', PARAM_TEXT);
            $mform->addRule('name', get_string('required'), 'required', null, 'client');
            $mform->addElement('text', 'shortname', get_string('shortname', 'local_orgprofile'), ['maxlength' => 100]);
            $mform->setType('shortname', PARAM_ALPHANUMEXT);
            $mform->addRule('shortname', get_string('required'), 'required', null, 'client');
        }
        if (in_array($entity, ['orgtype', 'form', 'field'], true)) {
            $mform->addElement('textarea', 'description', get_string('description', 'local_orgprofile'),
                ['rows' => 4, 'cols' => 60]);
            $mform->setType('description', PARAM_CLEANHTML);
        }
        if (in_array($entity, ['orgtype', 'usertype', 'category'], true)) {
            $mform->addElement('text', 'sortorder', get_string('sortorder', 'local_orgprofile'));
            $mform->setType('sortorder', PARAM_INT);
            $mform->setDefault('sortorder', 0);
        }
        if (in_array($entity, ['orgtype', 'usertype', 'form', 'field'], true)) {
            $mform->addElement('advcheckbox', 'enabled', get_string('enabled', 'local_orgprofile'));
            $mform->setDefault('enabled', 1);
        }
        if ($entity === 'category') {
            $mform->addElement('advcheckbox', 'collapsed', get_string('collapsed', 'local_orgprofile'));
        }
        if ($entity === 'field') {
            $types = array_combine(validation_service::FIELD_TYPES, validation_service::FIELD_TYPES);
            $mform->addElement('select', 'datatype', get_string('datatype', 'local_orgprofile'), $types);
            $corefields = array_combine(validation_service::CORE_FIELDS, validation_service::CORE_FIELDS);
            $mform->addElement('select', 'corefield', get_string('corefield', 'local_orgprofile'),
                ['' => get_string('notcorefield', 'local_orgprofile')] + $corefields);
            $mform->addElement('textarea', 'defaultvalue', get_string('defaultvalue', 'local_orgprofile'),
                ['rows' => 2, 'cols' => 60]);
            $mform->setType('defaultvalue', PARAM_TEXT);
            $mform->addElement('advcheckbox', 'required', get_string('required', 'local_orgprofile'));
            $mform->addElement('select', 'uniquescope', get_string('uniquescope', 'local_orgprofile'), [
                'none' => get_string('uniquenone', 'local_orgprofile'),
                'company' => get_string('uniquecompany', 'local_orgprofile'),
                'site' => get_string('uniquesite', 'local_orgprofile'),
            ]);
            $mform->addElement('advcheckbox', 'readonly', get_string('readonly', 'local_orgprofile'));
            $mform->addElement('advcheckbox', 'visible', get_string('visible', 'local_orgprofile'));
            $mform->setDefault('visible', 1);
            $mform->addElement('advcheckbox', 'sensitive', get_string('sensitive', 'local_orgprofile'));
            $mform->addElement('textarea', 'optionsjson', get_string('optionsjson', 'local_orgprofile'),
                ['rows' => 5, 'cols' => 70]);
            $mform->setType('optionsjson', PARAM_RAW_TRIMMED);
            $mform->addElement('textarea', 'validationjson', get_string('validationjson', 'local_orgprofile'),
                ['rows' => 5, 'cols' => 70]);
            $mform->setType('validationjson', PARAM_RAW_TRIMMED);
        }
        $this->add_action_buttons(true);
        if ($record) {
            $this->set_data($record);
        }
    }

    public function validation($data, $files): array {
        global $DB;
        $errors = parent::validation($data, $files);
        $entity = $data['entity'];
        if (isset($data['shortname']) && !preg_match('/^[a-z0-9_]+$/', $data['shortname'])) {
            $errors['shortname'] = get_string('invalidshortname', 'local_orgprofile');
        }
        $table = [
            'orgtype' => 'local_orgprofile_orgtype', 'usertype' => 'local_orgprofile_usertype',
            'form' => 'local_orgprofile_form', 'category' => 'local_orgprofile_category',
            'field' => 'local_orgprofile_field',
        ][$entity] ?? null;
        if ($table && isset($data['shortname'])) {
            $conditions = ['shortname' => $data['shortname']];
            if ($entity === 'usertype') {
                $conditions['orgtypeid'] = $data['orgtypeid'];
            } else if ($entity === 'category') {
                $conditions['formid'] = $data['formid'];
            }
            $duplicate = $DB->get_record($table, $conditions, 'id');
            if ($duplicate && (int) $duplicate->id !== (int) $data['id']) {
                $errors['shortname'] = get_string('duplicateshortname', 'local_orgprofile');
            }
        }
        if ($entity === 'field') {
            $errors += (new validation_service())->configuration_errors((object) $data);
        }
        return $errors;
    }
}
