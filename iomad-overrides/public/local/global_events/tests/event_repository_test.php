<?php
// This file is part of Moodle - http://moodle.org/

namespace local_global_events;

use local_global_events\local\event_repository;
use local_global_events\local\tenant_scope;
use local_iomad\company;

/**
 * Global-event visibility tests.
 *
 * @package local_global_events
 * @covers \local_global_events\local\event_repository
 */
final class event_repository_test extends \advanced_testcase {
    /**
     * An allowlisted event is not visible to an unrelated company.
     */
    public function test_company_allowlist_is_enforced(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $companya = $this->company('event_a');
        $companyb = $this->company('event_b');
        $companyc = $this->company('event_c');
        $course = $this->getDataGenerator()->create_course();
        $companya->add_course($course);
        $repository = new event_repository();
        $event = $repository->upsert(tenant_scope::system($companya->id), [
            'idnumber' => 'event:test:shared',
            'name' => 'Shared event',
            'description' => 'Sanitized test event.',
            'courseid' => $course->id,
            'visibility' => 'companies',
            'status' => 'published',
        ], [$companya->id, $companyb->id], get_admin()->id);

        $this->assertSame(
            [$event->id],
            array_column($repository->available(tenant_scope::system($companyb->id)), 'id'),
        );
        $this->assertSame([], $repository->available(tenant_scope::system($companyc->id)));
    }

    /**
     * Stable event IDs cannot be taken by another company.
     */
    public function test_event_idnumber_cannot_cross_company(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $companya = $this->company('event_owner_a');
        $companyb = $this->company('event_owner_b');
        $repository = new event_repository();
        $data = [
            'idnumber' => 'event:test:owner',
            'name' => 'Owner event',
            'visibility' => 'companies',
            'status' => 'draft',
        ];
        $repository->upsert(
            tenant_scope::system($companya->id),
            $data,
            [$companya->id],
            get_admin()->id,
        );

        $this->expectException(\invalid_parameter_exception::class);
        $repository->upsert(
            tenant_scope::system($companyb->id),
            $data,
            [$companyb->id],
            get_admin()->id,
        );
    }

    /**
     * Create a company.
     *
     * @param string $shortname Shortname.
     * @return company
     */
    private function company(string $shortname): company {
        return company::create_company((object)[
            'name' => ucfirst($shortname),
            'shortname' => $shortname,
            'city' => 'Pune',
            'country' => 'IN',
        ]);
    }
}
