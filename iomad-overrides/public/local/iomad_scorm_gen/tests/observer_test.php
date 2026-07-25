<?php
// This file is part of Moodle - http://moodle.org/

namespace local_iomad_scorm_gen;

use local_iomad\company;

/**
 * SCORM bridge reward tests.
 *
 * @package local_iomad_scorm_gen
 * @covers \local_iomad_scorm_gen\observer
 */
final class observer_test extends \advanced_testcase {
    /**
     * A trusted completion status is awarded once per attempt.
     */
    public function test_completion_awards_once_per_attempt(): void {
        global $DB;

        $this->resetAfterTest(true);
        $company = company::create_company((object)[
            'name' => 'SCORM Company',
            'shortname' => 'scorm_company',
            'city' => 'Pune',
            'country' => 'IN',
        ]);
        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module('scorm', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_user();
        $company->add_course($course);
        $company->assign_user_to_company($user->id);
        $this->setUser($user);
        $event = \mod_scorm\event\status_submitted::create([
            'context' => \context_module::instance($activity->cmid),
            'objectid' => $activity->id,
            'relateduserid' => $user->id,
            'other' => [
                'attemptid' => 1,
                'cmielement' => 'cmi.core.lesson_status',
                'cmivalue' => 'completed',
            ],
        ]);

        observer::status_submitted($event);
        observer::status_submitted($event);

        $this->assertSame(1, $DB->count_records('local_ge_ledger'));
        $this->assertSame(20, (int)$DB->get_field('local_ge_ledger', 'points', []));
    }
}
