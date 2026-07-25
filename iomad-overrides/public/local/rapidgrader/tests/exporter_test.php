<?php
// This file is part of Moodle - http://moodle.org/

namespace local_rapidgrader;

use local_rapidgrader\local\exporter;

/**
 * Grade export hardening tests.
 *
 * @package    local_rapidgrader
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_rapidgrader\local\exporter
 */
final class exporter_test extends \advanced_testcase {
    /**
     * Spreadsheet formulas are escaped before any dataformat writer sees them.
     */
    public function test_escape_row_neutralizes_spreadsheet_formula(): void {
        $row = exporter::escape_row([
            'learner' => '=HYPERLINK("https://invalid.test")',
            'idnumber' => '+123',
            'grade' => 91.5,
        ], false);

        $this->assertSame("'=HYPERLINK(\"https://invalid.test\")", $row['learner']);
        $this->assertSame("'+123", $row['idnumber']);
        $this->assertSame('91.5', $row['grade']);
    }
}
