<?php
// This file is part of Moodle - http://moodle.org/

namespace block_iomaddashboard;

use block_iomaddashboard\local\tenant_scope;
use local_iomad\company;

/**
 * Company-boundary tests for dashboard data.
 *
 * @package    block_iomaddashboard
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_iomaddashboard\local\tenant_scope
 */
final class tenant_scope_test extends \advanced_testcase {
    /**
     * A company scope includes its member and excludes another company's member.
     */
    public function test_scope_rejects_cross_company_user(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $companya = $this->company('Dashboard Company A', 'dashboard_a');
        $companyb = $this->company('Dashboard Company B', 'dashboard_b');
        $usera = $this->getDataGenerator()->create_user();
        $userb = $this->getDataGenerator()->create_user();
        company::upsert_company_user($usera->id, $companya->id, 0, 0);
        company::upsert_company_user($userb->id, $companyb->id, 0, 0);

        $scope = new tenant_scope($companya->id);
        $this->assertTrue($scope->contains_user($usera->id));
        $this->assertFalse($scope->contains_user($userb->id));
    }

    /**
     * Create an IOMAD company through the supported API.
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
