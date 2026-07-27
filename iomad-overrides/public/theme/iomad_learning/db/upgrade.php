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

    if ($oldversion < 2026072602) {
        $migrations = [
            'navbarbackground' => ['#ffffff', '#172033'],
            'navbartext' => ['#1d2433', '#f6f8fb'],
            'navbarborder' => ['#d6dce6', '#2d3748'],
            'footerheight' => ['4rem', '8rem'],
        ];
        foreach ($migrations as $key => [$legacy, $replacement]) {
            $configured = get_config('theme_iomad_learning', $key);
            if ($configured === false || $configured === $legacy) {
                set_config($key, $replacement, 'theme_iomad_learning');
            }
        }
        \core\output\icon_system::reset_caches();
        theme_reset_all_caches();
        upgrade_plugin_savepoint(true, 2026072602, 'theme', 'iomad_learning');
    }

    if ($oldversion < 2026072603) {
        \core\output\icon_system::reset_caches();
        theme_reset_all_caches();
        upgrade_plugin_savepoint(true, 2026072603, 'theme', 'iomad_learning');
    }

    if ($oldversion < 2026072604) {
        $migrations = [
            'primarycolor' => ['#2454a6', '#8a3145'],
            'primaryhover' => ['#1b4389', '#6f2536'],
            'secondarycolor' => ['#0f7b6c', '#4f5b6b'],
            'secondaryhover' => ['#0a6257', '#3e4856'],
            'linkcolor' => ['#1f55a5', '#364152'],
            'linkhover' => ['#173f7c', '#1f2937'],
            'iconactive' => ['#2454a6', '#8a3145'],
            'navigationiconactive' => ['#1f55a5', '#8a3145'],
            'selectionbackground' => ['#dce8fa', '#f3e5e8'],
            'sidebaractive' => ['#5f9dea', '#dca8b5'],
            'footerlink' => ['#d7e7ff', '#f1d8de'],
        ];
        foreach ($migrations as $key => [$legacy, $replacement]) {
            $configured = get_config('theme_iomad_learning', $key);
            if ($configured === false || $configured === $legacy) {
                set_config($key, $replacement, 'theme_iomad_learning');
            }
        }
        \core\output\icon_system::reset_caches();
        theme_reset_all_caches();
        upgrade_plugin_savepoint(true, 2026072604, 'theme', 'iomad_learning');
    }

    if ($oldversion < 2026072605) {
        theme_reset_all_caches();
        upgrade_plugin_savepoint(true, 2026072605, 'theme', 'iomad_learning');
    }

    return true;
}
