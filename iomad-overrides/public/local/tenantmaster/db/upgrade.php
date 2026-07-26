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
    if ($oldversion < 2026072601) {
        $dbman = $DB->get_manager();

        $placement = new xmldb_table('local_tenantmaster_placement');
        $placement->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $placement->add_field('tenantid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $placement->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $placement->add_field('acadyearid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $placement->add_field('externalid', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL);
        $placement->add_field('boardid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $placement->add_field('mediumid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $placement->add_field('gradeid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $placement->add_field('streamid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $placement->add_field('divisionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $placement->add_field('cohortid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $placement->add_field('rollnumber', XMLDB_TYPE_CHAR, '50');
        $placement->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'active');
        $placement->add_field('startdate', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $placement->add_field('enddate', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $placement->add_field('payloadjson', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
        $placement->add_field('createdby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $placement->add_field('modifiedby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $placement->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $placement->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $placement->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $placement->add_key(
            'tenant_fk',
            XMLDB_KEY_FOREIGN,
            ['tenantid'],
            'local_tenantmaster_tenant',
            ['id'],
        );
        $placement->add_key(
            'year_fk',
            XMLDB_KEY_FOREIGN,
            ['acadyearid'],
            'local_tenantmaster_acadyear',
            ['id'],
        );
        $placement->add_index('tenant_external', XMLDB_INDEX_UNIQUE, ['tenantid', 'externalid']);
        $placement->add_index('tenant_user_year', XMLDB_INDEX_UNIQUE, ['tenantid', 'userid', 'acadyearid']);
        $placement->add_index('tenant_year_status', XMLDB_INDEX_NOTUNIQUE, ['tenantid', 'acadyearid', 'status']);
        $placement->add_index(
            'tenant_class',
            XMLDB_INDEX_NOTUNIQUE,
            ['tenantid', 'acadyearid', 'gradeid', 'divisionid'],
        );
        if (!$dbman->table_exists($placement)) {
            $dbman->create_table($placement);
        }

        $progress = new xmldb_table('local_tenantmaster_progress');
        $progress->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $progress->add_field('tenantid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $progress->add_field('sourceplaceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $progress->add_field('toyearid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $progress->add_field('decision', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL);
        $progress->add_field('targetgradeid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $progress->add_field('targetstreamid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $progress->add_field('targetdivisionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $progress->add_field('targetplaceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $progress->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'planned');
        $progress->add_field('reason', XMLDB_TYPE_TEXT);
        $progress->add_field('createdby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $progress->add_field('approvedby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $progress->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $progress->add_field('timeapproved', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $progress->add_field('timefinished', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $progress->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $progress->add_key(
            'tenant_fk',
            XMLDB_KEY_FOREIGN,
            ['tenantid'],
            'local_tenantmaster_tenant',
            ['id'],
        );
        $progress->add_key(
            'source_fk',
            XMLDB_KEY_FOREIGN,
            ['sourceplaceid'],
            'local_tenantmaster_placement',
            ['id'],
        );
        $progress->add_key(
            'year_fk',
            XMLDB_KEY_FOREIGN,
            ['toyearid'],
            'local_tenantmaster_acadyear',
            ['id'],
        );
        $progress->add_index('source_year', XMLDB_INDEX_UNIQUE, ['sourceplaceid', 'toyearid']);
        $progress->add_index('tenant_status', XMLDB_INDEX_NOTUNIQUE, ['tenantid', 'status']);
        if (!$dbman->table_exists($progress)) {
            $dbman->create_table($progress);
        }

        $coursecopy = new xmldb_table('local_tenantmaster_crscopy');
        $coursecopy->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $coursecopy->add_field('tenantid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $coursecopy->add_field('sourcecourseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $coursecopy->add_field('targetcourseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $coursecopy->add_field('sourcehash', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL);
        $coursecopy->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'planned');
        $coursecopy->add_field('message', XMLDB_TYPE_TEXT);
        $coursecopy->add_field('createdby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $coursecopy->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $coursecopy->add_field('timefinished', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $coursecopy->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $coursecopy->add_key(
            'tenant_fk',
            XMLDB_KEY_FOREIGN,
            ['tenantid'],
            'local_tenantmaster_tenant',
            ['id'],
        );
        $coursecopy->add_index('tenant_target', XMLDB_INDEX_UNIQUE, ['tenantid', 'targetcourseid']);
        $coursecopy->add_index('tenant_status', XMLDB_INDEX_NOTUNIQUE, ['tenantid', 'status']);
        if (!$dbman->table_exists($coursecopy)) {
            $dbman->create_table($coursecopy);
        }

        upgrade_plugin_savepoint(true, 2026072601, 'local', 'tenantmaster');
    }
    if ($oldversion < 2026072602) {
        $nativecompanyfields = [
            'name',
            'address',
            'city',
            'region',
            'postcode',
            'country',
            'hostname',
            'maincolor',
            'headingcolor',
            'linkcolor',
            'customcss',
        ];
        foreach ($DB->get_records('local_tenantmaster_tenant') as $tenant) {
            $profile = \local_tenantmaster\local\json::decode_object((string)$tenant->profilejson);
            foreach ($nativecompanyfields as $field) {
                unset($profile[$field]);
            }
            $tenant->profilejson = \local_tenantmaster\local\json::encode($profile);
            $tenant->sourcehash = \local_tenantmaster\local\json::hash([
                'trustcode' => $tenant->trustcode,
                'tenanttype' => $tenant->tenanttype,
                'profile' => $profile,
            ]);
            $tenant->timemodified = time();
            $DB->update_record('local_tenantmaster_tenant', $tenant);
        }
        $DB->set_field_select(
            'local_tenantmaster_dirty',
            'state',
            'synced',
            'module = :module AND entitytable = :entitytable',
            ['module' => 'tenant', 'entitytable' => 'local_tenantmaster_tenant'],
        );
        (new \local_tenantmaster\local\course_metadata_service())->ensure_definitions();
        upgrade_plugin_savepoint(true, 2026072602, 'local', 'tenantmaster');
    }
    return true;
}
