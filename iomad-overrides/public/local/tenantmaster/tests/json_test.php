<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster;

use local_tenantmaster\local\json;

/**
 * Deterministic JSON tests.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class json_test extends \advanced_testcase {
    /**
     * Object key order does not alter hashes.
     *
     * @covers \local_tenantmaster\local\json
     */
    public function test_hash_is_deterministic(): void {
        $this->assertSame(
            json::hash(['b' => 2, 'a' => ['d' => 4, 'c' => 3]]),
            json::hash(['a' => ['c' => 3, 'd' => 4], 'b' => 2]),
        );
    }

    /**
     * Lists retain order.
     *
     * @covers \local_tenantmaster\local\json
     */
    public function test_list_order_is_significant(): void {
        $this->assertNotSame(json::hash([1, 2]), json::hash([2, 1]));
    }
}
