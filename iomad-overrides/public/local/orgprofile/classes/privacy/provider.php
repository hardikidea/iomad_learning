<?php
// This file is part of Moodle - https://moodle.org/

namespace local_orgprofile\privacy;

use context;
use context_user;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\core_userlist_provider;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/** Privacy API provider for company-scoped profile assignments and values. */
final class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider,
        core_userlist_provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_orgprofile_user', [
            'userid' => 'privacy:metadata:local_orgprofile_user:userid',
            'companyid' => 'privacy:metadata:local_orgprofile_user:companyid',
            'usertypeid' => 'privacy:metadata:local_orgprofile_user:usertypeid',
            'formid' => 'privacy:metadata:local_orgprofile_user:formid',
            'status' => 'privacy:metadata:local_orgprofile_user:status',
            'timecreated' => 'privacy:metadata:local_orgprofile_user:timecreated',
            'timemodified' => 'privacy:metadata:local_orgprofile_user:timemodified',
        ], 'privacy:metadata:local_orgprofile_user');
        $collection->add_database_table('local_orgprofile_value', [
            'userid' => 'privacy:metadata:local_orgprofile_value:userid',
            'companyid' => 'privacy:metadata:local_orgprofile_value:companyid',
            'fieldid' => 'privacy:metadata:local_orgprofile_value:fieldid',
            'value' => 'privacy:metadata:local_orgprofile_value:value',
            'valuejson' => 'privacy:metadata:local_orgprofile_value:valuejson',
            'uniquekey' => 'privacy:metadata:local_orgprofile_value:uniquekey',
            'timecreated' => 'privacy:metadata:local_orgprofile_value:timecreated',
            'timemodified' => 'privacy:metadata:local_orgprofile_value:timemodified',
        ], 'privacy:metadata:local_orgprofile_value');
        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();
        if ($DB->record_exists('local_orgprofile_user', ['userid' => $userid]) ||
                $DB->record_exists('local_orgprofile_value', ['userid' => $userid])) {
            $contextlist->add_user_context($userid);
        }
        return $contextlist;
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        if (!$contextlist->count()) {
            return;
        }
        $user = $contextlist->get_user();
        $context = context_user::instance($user->id);
        if (!in_array($context->id, $contextlist->get_contextids())) {
            return;
        }
        $assignments = $DB->get_records('local_orgprofile_user', ['userid' => $user->id], 'companyid ASC');
        foreach ($assignments as $assignment) {
            $company = $DB->get_record('local_iomad_companies', ['id' => $assignment->companyid], 'id,name');
            $usertype = $DB->get_record('local_orgprofile_usertype', ['id' => $assignment->usertypeid], 'id,name');
            if (!$company || !$usertype) {
                continue;
            }
            $form = $assignment->formid
                ? $DB->get_record('local_orgprofile_form', ['id' => $assignment->formid], 'id,name') : null;
            $data = (object) [
                'company' => format_string($company->name),
                'user_type' => format_string($usertype->name),
                'assigned_form' => $form ? format_string($form->name) : null,
                'status' => $assignment->status,
                'timecreated' => transform::datetime($assignment->timecreated),
                'timemodified' => transform::datetime($assignment->timemodified),
                'values' => [],
            ];
            $values = $DB->get_records('local_orgprofile_value', [
                'userid' => $user->id,
                'companyid' => $company->id,
            ], 'fieldid ASC');
            foreach ($values as $value) {
                $field = $DB->get_record('local_orgprofile_field', ['id' => $value->fieldid], 'id,name,shortname,sensitive');
                if ($field) {
                    $data->values[] = (object) [
                        'field' => format_string($field->name),
                        'shortname' => $field->shortname,
                        'sensitive' => (bool) $field->sensitive,
                        'value' => $value->value,
                        'timemodified' => transform::datetime($value->timemodified),
                    ];
                }
            }
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_orgprofile'), clean_filename($company->name)],
                $data
            );
        }
    }

    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;
        if (!$context instanceof context_user) {
            return;
        }
        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('local_orgprofile_value', ['userid' => $context->instanceid]);
        $DB->delete_records('local_orgprofile_user', ['userid' => $context->instanceid]);
        $transaction->allow_commit();
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        if (!$contextlist->count()) {
            return;
        }
        $usercontext = context_user::instance($contextlist->get_user()->id);
        if (in_array($usercontext->id, $contextlist->get_contextids())) {
            self::delete_data_for_all_users_in_context($usercontext);
        }
    }

    public static function get_users_in_context(userlist $userlist): void {
        global $DB;
        $context = $userlist->get_context();
        if (!$context instanceof context_user) {
            return;
        }
        if ($DB->record_exists('local_orgprofile_user', ['userid' => $context->instanceid]) ||
                $DB->record_exists('local_orgprofile_value', ['userid' => $context->instanceid])) {
            $userlist->add_user($context->instanceid);
        }
    }

    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        $context = $userlist->get_context();
        if (!$context instanceof context_user || !in_array($context->instanceid, $userlist->get_userids())) {
            return;
        }
        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('local_orgprofile_value', ['userid' => $context->instanceid]);
        $DB->delete_records('local_orgprofile_user', ['userid' => $context->instanceid]);
        $transaction->allow_commit();
    }
}
