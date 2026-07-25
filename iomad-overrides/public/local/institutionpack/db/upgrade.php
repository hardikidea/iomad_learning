<?php
// This file is part of IOMAD - http://www.iomad.org/

/**
 * Institution pack upgrade steps.
 *
 * @package    local_institutionpack
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Apply Institution Pack upgrades.
 *
 * @param int $oldversion Installed version.
 * @return bool
 */
function xmldb_local_institutionpack_upgrade(int $oldversion): bool {
    if ($oldversion < 2026072503) {
        upgrade_plugin_savepoint(true, 2026072503, 'local', 'institutionpack');
    }
    if ($oldversion < 2026072504) {
        upgrade_plugin_savepoint(true, 2026072504, 'local', 'institutionpack');
    }
    return true;
}
