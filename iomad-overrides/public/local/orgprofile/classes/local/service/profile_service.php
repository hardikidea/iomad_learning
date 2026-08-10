<?php
// This file is part of Moodle - https://moodle.org/

namespace local_orgprofile\local\service;

use invalid_parameter_exception;
use local_orgprofile\event\profile_updated;
use local_orgprofile\event\user_type_assigned;
use stdClass;

/**
 * Resolves, validates, and stores company-scoped user profiles.
 *
 * @package local_orgprofile
 */
final class profile_service {

    /** @var authorization_service Authorization policy. */
    private authorization_service $authorization;

    /** @var form_service Form resolver. */
    private form_service $forms;

    /** @var validation_service Value validator. */
    private validation_service $validation;

    /** Construct the service. */
    public function __construct(?authorization_service $authorization = null, ?form_service $forms = null,
            ?validation_service $validation = null) {
        $this->authorization = $authorization ?? new authorization_service();
        $this->forms = $forms ?? new form_service();
        $this->validation = $validation ?? new validation_service();
    }

    /** Assign one active business user type to a concrete user/company relationship. */
    public function assign_user_type(int $userid, int $companyid, int $usertypeid, ?int $formid = null,
            string $status = 'active'): int {
        global $DB;
        if (!$this->authorization->can_assign_user_type($userid, $companyid)) {
            $context = $companyid > 0
                ? \local_iomad\custom_context\context_company::instance($companyid, IGNORE_MISSING) : false;
            throw new \required_capability_exception(
                $context ?: \context_system::instance(),
                'local/orgprofile:assignusertype',
                'nopermissions',
                'local_orgprofile'
            );
        }
        if (!in_array($status, ['active', 'inactive'], true)) {
            throw new invalid_parameter_exception(get_string('invalidvalue', 'local_orgprofile'));
        }
        $mapping = $DB->get_record('local_orgprofile_company', ['companyid' => $companyid], '*', MUST_EXIST);
        $usertype = $DB->get_record('local_orgprofile_usertype', ['id' => $usertypeid], '*', MUST_EXIST);
        if ((int) $usertype->orgtypeid !== (int) $mapping->orgtypeid) {
            throw new invalid_parameter_exception(get_string('invalidrelationship', 'local_orgprofile'));
        }
        if ($formid) {
            $form = $DB->get_record('local_orgprofile_form', ['id' => $formid], '*', MUST_EXIST);
            if ((int) $form->orgtypeid !== (int) $mapping->orgtypeid ||
                    (!empty($form->usertypeid) && (int) $form->usertypeid !== $usertypeid)) {
                throw new invalid_parameter_exception(get_string('invalidrelationship', 'local_orgprofile'));
            }
        }
        $now = time();
        $record = $DB->get_record('local_orgprofile_user', ['userid' => $userid, 'companyid' => $companyid]);
        if ($record) {
            $record->usertypeid = $usertypeid;
            $record->formid = $formid ?: null;
            $record->status = $status;
            $record->timemodified = $now;
            $DB->update_record('local_orgprofile_user', $record);
            $id = (int) $record->id;
        } else {
            $id = $DB->insert_record('local_orgprofile_user', (object) [
                'userid' => $userid,
                'companyid' => $companyid,
                'usertypeid' => $usertypeid,
                'formid' => $formid ?: null,
                'status' => $status,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }
        user_type_assigned::create_for_assignment($id, $userid, $companyid)->trigger();
        return $id;
    }

    /** Load all data needed to render an authorized profile. */
    public function get_profile(int $userid, int $companyid): stdClass {
        global $CFG, $DB, $USER;
        $this->authorization->require_view_profile($userid, $companyid);
        $company = $DB->get_record('local_iomad_companies', ['id' => $companyid], 'id,name,shortname', MUST_EXIST);
        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', MUST_EXIST);
        $mapping = $DB->get_record('local_orgprofile_company', ['companyid' => $companyid], '*', MUST_EXIST);
        $assignment = $DB->get_record('local_orgprofile_user', [
            'userid' => $userid,
            'companyid' => $companyid,
            'status' => 'active',
        ], '*', MUST_EXIST);
        $form = $this->forms->resolve_form($companyid, $userid);
        $categories = $this->forms->get_form_structure($form->id);
        $values = $DB->get_records('local_orgprofile_value', ['userid' => $userid, 'companyid' => $companyid], '',
            'id,fieldid,value,valuejson');
        $byfield = [];
        foreach ($values as $value) {
            $byfield[$value->fieldid] = $value;
        }
        $canviewsensitive = $this->authorization->can_view_sensitive($companyid);
        foreach ($categories as $category) {
            $visible = [];
            foreach ($category->fields as $field) {
                if (empty($field->effective_visible) || (!empty($field->sensitive) && !$canviewsensitive)) {
                    continue;
                }
                if (($field->corefield ?? '') === 'email' && (int) $USER->id === $userid &&
                        !empty($CFG->verifychangedemail) &&
                        !has_capability('moodle/user:update', \context_system::instance())) {
                    // Preserve Moodle's confirmation workflow; self-service email changes belong on the core profile page.
                    $field->effective_readonly = 1;
                }
                if (!empty($field->corefield)) {
                    $field->currentvalue = $user->{$field->corefield};
                } else if (isset($byfield[$field->id])) {
                    $field->currentvalue = $byfield[$field->id]->value;
                } else {
                    $field->currentvalue = $field->defaultvalue ?? '';
                }
                $visible[] = $field;
            }
            $category->fields = $visible;
        }
        return (object) compact('user', 'company', 'mapping', 'assignment', 'form', 'categories');
    }

    /**
     * Validate a dynamic profile submission.
     *
     * @param int $userid Target user ID.
     * @param int $companyid Exact IOMAD company ID.
     * @param array $submitted Submitted values keyed by Moodle form element name.
     * @return array Moodle form errors keyed by element name.
     */
    public function validate_submission(int $userid, int $companyid, array $submitted): array {
        global $DB;
        $profile = $this->get_profile($userid, $companyid);
        $errors = [];
        $canedit = $this->authorization->can_edit_profile($userid, $companyid);
        $caneditsensitive = $this->authorization->can_edit_sensitive($companyid);
        if (!$canedit) {
            return ['_profile' => get_string('nopermissions', 'error')];
        }
        foreach ($profile->categories as $category) {
            foreach ($category->fields as $field) {
                if (!empty($field->effective_readonly) || (!empty($field->sensitive) && !$caneditsensitive)) {
                    continue;
                }
                $element = $this->element_name($field);
                try {
                    if (in_array($field->corefield ?? '', ['firstname', 'lastname', 'email'], true)) {
                        $field->effective_required = 1;
                    }
                    $value = $this->validation->normalize_value($field, $submitted[$element] ?? '');
                    if ($error = $this->validation->validate_value($field, $value)) {
                        $errors[$element] = $error;
                        continue;
                    }
                    if (empty($field->corefield) && $value !== '') {
                        $uniquekey = $this->validation->unique_key($field, $companyid, $value);
                        if ($uniquekey) {
                            $existing = $DB->get_record('local_orgprofile_value', [
                                'fieldid' => $field->id,
                                'uniquekey' => $uniquekey,
                            ], 'id,userid,companyid');
                            if ($existing && ((int) $existing->userid !== $userid ||
                                    (int) $existing->companyid !== $companyid)) {
                                $errors[$element] = get_string('valuealreadyused', 'local_orgprofile');
                            }
                        }
                    } else if (($field->corefield ?? '') === 'email') {
                        $this->validate_core_email($userid, $value, $errors, $element);
                    }
                } catch (invalid_parameter_exception $exception) {
                    $errors[$element] = $exception->getMessage();
                }
            }
        }
        return $errors;
    }

    /** Validate and atomically persist editable profile fields. */
    public function save_profile(int $userid, int $companyid, array $submitted): void {
        global $CFG, $DB;
        $this->authorization->require_edit_profile($userid, $companyid);
        $errors = $this->validate_submission($userid, $companyid, $submitted);
        if ($errors) {
            throw new invalid_parameter_exception(reset($errors));
        }
        $profile = $this->get_profile($userid, $companyid);
        $caneditsensitive = $this->authorization->can_edit_sensitive($companyid);
        $native = (object) ['id' => $userid];
        $hasnative = false;
        $custom = [];
        foreach ($profile->categories as $category) {
            foreach ($category->fields as $field) {
                if (!empty($field->effective_readonly) || (!empty($field->sensitive) && !$caneditsensitive)) {
                    continue;
                }
                $element = $this->element_name($field);
                $value = $this->validation->normalize_value($field, $submitted[$element] ?? '');
                if (!empty($field->corefield)) {
                    $native->{$field->corefield} = $value;
                    $hasnative = true;
                } else {
                    $custom[] = [$field, $value];
                }
            }
        }

        $transaction = $DB->start_delegated_transaction();
        $now = time();
        foreach ($custom as [$field, $value]) {
            $existing = $DB->get_record('local_orgprofile_value', [
                'userid' => $userid,
                'companyid' => $companyid,
                'fieldid' => $field->id,
            ]);
            if ($value === '') {
                if ($existing) {
                    $DB->delete_records('local_orgprofile_value', ['id' => $existing->id]);
                }
                continue;
            }
            $record = $existing ?: (object) [
                'userid' => $userid,
                'companyid' => $companyid,
                'fieldid' => $field->id,
                'timecreated' => $now,
            ];
            $record->value = $value;
            $record->valuejson = null;
            $record->uniquekey = $this->validation->unique_key($field, $companyid, $value);
            $record->timemodified = $now;
            if ($existing) {
                $DB->update_record('local_orgprofile_value', $record);
            } else {
                $DB->insert_record('local_orgprofile_value', $record);
            }
        }
        if ($hasnative) {
            require_once($CFG->dirroot . '/user/lib.php');
            $nativeerrors = \core_user::validate($native);
            if ($nativeerrors !== true) {
                throw new invalid_parameter_exception(reset($nativeerrors));
            }
            user_update_user($native, false, true);
        }
        profile_updated::create_for_profile($userid, $companyid, $profile->form->id)->trigger();
        $transaction->allow_commit();
    }

    /** Return the stable Moodle form element name for a field. */
    public function element_name(stdClass $field): string {
        return empty($field->corefield) ? 'field_' . $field->id : 'core_' . $field->corefield;
    }

    /** Apply the same duplicate-email rule used by Moodle's user edit forms. */
    private function validate_core_email(int $userid, string $email, array &$errors, string $element): void {
        global $CFG, $DB;
        if (!validate_email($email)) {
            $errors[$element] = get_string('invalidemail');
            return;
        }
        if (empty($CFG->allowaccountssameemail)) {
            $select = $DB->sql_equal('email', ':email', false) .
                ' AND mnethostid = :mnethostid AND id <> :userid';
            if ($DB->record_exists_select('user', $select, [
                'email' => $email,
                'mnethostid' => $CFG->mnet_localhost_id,
                'userid' => $userid,
            ])) {
                $errors[$element] = get_string('emailexists');
            }
        }
    }
}
