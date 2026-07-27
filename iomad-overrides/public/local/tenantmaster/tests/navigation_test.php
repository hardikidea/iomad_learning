<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster;

defined('MOODLE_INTERNAL') || die();

/**
 * Administrator navigation contracts.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class navigation_test extends \advanced_testcase {
    /**
     * Tenant operations and the system catalogue are distinct Admin Tools tiles.
     */
    public function test_iomad_admin_tools_menu_has_distinct_tiles(): void {
        global $CFG;

        require_once($CFG->dirroot . '/local/tenantmaster/db/iomadmenu.php');
        $menu = local_tenantmaster_menu();

        $this->assertArrayHasKey('tenantmaster', $menu);
        $this->assertArrayHasKey('tenantmastercatalogue', $menu);
        $this->assertSame('/local/tenantmaster/index.php', $menu['tenantmaster']['url']);
        $this->assertSame('local/tenantmaster:view', $menu['tenantmaster']['cap']);
        $this->assertSame(
            '/local/tenantmaster/index.php?section=catalogue',
            $menu['tenantmastercatalogue']['url'],
        );
        $this->assertSame(
            'local/tenantmaster:managecatalogue',
            $menu['tenantmastercatalogue']['cap'],
        );
        $this->assertSame('fa-building-columns', $menu['tenantmaster']['icon']);
        $this->assertSame('fa-layer-group', $menu['tenantmastercatalogue']['icon']);
        $this->assertSame('fa-graduation-cap', $menu['tenantmastercourseeditor']['icon']);
        $this->assertSame(1, $menu['tenantmaster']['tab']);
        $this->assertSame(1, $menu['tenantmastercatalogue']['tab']);
    }
}
