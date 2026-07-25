<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Tenant analytics database upgrades.
 *
 * @package    local_tenantanalytics
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Apply forward-only schema changes.
 *
 * @param int $oldversion Installed version.
 * @return bool
 */
function xmldb_local_tenantanalytics_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();
    if ($oldversion < 2026072501) {
        $table = new xmldb_table('local_tanalytics_schedule');
        $field = new xmldb_field(
            'locktoken',
            XMLDB_TYPE_CHAR,
            '64',
            null,
            XMLDB_NOTNULL,
            null,
            null,
            'lockeduntil'
        );
        $index = new xmldb_index('locktoken', XMLDB_INDEX_NOTUNIQUE, ['locktoken']);
        if ($dbman->index_exists($table, $index)) {
            $dbman->drop_index($table, $index);
        }
        $dbman->change_field_default($table, $field);
        $dbman->add_index($table, $index);

        $table = new xmldb_table('local_tanalytics_run');
        $field = new xmldb_field(
            'checksum',
            XMLDB_TYPE_CHAR,
            '64',
            null,
            XMLDB_NOTNULL,
            null,
            null,
            'rowcount'
        );
        $dbman->change_field_default($table, $field);

        upgrade_plugin_savepoint(true, 2026072501, 'local', 'tenantanalytics');
    }
    return true;
}
