<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Tenant Master upgrade steps.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Apply Tenant Master upgrades.
 *
 * @param int $oldversion Installed version.
 * @return bool
 */
function xmldb_local_tenantmaster_upgrade(int $oldversion): bool {
    global $DB;

    if ($oldversion < 2026072501) {
        upgrade_plugin_savepoint(true, 2026072501, 'local', 'tenantmaster');
    }
    if ($oldversion < 2026072502) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_tenantmaster_job');
        $tenantstatusindex = new xmldb_index('tenant_status', XMLDB_INDEX_NOTUNIQUE, ['tenantid', 'status']);
        $modulestatusindex = new xmldb_index('module_status', XMLDB_INDEX_NOTUNIQUE, ['module', 'status']);
        if ($dbman->index_exists($table, $tenantstatusindex)) {
            $dbman->drop_index($table, $tenantstatusindex);
        }
        if ($dbman->index_exists($table, $modulestatusindex)) {
            $dbman->drop_index($table, $modulestatusindex);
        }
        $field = new xmldb_field(
            'status',
            XMLDB_TYPE_CHAR,
            '30',
            null,
            XMLDB_NOTNULL,
            null,
            'queued',
            'mode',
        );
        $dbman->change_field_precision($table, $field);
        $dbman->add_index($table, $tenantstatusindex);
        $dbman->add_index($table, $modulestatusindex);
        upgrade_plugin_savepoint(true, 2026072502, 'local', 'tenantmaster');
    }
    if ($oldversion < 2026072503) {
        upgrade_plugin_savepoint(true, 2026072503, 'local', 'tenantmaster');
    }
    if ($oldversion < 2026072505) {
        global $CFG;

        require_once($CFG->dirroot . '/lib/accesslib.php');
        $roleservice = new \local_tenantmaster\local\role_service();
        $itroleid = $roleservice->ensure_it_coordinator_role();

        $tenants = $DB->get_records('local_tenantmaster_tenant');
        foreach ($tenants as $tenant) {
            $roleservice->ensure_defaults((int)$tenant->id);
            $itmapping = $DB->get_record('local_tenantmaster_rolemap', [
                'tenantid' => $tenant->id,
                'rolekey' => 'it_coordinator',
            ]);
            if ($itmapping) {
                $itmapping->roleid = $itroleid;
                $itmapping->managertype = 0;
                $itmapping->scope = 'company';
                $itmapping->capabilityjson = \local_tenantmaster\local\json::encode(array_map(
                    'intval',
                    $DB->get_records_menu(
                        'role_capabilities',
                        ['roleid' => $itroleid],
                        'capability',
                        'capability, permission'
                    )
                ));
                $itmapping->active = 1;
                $itmapping->timemodified = time();
                $DB->update_record('local_tenantmaster_rolemap', $itmapping);
            }
        }

        $reporters = $DB->get_records_sql(
            "SELECT ra.id, ra.userid, ctx.instanceid AS companyid,
                    cu.departmentid, cu.educator
               FROM {role_assignments} ra
               JOIN {role} r ON r.id = ra.roleid AND r.shortname = :roleshortname
               JOIN {context} ctx ON ctx.id = ra.contextid
               JOIN {local_iomad_company_users} cu
                 ON cu.userid = ra.userid
                AND cu.companyid = ctx.instanceid
              WHERE ctx.contextlevel = :contextlevel",
            [
                'roleshortname' => 'companyreporter',
                'contextlevel' => CONTEXT_COMPANY,
            ]
        );
        foreach ($reporters as $reporter) {
            \local_iomad\company::upsert_company_user(
                (int)$reporter->userid,
                (int)$reporter->companyid,
                (int)$reporter->departmentid,
                4,
                !empty($reporter->educator),
                false,
                true
            );
        }

        $itcoordinators = $DB->get_records_sql(
            "SELECT ra.id, ra.userid, ctx.instanceid AS companyid,
                    cu.departmentid, cu.educator
               FROM {role_assignments} ra
               JOIN {role} r ON r.id = ra.roleid AND r.shortname = :roleshortname
               JOIN {context} ctx ON ctx.id = ra.contextid
               JOIN {local_iomad_company_users} cu
                 ON cu.userid = ra.userid
                AND cu.companyid = ctx.instanceid
              WHERE ctx.contextlevel = :contextlevel",
            [
                'roleshortname' => 'institutionitcoordinator',
                'contextlevel' => CONTEXT_COMPANY,
            ]
        );
        foreach ($itcoordinators as $itcoordinator) {
            \local_iomad\company::upsert_company_user(
                (int)$itcoordinator->userid,
                (int)$itcoordinator->companyid,
                (int)$itcoordinator->departmentid,
                0,
                !empty($itcoordinator->educator),
                false,
                true
            );
            role_assign(
                $itroleid,
                (int)$itcoordinator->userid,
                \local_iomad\custom_context\context_company::instance(
                    (int)$itcoordinator->companyid
                )->id
            );
        }

        $legacyguardianid = (int)$DB->get_field('role', 'id', ['shortname' => 'parentguardian']);
        $guardianid = (int)$DB->get_field('role', 'id', ['shortname' => 'tenantguardian']);
        if ($legacyguardianid > 0 && $guardianid > 0 && $legacyguardianid !== $guardianid) {
            $assignments = $DB->get_records('role_assignments', ['roleid' => $legacyguardianid]);
            foreach ($assignments as $assignment) {
                role_assign(
                    $guardianid,
                    (int)$assignment->userid,
                    (int)$assignment->contextid,
                    (string)$assignment->component,
                    (int)$assignment->itemid,
                    (int)$assignment->timemodified
                );
                role_unassign(
                    $legacyguardianid,
                    (int)$assignment->userid,
                    (int)$assignment->contextid,
                    (string)$assignment->component,
                    (int)$assignment->itemid
                );
            }
        }

        upgrade_plugin_savepoint(true, 2026072505, 'local', 'tenantmaster');
    }
    if ($oldversion < 2026072506) {
        upgrade_plugin_savepoint(true, 2026072506, 'local', 'tenantmaster');
    }
    if ($oldversion < 2026072507) {
        upgrade_plugin_savepoint(true, 2026072507, 'local', 'tenantmaster');
    }
    return true;
}
