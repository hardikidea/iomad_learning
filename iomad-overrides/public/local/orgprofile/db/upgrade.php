<?php
// This file is part of Moodle - https://moodle.org/

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade local_orgprofile.
 *
 * The initial production schema is installed from install.xml. Future schema
 * changes must add an idempotent XMLDB step here before increasing version.php.
 *
 * @param int $oldversion Previously installed version.
 * @return bool
 */
function xmldb_local_orgprofile_upgrade(int $oldversion): bool {
    return true;
}
