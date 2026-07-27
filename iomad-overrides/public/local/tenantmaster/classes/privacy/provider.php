<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\privacy;

use context_system;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for Tenant Master operator metadata.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe stored operator metadata.
     *
     * @param collection $collection Collection.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_tenantmaster_tenant', [
            'createdby' => 'privacy:metadata:tenant:createdby',
            'modifiedby' => 'privacy:metadata:tenant:modifiedby',
        ], 'privacy:metadata:tenant');
        $collection->add_database_table('local_tenantmaster_audit', [
            'actorid' => 'privacy:metadata:audit:actorid',
        ], 'privacy:metadata:audit');
        $collection->add_database_table('local_tenantmaster_job', [
            'actorid' => 'privacy:metadata:job:actorid',
        ], 'privacy:metadata:job');
        $collection->add_database_table('local_tenantmaster_batch', [
            'actorid' => 'privacy:metadata:batch:actorid',
        ], 'privacy:metadata:batch');
        $collection->add_database_table('local_tenantmaster_rollover', [
            'actorid' => 'privacy:metadata:rollover:actorid',
            'approvedby' => 'privacy:metadata:rollover:approvedby',
        ], 'privacy:metadata:rollover');
        $collection->add_database_table('local_tenantmaster_drift', [
            'resolvedby' => 'privacy:metadata:drift:resolvedby',
        ], 'privacy:metadata:drift');
        $collection->add_database_table('local_tenantmaster_batchrow', [
            'payloadjson' => 'privacy:metadata:batchrow:payloadjson',
        ], 'privacy:metadata:batchrow');
        $collection->add_database_table('local_tenantmaster_placement', [
            'userid' => 'privacy:metadata:placement:userid',
            'createdby' => 'privacy:metadata:placement:createdby',
            'modifiedby' => 'privacy:metadata:placement:modifiedby',
        ], 'privacy:metadata:placement');
        $collection->add_database_table('local_tenantmaster_progress', [
            'createdby' => 'privacy:metadata:progress:createdby',
            'approvedby' => 'privacy:metadata:progress:approvedby',
        ], 'privacy:metadata:progress');
        $collection->add_database_table('local_tenantmaster_crscopy', [
            'createdby' => 'privacy:metadata:coursecopy:createdby',
        ], 'privacy:metadata:coursecopy');
        $collection->add_database_table('local_tenantmaster_catitem', [
            'createdby' => 'privacy:metadata:catalogue:createdby',
            'modifiedby' => 'privacy:metadata:catalogue:modifiedby',
        ], 'privacy:metadata:catalogue');
        return $collection;
    }

    /**
     * Return system context when operator metadata exists.
     *
     * @param int $userid User.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;

        $contextlist = new contextlist();
        $exists = $DB->record_exists_select(
            'local_tenantmaster_tenant',
            'createdby = :created OR modifiedby = :modified',
            ['created' => $userid, 'modified' => $userid],
        ) || $DB->record_exists('local_tenantmaster_audit', ['actorid' => $userid])
            || $DB->record_exists('local_tenantmaster_job', ['actorid' => $userid])
            || $DB->record_exists('local_tenantmaster_batch', ['actorid' => $userid])
            || $DB->record_exists('local_tenantmaster_rollover', ['actorid' => $userid])
            || $DB->record_exists('local_tenantmaster_rollover', ['approvedby' => $userid])
            || $DB->record_exists('local_tenantmaster_drift', ['resolvedby' => $userid])
            || $DB->record_exists('local_tenantmaster_placement', ['userid' => $userid])
            || $DB->record_exists('local_tenantmaster_placement', ['createdby' => $userid])
            || $DB->record_exists('local_tenantmaster_placement', ['modifiedby' => $userid])
            || $DB->record_exists('local_tenantmaster_progress', ['createdby' => $userid])
            || $DB->record_exists('local_tenantmaster_progress', ['approvedby' => $userid])
            || $DB->record_exists('local_tenantmaster_crscopy', ['createdby' => $userid])
            || $DB->record_exists('local_tenantmaster_catitem', ['createdby' => $userid])
            || $DB->record_exists('local_tenantmaster_catitem', ['modifiedby' => $userid]);
        if ($exists) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    /**
     * Export operator audit references without package payloads or native PII.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        if (!in_array(context_system::instance()->id, $contextlist->get_contextids(), true)) {
            return;
        }
        $userid = $contextlist->get_user()->id;
        $audits = $DB->get_records('local_tenantmaster_audit', ['actorid' => $userid], 'timecreated ASC');
        $jobs = $DB->get_records('local_tenantmaster_job', ['actorid' => $userid], 'timecreated ASC');
        $placements = $DB->get_records('local_tenantmaster_placement', ['userid' => $userid], 'timecreated ASC');
        writer::with_context(context_system::instance())->export_data(
            [get_string('pluginname', 'local_tenantmaster')],
            (object)[
                'audit' => array_map(static fn(object $record): object => (object)[
                    'tenantid' => $record->tenantid,
                    'action' => $record->action,
                    'result' => $record->result,
                    'timecreated' => $record->timecreated,
                ], array_values($audits)),
                'jobs' => array_map(static fn(object $record): object => (object)[
                    'tenantid' => $record->tenantid,
                    'module' => $record->module,
                    'status' => $record->status,
                    'timecreated' => $record->timecreated,
                ], array_values($jobs)),
                'placements' => array_map(static fn(object $record): object => (object)[
                    'tenantid' => $record->tenantid,
                    'academicyearid' => $record->acadyearid,
                    'status' => $record->status,
                    'timecreated' => $record->timecreated,
                ], array_values($placements)),
            ],
        );
    }

    /**
     * Tenant Master is operational evidence and is retained under policy.
     *
     * @param \context $context Context.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        // Operational audit is not deleted through a broad context request.
    }

    /**
     * Anonymise operator references while retaining operational evidence.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        if (!in_array(context_system::instance()->id, $contextlist->get_contextids(), true)) {
            return;
        }
        $userid = $contextlist->get_user()->id;
        foreach (
            [
            'local_tenantmaster_tenant' => ['createdby', 'modifiedby'],
            'local_tenantmaster_audit' => ['actorid'],
            'local_tenantmaster_job' => ['actorid'],
            'local_tenantmaster_batch' => ['actorid'],
            'local_tenantmaster_rollover' => ['actorid', 'approvedby'],
            'local_tenantmaster_drift' => ['resolvedby'],
            'local_tenantmaster_placement' => ['createdby', 'modifiedby'],
            'local_tenantmaster_progress' => ['createdby', 'approvedby'],
            'local_tenantmaster_crscopy' => ['createdby'],
            'local_tenantmaster_catitem' => ['createdby', 'modifiedby'],
            ] as $table => $fields
        ) {
            foreach ($fields as $field) {
                $DB->set_field($table, $field, 0, [$field => $userid]);
            }
        }
    }
}
