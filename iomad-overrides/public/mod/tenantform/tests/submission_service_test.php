<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_tenantform;

use mod_tenantform\local\submission_service;
use mod_tenantform\local\template_catalog;

/**
 * Server-authoritative field normalisation tests.
 *
 * @package    mod_tenantform
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_tenantform\local\condition_evaluator
 * @covers     \mod_tenantform\local\submission_service
 */
final class submission_service_test extends \advanced_testcase {
    /**
     * Hidden required conditional fields are omitted.
     */
    public function test_hidden_required_field_is_not_accepted_or_required(): void {
        $definition = template_catalog::definition('feedback');
        [$values, $uploads, $errors] = (new submission_service())->normalise(
            $definition,
            [
                'field_area' => 'Content',
                'field_helpful' => 'Yes',
                'field_comments' => 'Useful',
                'field_email' => 'should-not-be-stored@example.test',
            ],
            [],
        );
        $this->assertSame([], $errors);
        $this->assertSame([], $uploads);
        $this->assertSame('0', $values['followup']);
        $this->assertArrayNotHasKey('email', $values);
    }

    /**
     * A visible conditional field remains required.
     */
    public function test_visible_required_field_is_enforced(): void {
        $definition = template_catalog::definition('feedback');
        [, , $errors] = (new submission_service())->normalise(
            $definition,
            [
                'field_area' => 'Content',
                'field_helpful' => 'Yes',
                'field_comments' => 'Useful',
                'field_followup' => '1',
            ],
            [],
        );
        $this->assertArrayHasKey('email', $errors);
    }

    /**
     * Values outside a select allowlist are rejected.
     */
    public function test_select_allowlist_is_enforced(): void {
        $definition = template_catalog::definition('contact');
        [, , $errors] = (new submission_service())->normalise(
            $definition,
            [
                'field_name' => 'Sam',
                'field_email' => 'sam@example.test',
                'field_topic' => 'Injected',
                'field_message' => 'Hello',
            ],
            [],
        );
        $this->assertArrayHasKey('topic', $errors);
    }
}
