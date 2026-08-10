<?php
// This file is part of Moodle - https://moodle.org/

namespace local_orgprofile\form;

/** Form-field placement editor. */
final class form_field_form extends \moodleform {
    protected function definition(): void {
        global $DB;
        $mform = $this->_form;
        $formid = (int) $this->_customdata['formid'];
        $record = $this->_customdata['record'] ?? null;
        $mform->addElement('hidden', 'formid', $formid);
        $mform->setType('formid', PARAM_INT);
        $mform->addElement('hidden', 'id', $record->id ?? 0);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('select', 'categoryid', get_string('category', 'local_orgprofile'),
            $DB->get_records_menu('local_orgprofile_category', ['formid' => $formid], 'sortorder ASC, name ASC', 'id,name'));
        $mform->addElement('select', 'fieldid', get_string('field', 'local_orgprofile'),
            $DB->get_records_menu('local_orgprofile_field', ['enabled' => 1], 'name ASC', 'id,name'));
        $mform->addElement('text', 'sortorder', get_string('sortorder', 'local_orgprofile'));
        $mform->setType('sortorder', PARAM_INT);
        $choices = [
            '' => get_string('overrideinherit', 'local_orgprofile'),
            '1' => get_string('overrideyes', 'local_orgprofile'),
            '0' => get_string('overrideno', 'local_orgprofile'),
        ];
        $mform->addElement('select', 'requiredoverride', get_string('required', 'local_orgprofile'), $choices);
        $mform->addElement('select', 'readonlyoverride', get_string('readonly', 'local_orgprofile'), $choices);
        $mform->addElement('select', 'visibleoverride', get_string('visible', 'local_orgprofile'), $choices);
        $this->add_action_buttons(true);
        if ($record) {
            foreach (['requiredoverride', 'readonlyoverride', 'visibleoverride'] as $name) {
                if ($record->{$name} === null) {
                    $record->{$name} = '';
                }
            }
            $this->set_data($record);
        }
    }
}
