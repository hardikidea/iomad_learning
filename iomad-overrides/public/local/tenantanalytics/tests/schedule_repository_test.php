<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantanalytics;

use local_tenantanalytics\local\schedule_repository;

/**
 * Schedule ownership, claim, and audit tests.
 *
 * @package    local_tenantanalytics
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_tenantanalytics\local\schedule_repository
 */
final class schedule_repository_test extends \advanced_testcase {
    /**
     * A due schedule is claimed once and completion creates an audit.
     */
    public function test_claim_and_complete_are_resumable_and_auditable(): void {
        global $DB;

        $this->resetAfterTest(true);
        $user = $this->getDataGenerator()->create_user();
        $now = 1784950000;
        $id = $DB->insert_record('local_tanalytics_schedule', (object)[
            'companyid' => 42,
            'userid' => $user->id,
            'reportkey' => 'course_engagement',
            'dataformat' => 'csv',
            'frequency' => 'daily',
            'filtersjson' => '{"lookbackdays":30,"courseid":0,"cohortid":0,"groupid":0}',
            'enabled' => 1,
            'nextrun' => $now - 1,
            'lastrun' => 0,
            'lockeduntil' => 0,
            'locktoken' => '',
            'timecreated' => $now - DAYSECS,
            'timemodified' => $now - DAYSECS,
        ]);
        $repository = new schedule_repository();

        $claimed = $repository->claim_due($now);
        $this->assertCount(1, $claimed);
        $this->assertSame($id, (int)$claimed[0]->id);
        $this->assertSame([], $repository->claim_due($now));
        $repository->complete($claimed[0], 'sent', 12, str_repeat('a', 64), $now + 5);

        $schedule = $DB->get_record('local_tanalytics_schedule', ['id' => $id], '*', MUST_EXIST);
        $this->assertSame(0, (int)$schedule->lockeduntil);
        $this->assertSame('', $schedule->locktoken);
        $this->assertGreaterThan($now, (int)$schedule->nextrun);
        $run = $DB->get_record('local_tanalytics_run', ['scheduleid' => $id], '*', MUST_EXIST);
        $this->assertSame('sent', $run->status);
        $this->assertSame(12, (int)$run->rowcount);
        $this->assertSame(str_repeat('a', 64), $run->checksum);
    }

    /**
     * Rolling filters are materialized relative to delivery time.
     */
    public function test_filters_are_rolling_and_owner_lookup_is_private(): void {
        global $DB;

        $this->resetAfterTest(true);
        $owner = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $record = (object)[
            'companyid' => 9,
            'userid' => $owner->id,
            'reportkey' => 'visits',
            'dataformat' => 'pdf',
            'frequency' => 'weekly',
            'filtersjson' => '{"lookbackdays":7,"courseid":3,"cohortid":4,"groupid":5}',
            'enabled' => 1,
            'nextrun' => 100,
            'lastrun' => 0,
            'lockeduntil' => 0,
            'locktoken' => '',
            'timecreated' => 50,
            'timemodified' => 50,
        ];
        $record->id = $DB->insert_record('local_tanalytics_schedule', $record);
        $repository = new schedule_repository();
        $filters = $repository->filters_for_run($record, 1000000);

        $this->assertSame(1000000 - (7 * DAYSECS), $filters['since']);
        $this->assertSame(1000000, $filters['until']);
        $this->assertSame(3, $filters['courseid']);
        $this->assertSame($record->id, (int)$repository->get_owned($record->id, $owner->id)->id);
        $this->expectException(\dml_missing_record_exception::class);
        $repository->get_owned($record->id, $other->id);
    }
}
