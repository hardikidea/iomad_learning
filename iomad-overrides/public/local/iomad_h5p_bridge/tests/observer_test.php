<?php
// This file is part of Moodle - http://moodle.org/

namespace local_iomad_h5p_bridge;

use local_iomad\company;

/**
 * H5P bridge reward tests.
 *
 * @package local_iomad_h5p_bridge
 * @covers \local_iomad_h5p_bridge\observer
 */
final class observer_test extends \advanced_testcase {
    /**
     * A successful trusted answer is awarded once.
     */
    public function test_successful_answer_awards_once(): void {
        global $DB;

        $this->resetAfterTest(true);
        $company = company::create_company((object)[
            'name' => 'H5P Company',
            'shortname' => 'h5p_company',
            'city' => 'Pune',
            'country' => 'IN',
        ]);
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module('h5pactivity', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_user();
        $company->add_course($course);
        $company->assign_user_to_company($user->id);
        $this->setUser($user);
        $event = \mod_h5pactivity\event\statement_received::create([
            'context' => \context_module::instance($activity->cmid),
            'objectid' => $activity->id,
            'userid' => $user->id,
            'other' => [
                'verb' => ['id' => 'http://adlnet.gov/expapi/verbs/answered'],
                'result' => ['success' => true],
                'object' => ['id' => 'urn:test:question'],
            ],
        ]);

        observer::statement_received($event);
        observer::statement_received($event);

        $this->assertSame(1, $DB->count_records('local_ge_ledger'));
        $this->assertSame(5, (int)$DB->get_field('local_ge_ledger', 'points', []));
    }
}
