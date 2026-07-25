<?php
// This file is part of Moodle - http://moodle.org/

namespace local_global_events;

use local_global_events\local\dashboard_service;
use local_global_events\local\gamification_service;
use local_global_events\local\tenant_scope;
use local_iomad\company;

/**
 * Role-safe dashboard projection tests.
 *
 * @package local_global_events
 * @covers \local_global_events\local\dashboard_service
 */
final class dashboard_service_test extends \advanced_testcase {
    /**
     * Parent reports contain own and child aggregates but no unrelated company.
     */
    public function test_parent_projection_excludes_unrelated_company(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $parent = $this->company('Parent Institution', 'dashboard_parent');
        $child = $this->company('Child Faculty', 'dashboard_child', $parent->id);
        $other = $this->company('Other Institution', 'dashboard_other');
        $parentuser = $this->getDataGenerator()->create_user();
        $childuser = $this->getDataGenerator()->create_user();
        $otheruser = $this->getDataGenerator()->create_user();
        $parent->assign_user_to_company($parentuser->id);
        $child->assign_user_to_company($childuser->id);
        $other->assign_user_to_company($otheruser->id);
        $gamification = new gamification_service();
        $gamification->award(
            tenant_scope::system($parent->id),
            $parentuser->id,
            10,
            'local_global_events',
            'dashboard_parent',
            'dashboard-parent-award',
        );
        $gamification->award(
            tenant_scope::system($child->id),
            $childuser->id,
            20,
            'local_global_events',
            'dashboard_child',
            'dashboard-child-award',
        );
        $gamification->award(
            tenant_scope::system($other->id),
            $otheruser->id,
            30,
            'local_global_events',
            'dashboard_other',
            'dashboard-other-award',
        );

        $result = (new dashboard_service())->manager(
            tenant_scope::system($parent->id),
            true,
        );
        $companyids = array_column($result['companies'], 'companyid');

        $this->assertEqualsCanonicalizing([$parent->id, $child->id], $companyids);
        $this->assertNotContains($other->id, $companyids);
        $this->assertSame(30, array_sum(array_column($result['companies'], 'points')));
    }

    /**
     * Create an institution through IOMAD's public API.
     *
     * @param string $name Name.
     * @param string $shortname Shortname.
     * @param int $parentid Parent company.
     * @return company
     */
    private function company(string $name, string $shortname, int $parentid = 0): company {
        return company::create_company((object)[
            'name' => $name,
            'shortname' => $shortname,
            'city' => 'Pune',
            'country' => 'IN',
            'parentid' => $parentid,
        ]);
    }
}
