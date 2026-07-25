<?php
// This file is part of Moodle - http://moodle.org/

namespace block_iomaddashboard;

use block_iomaddashboard\local\widget_catalog;

/**
 * Dashboard catalogue tests.
 *
 * @package    block_iomaddashboard
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_iomaddashboard\local\widget_catalog
 */
final class widget_catalog_test extends \advanced_testcase {
    /**
     * The promised dashboard set is explicit and cannot dispatch arbitrary methods.
     */
    public function test_catalog_contains_ten_supported_widgets(): void {
        $catalog = widget_catalog::all();
        $this->assertCount(10, $catalog);
        $this->assertTrue(widget_catalog::exists('courseprogress'));
        $this->assertTrue(widget_catalog::exists('todo'));
        $this->assertFalse(widget_catalog::exists('../../invalid'));
    }
}
