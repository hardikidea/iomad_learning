<?php
// This file is part of Moodle - http://moodle.org/

namespace theme_iomad_learning;

use theme_iomad_learning\local\icon_catalog;
use theme_iomad_learning\output\icon_system_fontawesome;

/**
 * Product icon-system contract tests.
 *
 * @package    theme_iomad_learning
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \theme_iomad_learning\local\icon_catalog
 * @covers     \theme_iomad_learning\output\icon_system_fontawesome
 */
final class icon_catalog_test extends \advanced_testcase {
    /**
     * Every project component has explicit icon and monologo mappings.
     */
    public function test_project_components_have_reviewed_mappings(): void {
        $map = icon_catalog::fontawesome_map();

        foreach (icon_catalog::project_components() as $component => $icon) {
            $this->assertSame($icon, $map[$component . ':icon']);
            $this->assertSame($icon, $map[$component . ':monologo']);
        }

        $this->assertSame('fa-building-columns', $map['local_tenantmaster:icon']);
        $this->assertSame('fa-certificate', $map['mod_iomadcertificate:monologo']);
        $this->assertSame('fa-key', $map['enrol_license:withkey']);
        $this->assertSame('fa-unlock-keyhole', $map['enrol_license:withoutkey']);
    }

    /**
     * Installed plugins receive deterministic fallback mappings.
     */
    public function test_installed_plugins_have_icon_fallbacks(): void {
        $map = icon_catalog::fontawesome_map();

        foreach (array_keys(\core_component::get_plugin_types()) as $type) {
            foreach (array_keys(\core_component::get_plugin_list($type)) as $name) {
                $component = \core_component::normalize_componentname($type . '_' . $name);
                $this->assertArrayHasKey($component . ':icon', $map);
                $this->assertArrayHasKey($component . ':monologo', $map);
            }
        }
    }

    /**
     * The active icon system preserves core mappings and adds product mappings.
     */
    public function test_theme_icon_system_extends_fontawesome(): void {
        \core\output\icon_system::reset_caches();
        $system = \core\output\icon_system::instance(icon_system_fontawesome::class);
        $map = $system->get_core_icon_map();

        $this->assertInstanceOf(\core\output\icon_system_fontawesome::class, $system);
        $this->assertSame('fa-gear', $map['core:a/setting']);
        $this->assertSame('fa-file-pen', $map['mod_assign:monologo']);
        $this->assertSame('fa-chart-line', $map['local_tenantanalytics:icon']);
    }

    /**
     * Custom domain icons are versioned theme assets.
     */
    public function test_custom_icon_asset_exists(): void {
        global $CFG;

        $path = $CFG->dirroot . '/theme/iomad_learning/pix/icons/institution-hierarchy.svg';
        $this->assertFileExists($path);
        $svg = file_get_contents($path);
        $this->assertStringContainsString('viewBox="0 0 512 512"', $svg);
        $this->assertStringNotContainsString('<script', strtolower($svg));
    }

    /**
     * Custom SVG and browser maps remain aligned with server mappings.
     */
    public function test_custom_and_client_maps_are_consistent(): void {
        $this->assertSame(
            'iomad-learning-icon-custom iomad-learning-icon-institution',
            icon_catalog::custom_icon_classes('local_tenantmaster', 'icon'),
        );
        $this->assertSame(
            'iomad-learning-icon-custom iomad-learning-icon-institution',
            icon_catalog::client_component_map()['local_tenantmaster'],
        );
        $this->assertSame('fa-comments', icon_catalog::client_component_map()['mod_forum']);
        $this->assertNull(icon_catalog::custom_icon_classes('mod_forum', 'icon'));
    }
}
