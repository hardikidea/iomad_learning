<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\local;

use local_iomad\company;

/**
 * Native Moodle user creation and immediate IOMAD company assignment.
 *
 * Tenant Master never stores a duplicate user profile or a password. Moodle
 * generates a one-time password, sends it through the configured mail service,
 * and requires a change on first login.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class native_user_service {
    /**
     * Create one native user and assign the requested business role.
     *
     * @param object $tenant Tenant.
     * @param object $data Validated form data.
     * @return object Native user with notification status.
     */
    public function create(object $tenant, object $data): object {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/user/lib.php');
        $username = \core_text::strtolower(trim((string)$data->username));
        if ($username === '' || $username !== \core_user::clean_field($username, 'username')) {
            throw new \invalid_parameter_exception('Invalid native username.');
        }
        if (!catalog::valid_external_key((string)$data->idnumber)) {
            throw new \invalid_parameter_exception('A stable user idnumber is required.');
        }
        if (!validate_email((string)$data->email)) {
            throw new \invalid_parameter_exception('A valid email address is required.');
        }
        if (
            $DB->record_exists('user', ['username' => $username, 'mnethostid' => $CFG->mnet_localhost_id])
                || $DB->record_exists('user', ['idnumber' => $data->idnumber, 'deleted' => 0])
        ) {
            throw new \invalid_parameter_exception('The username or user idnumber already exists.');
        }

        $user = (object)[
            'auth' => 'manual',
            'confirmed' => 1,
            'mnethostid' => $CFG->mnet_localhost_id,
            'username' => $username,
            'password' => '',
            'firstname' => trim((string)$data->firstname),
            'lastname' => trim((string)$data->lastname),
            'email' => trim((string)$data->email),
            'idnumber' => trim((string)$data->idnumber),
            'city' => (string)($data->city ?? ''),
            'country' => (string)($data->country ?? ''),
            'lang' => current_language(),
        ];
        $transaction = $DB->start_delegated_transaction();
        $userid = (int)user_create_user($user, false, false);
        $rootdepartment = company::get_company_parentnode((int)$tenant->companyid);
        company::upsert_company_user(
            $userid,
            (int)$tenant->companyid,
            (int)$rootdepartment->id,
            0,
            false,
        );
        (new people_service())->assign_role(
            $tenant,
            $userid,
            (string)$data->rolekey,
            (int)($data->departmentid ?? 0),
            (int)($data->courseid ?? 0),
        );
        \core\event\user_created::create_from_userid($userid)->trigger();
        $transaction->allow_commit();

        $nativeuser = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
        $notified = (bool)setnew_password_and_mail($nativeuser);
        set_user_preference('auth_forcepasswordchange', 1, $nativeuser);
        (new audit_service())->record(
            (int)$tenant->id,
            'people.native_user.created',
            $notified ? 'success' : 'notification_failed',
            ['rolekey' => (string)$data->rolekey],
            ['entitytable' => 'user', 'entityid' => $userid, 'targetcomponent' => 'core/user', 'targetid' => $userid],
        );
        $nativeuser->notificationstatus = $notified ? 'sent' : 'failed';
        return $nativeuser;
    }
}
