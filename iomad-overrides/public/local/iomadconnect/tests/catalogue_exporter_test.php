<?php
// This file is part of Moodle - http://moodle.org/

namespace local_iomadconnect;

use local_iomad\company;
use local_iomadconnect\local\catalogue_exporter;

/**
 * Tenant catalogue isolation and cursor tests.
 *
 * @package    local_iomadconnect
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_iomadconnect\local\catalogue_exporter
 */
final class catalogue_exporter_test extends \advanced_testcase {
    /**
     * Export includes ancestors and excludes another company's course.
     */
    public function test_export_is_company_scoped_and_cursor_is_replay_safe(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $companya = $this->company('Catalogue A', 'catalogue_a');
        $companyb = $this->company('Catalogue B', 'catalogue_b');
        $parent = $this->getDataGenerator()->create_category([
            'name' => 'Parent',
            'idnumber' => 'CAT-PARENT',
        ]);
        $child = $this->getDataGenerator()->create_category([
            'name' => 'Child',
            'idnumber' => 'CAT-CHILD',
            'parent' => $parent->id,
        ]);
        $coursea = $this->getDataGenerator()->create_course([
            'shortname' => 'CATALOGUE-A',
            'idnumber' => 'CATALOGUE-A',
            'category' => $child->id,
        ]);
        $courseb = $this->getDataGenerator()->create_course([
            'shortname' => 'CATALOGUE-B',
            'idnumber' => 'CATALOGUE-B',
        ]);
        $companya->add_course($coursea);
        $companyb->add_course($courseb);

        $exporter = new catalogue_exporter();
        $page = $exporter->export($companya->id, '', 500);
        $ids = array_column($page['events'], 'entityid');

        $this->assertContains('CAT-PARENT', $ids);
        $this->assertContains('CAT-CHILD', $ids);
        $this->assertContains('CATALOGUE-A', $ids);
        $this->assertNotContains('CATALOGUE-B', $ids);
        $this->assertFalse($page['hasmore']);
        $this->assertNotSame('', $page['cursor']);
        $this->assertSame([], $exporter->export($companya->id, $page['cursor'], 500)['events']);
    }

    /**
     * Invalid cursors fail closed.
     */
    public function test_invalid_cursor_is_rejected(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $company = $this->company('Catalogue Cursor', 'catalogue_cursor');

        $this->expectException(\invalid_parameter_exception::class);
        (new catalogue_exporter())->export($company->id, 'not-a-cursor', 100);
    }

    /**
     * Create a company.
     *
     * @param string $name Name.
     * @param string $shortname Shortname.
     * @return company
     */
    private function company(string $name, string $shortname): company {
        return company::create_company((object)[
            'name' => $name,
            'shortname' => $shortname,
            'city' => 'Pune',
            'country' => 'IN',
        ]);
    }
}
