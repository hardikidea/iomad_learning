<?php
// This file is part of Moodle - http://moodle.org/

namespace local_iomadconnect;

use local_iomad\company;
use local_iomadconnect\local\event_repository;

/**
 * Synchronization event replay tests.
 *
 * @package    local_iomadconnect
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_iomadconnect\local\event_repository
 */
final class event_repository_test extends \advanced_testcase {
    /**
     * Applied events are replay safe and immutable.
     */
    public function test_event_replay_is_idempotent_and_payload_is_immutable(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $company = $this->company('Connect Events', 'connect_events');
        $repository = new event_repository();
        $event = [
            'eventid' => 'EVENT-0001',
            'entitytype' => 'course',
            'entityid' => 'COURSE-0001',
            'action' => 'upsert',
        ];
        $hash = hash('sha256', 'one');

        $this->assertTrue($repository->claim($company->id, $event, $hash));
        $repository->complete($company->id, $event['eventid']);
        $this->assertFalse($repository->claim($company->id, $event, $hash));

        $this->expectException(\invalid_parameter_exception::class);
        $repository->claim($company->id, $event, hash('sha256', 'two'));
    }

    /**
     * Entity/action combinations are explicit.
     */
    public function test_invalid_entity_action_combination_is_rejected(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $company = $this->company('Connect Actions', 'connect_actions');

        $this->expectException(\invalid_parameter_exception::class);
        (new event_repository())->claim($company->id, [
            'eventid' => 'EVENT-0002',
            'entitytype' => 'course',
            'entityid' => 'COURSE-0002',
            'action' => 'unenrol',
        ], hash('sha256', 'invalid'));
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
