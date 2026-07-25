<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_tenantform\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\writer;

/**
 * Privacy provider for tenant form entries and audit events.
 *
 * @package    mod_tenantform
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe stored personal data.
     *
     * @param collection $collection Collection.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('tenantform_entry', [
            'companyid' => 'privacy:metadata:entry:companyid',
            'userid' => 'privacy:metadata:entry:userid',
            'status' => 'privacy:metadata:entry:status',
            'datajson' => 'privacy:metadata:entry:datajson',
            'timecreated' => 'privacy:metadata:entry:timecreated',
        ], 'privacy:metadata:entry');
        $collection->add_database_table('tenantform_audit', [
            'entryid' => 'privacy:metadata:audit:entryid',
            'companyid' => 'privacy:metadata:audit:companyid',
            'userid' => 'privacy:metadata:audit:userid',
            'action' => 'privacy:metadata:audit:action',
            'timecreated' => 'privacy:metadata:audit:timecreated',
        ], 'privacy:metadata:audit');
        $collection->link_subsystem('core_files', 'privacy:metadata:core_files');
        return $collection;
    }

    /**
     * Return module contexts where a user submitted or acted on entries.
     *
     * @param int $userid User.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT DISTINCT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid
                  JOIN {modules} m ON m.id = cm.module AND m.name = :module
                  JOIN {tenantform} tf ON tf.id = cm.instance
             LEFT JOIN {tenantform_entry} te ON te.tenantformid = tf.id
             LEFT JOIN {tenantform_audit} ta ON ta.tenantformid = tf.id
                 WHERE ctx.contextlevel = :contextlevel
                   AND (te.userid = :entryuserid OR ta.userid = :audituserid)";
        $contextlist->add_from_sql($sql, [
            'module' => 'tenantform',
            'contextlevel' => CONTEXT_MODULE,
            'entryuserid' => $userid,
            'audituserid' => $userid,
        ]);
        return $contextlist;
    }

    /**
     * Export a user's submitted data and audit events.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_MODULE) {
                continue;
            }
            $cm = get_coursemodule_from_id('tenantform', $context->instanceid);
            if (!$cm) {
                continue;
            }
            $form = $DB->get_record('tenantform', ['id' => $cm->instance], '*', MUST_EXIST);
            $entries = $DB->get_records('tenantform_entry', [
                'tenantformid' => $form->id,
                'userid' => $userid,
            ]);
            foreach ($entries as $entry) {
                writer::with_context($context)->export_data(
                    [format_string($form->name), get_string('entrytitle', 'mod_tenantform', $entry->id)],
                    (object)[
                        'status' => $entry->status,
                        'values' => json_decode($entry->datajson, false, 64, JSON_THROW_ON_ERROR),
                        'submitted' => transform::datetime($entry->timecreated),
                    ],
                );
                writer::with_context($context)->export_area_files(
                    [format_string($form->name), get_string('entrytitle', 'mod_tenantform', $entry->id)],
                    'mod_tenantform',
                    'entry',
                    $entry->id,
                );
            }
            $audits = $DB->get_records('tenantform_audit', [
                'tenantformid' => $form->id,
                'userid' => $userid,
            ]);
            if ($audits) {
                $data = [];
                foreach ($audits as $audit) {
                    $data[] = (object)[
                        'entryid' => $audit->entryid,
                        'action' => $audit->action,
                        'created' => transform::datetime($audit->timecreated),
                    ];
                }
                writer::with_context($context)->export_data(
                    [format_string($form->name), get_string('audittrail', 'mod_tenantform')],
                    (object)['events' => $data],
                );
            }
        }
    }

    /**
     * Delete all entry data in one module context.
     *
     * @param \context $context Context.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if ($context->contextlevel !== CONTEXT_MODULE) {
            return;
        }
        $cm = get_coursemodule_from_id('tenantform', $context->instanceid);
        if (!$cm) {
            return;
        }
        get_file_storage()->delete_area_files($context->id, 'mod_tenantform', 'entry');
        $DB->delete_records('tenantform_audit', ['tenantformid' => $cm->instance]);
        $DB->delete_records('tenantform_entry', ['tenantformid' => $cm->instance]);
    }

    /**
     * Delete an approved user's submissions and actor audit events.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_MODULE) {
                continue;
            }
            $cm = get_coursemodule_from_id('tenantform', $context->instanceid);
            if (!$cm) {
                continue;
            }
            $entries = $DB->get_records('tenantform_entry', [
                'tenantformid' => $cm->instance,
                'userid' => $userid,
            ], '', 'id');
            foreach ($entries as $entry) {
                get_file_storage()->delete_area_files(
                    $context->id,
                    'mod_tenantform',
                    'entry',
                    $entry->id,
                );
                $DB->delete_records('tenantform_audit', ['entryid' => $entry->id]);
                $DB->delete_records('tenantform_entry', ['id' => $entry->id]);
            }
            $DB->delete_records('tenantform_audit', [
                'tenantformid' => $cm->instance,
                'userid' => $userid,
            ]);
        }
    }
}
