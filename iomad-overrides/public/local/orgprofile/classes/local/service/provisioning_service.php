<?php
// This file is part of Moodle - https://moodle.org/

namespace local_orgprofile\local\service;

use core_user;
use core_text;
use invalid_parameter_exception;
use local_iomad\company;
use local_iomad\company_user;
use local_iomad\custom_context\context_company;
use local_iomad\iomad;
use stdClass;

/**
 * Guided IOMAD company and user provisioning with organization profiles.
 *
 * @package local_orgprofile
 */
final class provisioning_service {

    /** Create an IOMAD company and its immutable organization mapping atomically. */
    public function create_company(stdClass $data): company {
        global $CFG, $DB;
        require_capability('block/iomad_company_admin:company_add', \context_system::instance());
        $orgtypeid = (int) ($data->orgtypeid ?? 0);
        $DB->get_record('local_orgprofile_orgtype', ['id' => $orgtypeid, 'enabled' => 1], 'id', MUST_EXIST);
        $name = trim((string) ($data->name ?? ''));
        $shortname = trim((string) ($data->shortname ?? ''));
        $city = trim((string) ($data->city ?? ''));
        $country = trim((string) ($data->country ?? ''));
        if ($name === '' || core_text::strlen($name) > 50 || $shortname === '' ||
                core_text::strlen($shortname) > 25 || $city === '' || $country === '' ||
                !preg_match('/^[A-Za-z0-9_]+$/', $shortname)) {
            throw new invalid_parameter_exception(get_string('invalidvalue', 'local_orgprofile'));
        }
        if (!array_key_exists($country, get_string_manager()->get_list_of_countries())) {
            throw new invalid_parameter_exception(get_string('invalidvalue', 'local_orgprofile'));
        }
        foreach (['name' => $name, 'shortname' => $shortname] as $field => $value) {
            if ($DB->record_exists('local_iomad_companies', [$field => $value])) {
                throw new invalid_parameter_exception(get_string('companyfieldexists', 'local_orgprofile'));
            }
        }
        $code = trim((string) ($data->code ?? ''));
        if (core_text::strlen($code) > 255) {
            throw new invalid_parameter_exception(get_string('invalidvalue', 'local_orgprofile'));
        }
        if ($code !== '' && $DB->record_exists('local_iomad_companies', ['code' => $code])) {
            throw new invalid_parameter_exception(get_string('companyfieldexists', 'local_orgprofile'));
        }
        $companydata = (object) [
            'name' => clean_param($name, PARAM_NOTAGS),
            'shortname' => clean_param($shortname, PARAM_NOTAGS),
            'code' => clean_param($code, PARAM_NOTAGS),
            'address' => clean_param($data->address ?? '', PARAM_TEXT),
            'city' => clean_param($city, PARAM_TEXT),
            'region' => clean_param($data->region ?? '', PARAM_TEXT),
            'postcode' => clean_param($data->postcode ?? '', PARAM_TEXT),
            'country' => clean_param($country, PARAM_ALPHA),
            'theme' => $CFG->theme,
            'maxusers' => max(0, (int) ($data->maxusers ?? 0)),
            'parentid' => 0,
            'templates' => [],
        ];
        $transaction = $DB->start_delegated_transaction();
        $company = company::create_company($companydata);
        (new organization_service())->map_company((int) $company->id, $orgtypeid);
        $transaction->allow_commit();
        return $company;
    }

    /** Return whether the current actor can use the profiled-user workflow in one company. */
    public function can_create_company_user(int $companyid): bool {
        global $USER;
        if ($companyid <= 0 || !context_company::instance($companyid, IGNORE_MISSING)) {
            return false;
        }
        if (is_siteadmin()) {
            return true;
        }
        $authorization = new authorization_service();
        if (!$authorization->is_company_member((int) $USER->id, $companyid, true)) {
            return false;
        }
        $context = context_company::instance($companyid);
        return iomad::has_capability('block/iomad_company_admin:user_create', $context, $companyid)
            && iomad::has_capability('local/orgprofile:assignusertype', $context, $companyid)
            && iomad::has_capability('local/orgprofile:editcompany', $context, $companyid);
    }

    /** Require the combined IOMAD and organization-profile permissions for user creation. */
    public function require_create_company_user(int $companyid): void {
        if (!$this->can_create_company_user($companyid)) {
            $context = context_company::instance($companyid, IGNORE_MISSING) ?: \context_system::instance();
            throw new \required_capability_exception(
                $context,
                'block/iomad_company_admin:user_create',
                'nopermissions',
                'local_orgprofile'
            );
        }
    }

    /** Return the mapped company, selected user type, resolved form, and visible form structure. */
    public function get_creation_definition(int $companyid, int $usertypeid): stdClass {
        global $DB;
        $this->require_create_company_user($companyid);
        $companyrecord = $DB->get_record('local_iomad_companies', ['id' => $companyid], 'id,name,shortname', MUST_EXIST);
        $mapping = $DB->get_record('local_orgprofile_company', ['companyid' => $companyid], '*', MUST_EXIST);
        $usertype = $DB->get_record('local_orgprofile_usertype', [
            'id' => $usertypeid,
            'orgtypeid' => $mapping->orgtypeid,
            'enabled' => 1,
        ], '*', MUST_EXIST);
        $forms = new form_service();
        $form = $forms->resolve_form_for_user_type($companyid, $usertypeid);
        $categories = $forms->get_form_structure((int) $form->id);
        $canviewsensitive = (new authorization_service())->can_view_sensitive($companyid);
        foreach ($categories as $category) {
            $category->fields = array_values(array_filter(
                $category->fields,
                static fn(stdClass $field): bool => !empty($field->effective_visible)
                    && (empty($field->sensitive) || $canviewsensitive)
            ));
        }
        return (object) [
            'company' => $companyrecord,
            'mapping' => $mapping,
            'usertype' => $usertype,
            'form' => $form,
            'categories' => $categories,
        ];
    }

    /** Validate account data and every editable configured profile field before creation. */
    public function validate_company_user(int $companyid, int $usertypeid, array $submitted): array {
        global $CFG, $DB;
        $definition = $this->get_creation_definition($companyid, $usertypeid);
        $errors = [];
        $company = new company($companyid);
        if (!$company->check_usercount(1)) {
            $errors['workflowstatus'] = get_string(
                'maxuserswarning',
                'block_iomad_company_admin',
                $company->get('maxusers')
            );
        }
        foreach (['firstname', 'lastname', 'email'] as $fieldname) {
            $element = 'core_' . $fieldname;
            if (trim((string) ($submitted[$element] ?? '')) === '') {
                $errors[$element] = get_string('required');
            }
        }
        $email = core_text::strtolower(trim((string) ($submitted['core_email'] ?? '')));
        if ($email !== '' && !validate_email($email)) {
            $errors['core_email'] = get_string('invalidemail');
        } else if ($email !== '' && empty($CFG->allowaccountssameemail) && $DB->record_exists('user', [
            'email' => $email,
            'mnethostid' => $CFG->mnet_localhost_id,
        ])) {
            $errors['core_email'] = get_string('emailexists');
        }
        $username = $this->resolve_username($submitted, $email);
        if ($username === '') {
            $errors['username'] = get_string('required');
        } else if ($username !== core_text::strtolower($username)) {
            $errors['username'] = get_string('usernamelowercase');
        } else if ($username !== core_user::clean_field($username, 'username')) {
            $errors['username'] = get_string('invalidusername');
        } else if ($DB->record_exists('user', [
            'username' => $username,
            'mnethostid' => $CFG->mnet_localhost_id,
        ])) {
            $errors['username'] = get_string('usernameexists');
        }
        if (!empty($submitted['newpassword'])) {
            $passworderror = '';
            if (!check_password_policy((string) $submitted['newpassword'], $passworderror)) {
                $errors['newpassword'] = $passworderror;
            }
        }
        if (!empty($submitted['sendnewpasswordemails']) && empty($submitted['preference_auth_forcepasswordchange'])) {
            $errors['preference_auth_forcepasswordchange'] = get_string('passwordemailrequiresforcechange', 'local_orgprofile');
        }

        $validator = new validation_service();
        $profiles = new profile_service();
        $caneditsensitive = (new authorization_service())->can_edit_sensitive($companyid);
        foreach ($definition->categories as $category) {
            foreach ($category->fields as $field) {
                if (!empty($field->effective_readonly) || (!empty($field->sensitive) && !$caneditsensitive)) {
                    continue;
                }
                $element = $profiles->element_name($field);
                try {
                    if (in_array($field->corefield ?? '', ['firstname', 'lastname', 'email'], true)) {
                        $field->effective_required = 1;
                    }
                    $value = $validator->normalize_value($field, $submitted[$element] ?? '');
                    if ($error = $validator->validate_value($field, $value)) {
                        $errors[$element] = $error;
                        continue;
                    }
                    if (empty($field->corefield) && $value !== '') {
                        $uniquekey = $validator->unique_key($field, $companyid, $value);
                        if ($uniquekey && $DB->record_exists('local_orgprofile_value', [
                            'fieldid' => $field->id,
                            'uniquekey' => $uniquekey,
                        ])) {
                            $errors[$element] = get_string('valuealreadyused', 'local_orgprofile');
                        }
                    }
                } catch (invalid_parameter_exception $exception) {
                    $errors[$element] = $exception->getMessage();
                }
            }
        }
        return $errors;
    }

    /** Create the Moodle account, exact IOMAD membership, assignment, and profile values atomically. */
    public function create_company_user(int $companyid, int $usertypeid, array $submitted): int {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/user/lib.php');
        $this->require_create_company_user($companyid);
        if ($errors = $this->validate_company_user($companyid, $usertypeid, $submitted)) {
            throw new invalid_parameter_exception(reset($errors));
        }
        $definition = $this->get_creation_definition($companyid, $usertypeid);
        $email = core_text::strtolower(trim((string) $submitted['core_email']));
        $userdata = (object) [
            'firstname' => trim((string) $submitted['core_firstname']),
            'lastname' => trim((string) $submitted['core_lastname']),
            'email' => $email,
            'username' => $this->resolve_username($submitted, $email),
            'use_email_as_username' => !empty($submitted['use_email_as_username']) ? 1 : 0,
            'newpassword' => (string) ($submitted['newpassword'] ?? ''),
            'sendnewpasswordemails' => !empty($submitted['sendnewpasswordemails']) ? 1 : 0,
            'preference_auth_forcepasswordchange' => !empty($submitted['preference_auth_forcepasswordchange']) ? 1 : 0,
            'due' => time(),
            'managertype' => 0,
        ];
        foreach (['phone1', 'phone2', 'city', 'country'] as $corefield) {
            if (array_key_exists('core_' . $corefield, $submitted)) {
                $userdata->{$corefield} = $submitted['core_' . $corefield];
            }
        }
        $department = company::get_company_parentnode($companyid);
        $userdata->departmentid = (int) $department->id;

        $transaction = $DB->start_delegated_transaction();
        $userid = company_user::create($userdata, $companyid);
        if (!$userid) {
            throw new \moodle_exception('cannotcreateuser', 'error');
        }
        $profiles = new profile_service();
        $profiles->assign_user_type($userid, $companyid, $usertypeid, (int) $definition->form->id);
        $profiles->save_profile($userid, $companyid, $submitted);
        $transaction->allow_commit();
        return $userid;
    }

    /** Resolve a submitted username using the IOMAD email-as-username convention. */
    private function resolve_username(array $submitted, string $email): string {
        if (!empty($submitted['use_email_as_username'])) {
            return core_text::strtolower($email);
        }
        return trim((string) ($submitted['username'] ?? ''));
    }
}
