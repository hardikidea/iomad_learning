<?php
// This file is part of Moodle - http://moodle.org/

namespace local_global_events;

use local_global_events\local\gamification_service;
use local_global_events\local\ledger_repository;
use local_global_events\local\tenant_scope;
use local_iomad\company;

/**
 * Ledger idempotency and tenant isolation tests.
 *
 * @package local_global_events
 * @covers \local_global_events\local\gamification_service
 * @covers \local_global_events\local\ledger_repository
 * @covers \local_global_events\local\tenant_scope
 */
final class gamification_service_test extends \advanced_testcase {
    /**
     * Replay is idempotent and payload mutation is rejected.
     */
    public function test_award_is_idempotent_and_immutable(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        [$scope, $user, $course] = $this->fixture('ledger_a');
        $service = new gamification_service();

        $first = $service->award(
            $scope,
            $user->id,
            10,
            'local_global_events',
            'test.completed',
            'test-event-0001',
            $course->id,
            0,
            'xp',
            ['activitytype' => 'test'],
        );
        $replay = $service->award(
            $scope,
            $user->id,
            10,
            'local_global_events',
            'test.completed',
            'test-event-0001',
            $course->id,
            0,
            'xp',
            ['activitytype' => 'test'],
        );

        $this->assertTrue($first['awarded']);
        $this->assertFalse($replay['awarded']);
        $this->assertSame(10, $replay['total']);
        $this->assertSame(1, $DB->count_records('local_ge_ledger'));

        $this->expectException(\invalid_parameter_exception::class);
        $service->award(
            $scope,
            $user->id,
            20,
            'local_global_events',
            'test.completed',
            'test-event-0001',
            $course->id,
            0,
            'xp',
            ['activitytype' => 'test'],
        );
    }

    /**
     * A learner from another company cannot receive an award.
     */
    public function test_rejects_cross_company_user_and_course(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        [$scopea, , $coursea] = $this->fixture('isolation_a');
        [, $userb] = $this->fixture('isolation_b');

        $this->expectException(\required_capability_exception::class);
        (new gamification_service())->award(
            $scopea,
            $userb->id,
            10,
            'local_global_events',
            'test.completed',
            'cross-company-0001',
            $coursea->id,
        );
    }

    /**
     * Build a company, assigned course, and member.
     *
     * @param string $shortname Company shortname.
     * @return array
     */
    private function fixture(string $shortname): array {
        $company = company::create_company((object)[
            'name' => ucfirst($shortname),
            'shortname' => $shortname,
            'city' => 'Pune',
            'country' => 'IN',
        ]);
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $company->add_course($course);
        $company->assign_user_to_company($user->id);
        return [tenant_scope::system((int)$company->id), $user, $course, $company];
    }
}
