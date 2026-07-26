<?php
// This file is part of Moodle - http://moodle.org/

/**
 * IOMAD Learning theme upgrade steps.
 *
 * @package    theme_iomad_learning
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Apply IOMAD Learning theme upgrades.
 *
 * @param int $oldversion Installed version.
 * @return bool
 */
function xmldb_theme_iomad_learning_upgrade(int $oldversion): bool {
    if ($oldversion < 2026072600) {
        $migrations = [
            'contentmaxwidth' => ['90rem', 'none'],
            'coursecontentmaxwidth' => ['80rem', 'none'],
            'pagepaddingx' => ['1.5rem', '1rem'],
            'pagepaddingy' => ['1.5rem', '0.75rem'],
        ];
        foreach ($migrations as $key => [$legacy, $fluid]) {
            $configured = get_config('theme_iomad_learning', $key);
            if ($configured === false || $configured === $legacy) {
                set_config($key, $fluid, 'theme_iomad_learning');
            }
        }
        upgrade_plugin_savepoint(true, 2026072600, 'theme', 'iomad_learning');
    }

    if ($oldversion < 2026072601) {
        \cache_helper::purge_by_definition('core', 'fontawesomeiconmapping');
        upgrade_plugin_savepoint(true, 2026072601, 'theme', 'iomad_learning');
    }

    return true;
}
