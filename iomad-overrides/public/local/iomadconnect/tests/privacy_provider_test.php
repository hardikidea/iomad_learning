<?php
// This file is part of Moodle - http://moodle.org/

namespace local_iomadconnect;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\writer;
use local_iomadconnect\privacy\provider;

/**
 * Connector privacy lifecycle tests.
 *
 * @package    local_iomadconnect
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_iomadconnect\privacy\provider
 */
final class privacy_provider_test extends \advanced_testcase {
    /**
     * User and enrolment links export and erase without touching other links.
     */
    public function test_export_and_erasure_remove_user_identity_links(): void {
        global $DB;

        $this->resetAfterTest(true);
        $user = $this->getDataGenerator()->create_user();
        $context = \context_user::instance($user->id);
        $now = time();
        $base = [
            'companyid' => 1,
            'systemkey' => 'privacy',
            'localid' => $user->id,
            'ownedid' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ];

        $DB->insert_record('local_iomadconnect_link', (object)($base + [
            'entitytype' => 'user',
            'externalid' => 'privacy-user',
        ]));
        $DB->insert_record('local_iomadconnect_link', (object)($base + [
            'entitytype' => 'enrolment',
            'externalid' => 'privacy-enrolment',
        ]));
        $DB->insert_record('local_iomadconnect_link', (object)($base + [
            'entitytype' => 'course',
            'externalid' => 'privacy-course',
        ]));
        $DB->insert_record('local_iomadconnect_event', (object)[
            'companyid' => 1,
            'eventid' => 'privacy-user-event',
            'direction' => 'inbound',
            'entitytype' => 'user',
            'entityid' => 'privacy-user',
            'action' => 'upsert',
            'payloadhash' => hash('sha256', 'privacy'),
            'status' => 'processed',
            'attempts' => 1,
            'errorcode' => '',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $contexts = provider::get_contexts_for_userid($user->id);
        $this->assertSame([$context->id], array_map('intval', $contexts->get_contextids()));

        provider::export_user_data(new approved_contextlist(
            $user,
            'local_iomadconnect',
            [$context->id],
        ));
        $export = writer::with_context($context)->get_data([
            get_string('pluginname', 'local_iomadconnect'),
        ]);
        $this->assertCount(2, $export->links);

        provider::delete_data_for_user(new approved_contextlist(
            $user,
            'local_iomadconnect',
            [$context->id],
        ));
        $this->assertSame(
            0,
            $DB->count_records_select(
                'local_iomadconnect_link',
                "localid = :userid AND entitytype IN ('user', 'enrolment')",
                ['userid' => $user->id],
            ),
        );
        $this->assertSame(1, $DB->count_records('local_iomadconnect_link', [
            'entitytype' => 'course',
        ]));
        $this->assertSame(0, $DB->count_records('local_iomadconnect_event', [
            'entitytype' => 'user',
        ]));
    }
}
