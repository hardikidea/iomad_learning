<?php
// This file is part of Moodle - http://moodle.org/

namespace theme_iomad_learning;

use theme_iomad_learning\local\icon_catalog;
use theme_iomad_learning\local\svg_icon_library;
use theme_iomad_learning\output\icon_system_svg;

/**
 * Product SVG icon-system contract tests.
 *
 * @package    theme_iomad_learning
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \theme_iomad_learning\local\icon_catalog
 * @covers     \theme_iomad_learning\local\svg_icon_library
 * @covers     \theme_iomad_learning\output\icon_system_svg
 */
final class icon_catalog_test extends \advanced_testcase {
    /**
     * Every project component has a reviewed sprite symbol.
     */
    public function test_project_components_have_reviewed_mappings(): void {
        foreach (icon_catalog::project_components() as $component => $icon) {
            $this->assertTrue(svg_icon_library::has($icon));
            $this->assertSame($icon, icon_catalog::resolve($component, 'icon'));
            $this->assertSame($icon, icon_catalog::resolve($component, 'monologo'));
        }

        $this->assertSame('institution', icon_catalog::resolve('local_tenantmaster', 'icon'));
        $this->assertSame('certificate', icon_catalog::resolve('mod_iomadcertificate', 'monologo'));
        $this->assertSame('key', icon_catalog::resolve('enrol_license', 'withkey'));
        $this->assertSame('lock', icon_catalog::resolve('enrol_license', 'withoutkey'));
    }

    /**
     * Installed plugins receive deterministic client-side fallbacks.
     */
    public function test_installed_plugins_have_icon_fallbacks(): void {
        $map = icon_catalog::client_component_map();

        foreach (array_keys(\core_component::get_plugin_types()) as $type) {
            foreach (array_keys(\core_component::get_plugin_list($type)) as $name) {
                $component = \core_component::normalize_componentname($type . '_' . $name);
                $this->assertArrayHasKey($component, $map);
                $this->assertTrue(svg_icon_library::has($map[$component]));
            }
        }
    }

    /**
     * Common core actions retain distinct semantic meanings.
     */
    public function test_core_actions_resolve_to_semantic_symbols(): void {
        $this->assertSame('settings', icon_catalog::resolve('core', 'a/setting'));
        $this->assertSame('edit', icon_catalog::resolve('moodle', 't/edit'));
        $this->assertSame('trash', icon_catalog::resolve(null, 't/delete'));
        $this->assertSame('download', icon_catalog::resolve('core', 'a/download_all'));
        $this->assertSame('required', icon_catalog::resolve('core', 'i/req'));
        $this->assertSame('required', icon_catalog::resolve('core', 'req'));
        $this->assertSame('chevronRight', icon_catalog::resolve('core', 't/collapsed_empty'));
        $this->assertSame('check', icon_catalog::resolve('core', 't/markasread'));
        $this->assertSame('help', icon_catalog::resolve('core', 't/life-ring'));
        $this->assertSame('spinner', icon_catalog::resolve('core', 'i/loading'));
        $this->assertSame('shield', icon_catalog::resolve('moodle', 'i/permissions'));
        $this->assertSame('restore', icon_catalog::resolve('moodle', 'i/restore'));
        $this->assertSame('folderPlus', icon_catalog::resolve('moodle', 'i/withsubcat'));
        $this->assertSame('group', icon_catalog::resolve('moodle', 't/cohort'));
        $this->assertSame('eyeOff', icon_catalog::resolve('core', 't/switch_minus'));
        $this->assertSame('eye', icon_catalog::resolve('core', 't/switch_plus'));
        $this->assertSame('dashboard', icon_catalog::resolve('theme', 'fp/view_icon_active'));
        $this->assertSame('smile', icon_catalog::resolve('core', 'i/emojicategorysmileysemotion'));
        $this->assertSame('message', icon_catalog::resolve('mod_forum', 'icon'));
    }

    /**
     * The theme activates its independent SVG icon system.
     */
    public function test_theme_uses_svg_icon_system(): void {
        \core\output\icon_system::reset_caches();
        $system = \core\output\icon_system::instance(icon_system_svg::class);

        $this->assertInstanceOf(icon_system_svg::class, $system);
        $this->assertSame('core/icon_system_standard', $system->get_amd_name());
    }

    /**
     * The sprite is local, inert, and complete.
     */
    public function test_sprite_contains_every_reviewed_symbol(): void {
        global $CFG;

        $path = $CFG->dirroot . '/theme/iomad_learning/pix/icons/lms-icons.svg';
        $this->assertFileExists($path);
        $svg = (string)file_get_contents($path);
        $this->assertStringNotContainsString('<script', strtolower($svg));
        $this->assertStringNotContainsString('href="http', strtolower($svg));
        foreach (svg_icon_library::names() as $name) {
            $this->assertStringContainsString('id="' . $name . '"', $svg);
        }
    }

    /**
     * Legacy IOMAD Font Awesome markup has a deterministic SVG migration path.
     */
    public function test_legacy_class_map_uses_only_sprite_symbols(): void {
        $map = icon_catalog::legacy_class_map();

        $this->assertSame('building', $map['fa-building']);
        $this->assertSame('institution', $map['fa-building-columns']);
        $this->assertSame('settings', $map['fa-gear']);
        $this->assertSame('spinner', $map['fa-spinner']);
        $this->assertSame('folderPlus', $map['fa-folder-plus']);
        $this->assertSame('externalLink', $map['fa-up-right-from-square']);
        $this->assertSame('group', $map['fa-users-gear']);
        $this->assertSame('book', $map['fa-book-open']);
        $this->assertSame('fileImport', $map['fa-file-csv']);
        foreach ($map as $icon) {
            $this->assertTrue(svg_icon_library::has($icon));
        }
    }
}
