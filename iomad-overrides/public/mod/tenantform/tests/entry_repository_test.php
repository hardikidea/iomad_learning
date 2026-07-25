<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_tenantform;

use mod_tenantform\local\entry_repository;
use mod_tenantform\local\template_catalog;

/**
 * Idempotency and audit persistence tests.
 *
 * @package    mod_tenantform
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_tenantform\local\entry_repository
 */
final class entry_repository_test extends \advanced_testcase {
    /**
     * Insert, token lookup, status change, and audit are consistent.
     */
    public function test_entry_lifecycle_is_idempotent_and_audited(): void {
        global $DB;

        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $definition = json_encode(template_catalog::definition('contact'), JSON_THROW_ON_ERROR);
        $formid = $DB->insert_record('tenantform', (object)[
            'course' => $course->id,
            'name' => 'Contact',
            'intro' => '',
            'introformat' => FORMAT_HTML,
            'companyid' => 0,
            'formtype' => 'contact',
            'definitionjson' => $definition,
            'brandingjson' => '{"accent":"#176b5b","density":"comfortable"}',
            'allowguest' => 0,
            'notify' => 0,
            'targetcourseid' => 0,
            'autoenrol' => 0,
            'timecreated' => 100,
            'timemodified' => 100,
        ]);
        $token = str_repeat('a', 48);
        $json = '{"name":"Sam"}';
        $repository = new entry_repository();
        $entry = $repository->insert((object)[
            'tenantformid' => $formid,
            'companyid' => 0,
            'userid' => $user->id,
            'submissiontoken' => $token,
            'status' => 'submitted',
            'datajson' => $json,
            'checksum' => hash('sha256', $json),
            'filecount' => 0,
            'timecreated' => 101,
        ]);

        $this->assertSame((int)$entry->id, (int)$repository->find_by_token($formid, $token)->id);
        $repository->update_status($entry, 'approved', get_admin()->id);
        $this->assertSame(
            'approved',
            $DB->get_field('tenantform_entry', 'status', ['id' => $entry->id]),
        );
        $this->assertSame(2, $DB->count_records('tenantform_audit', ['entryid' => $entry->id]));
    }
}
