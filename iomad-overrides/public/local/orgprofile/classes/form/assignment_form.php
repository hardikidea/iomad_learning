<?php
// This file is part of Moodle - https://moodle.org/

namespace local_orgprofile\form;

/** Company-scoped user type assignment form. */
final class assignment_form extends \moodleform {
    protected function definition(): void {
        global $DB;
        $companyid = (int) $this->_customdata['companyid'];
        $mapping = $this->_customdata['mapping'];
        $mform = $this->_form;
        $mform->addElement('hidden', 'companyid', $companyid);
        $mform->setType('companyid', PARAM_INT);
        $sql = "SELECT DISTINCT u.id, " . $DB->sql_concat('u.firstname', "' '", 'u.lastname') . " AS fullname
                  FROM {user} u
                  JOIN {local_iomad_company_users} cu ON cu.userid = u.id
                 WHERE cu.companyid = :companyid AND u.deleted = 0
              ORDER BY fullname ASC";
        $users = $DB->get_records_sql_menu($sql, ['companyid' => $companyid]);
        $mform->addElement('autocomplete', 'userid', get_string('user', 'local_orgprofile'), $users);
        $mform->addRule('userid', get_string('required'), 'required', null, 'client');
        $mform->addElement('select', 'usertypeid', get_string('usertype', 'local_orgprofile'),
            $DB->get_records_menu('local_orgprofile_usertype', [
                'orgtypeid' => $mapping->orgtypeid,
                'enabled' => 1,
            ], 'sortorder ASC, name ASC', 'id,name'));
        $mform->addElement('select', 'formid', get_string('profileform', 'local_orgprofile'),
            [0 => get_string('none')] + $DB->get_records_menu('local_orgprofile_form', [
                'orgtypeid' => $mapping->orgtypeid,
                'enabled' => 1,
            ], 'name ASC', 'id,name'));
        $mform->addElement('select', 'status', get_string('status', 'local_orgprofile'), [
            'active' => get_string('statusactive', 'local_orgprofile'),
            'inactive' => get_string('statusinactive', 'local_orgprofile'),
        ]);
        $this->add_action_buttons(true, get_string('assign', 'local_orgprofile'));
    }

    public function validation($data, $files): array {
        global $DB;
        $errors = parent::validation($data, $files);
        if (!$DB->record_exists('local_iomad_company_users', [
            'userid' => (int) $data['userid'],
            'companyid' => (int) $data['companyid'],
        ])) {
            $errors['userid'] = get_string('invalidcompanyuser', 'local_orgprofile');
        }
        $mapping = $DB->get_record('local_orgprofile_company', ['companyid' => (int) $data['companyid']]);
        $usertype = $DB->get_record('local_orgprofile_usertype', ['id' => (int) $data['usertypeid']]);
        if (!$mapping || !$usertype || (int) $mapping->orgtypeid !== (int) $usertype->orgtypeid) {
            $errors['usertypeid'] = get_string('invalidrelationship', 'local_orgprofile');
        }
        if (!empty($data['formid'])) {
            $form = $DB->get_record('local_orgprofile_form', ['id' => (int) $data['formid']]);
            if (!$form || !$mapping || (int) $form->orgtypeid !== (int) $mapping->orgtypeid ||
                    (!empty($form->usertypeid) && (int) $form->usertypeid !== (int) $data['usertypeid'])) {
                $errors['formid'] = get_string('invalidrelationship', 'local_orgprofile');
            }
        }
        return $errors;
    }
}
