<?php
// This file is part of IOMAD - http://www.iomad.org/

namespace local_aicoursecreator;

use local_iomad\company;

defined('MOODLE_INTERNAL') || die();

final class course_publisher_test extends \advanced_testcase {
    public function test_approved_definition_publishes_hidden_company_course_and_quiz(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $company = company::create_company((object)[
            'name' => 'AI Publisher Company',
            'shortname' => 'ai_publisher_company',
            'city' => 'Pune',
            'country' => 'IN',
        ]);
        $repository = new draft_repository();
        $draft = $repository->create([
            'title' => 'Publisher acceptance',
            'brief' => 'A sanitised publisher acceptance brief.',
        ], $company->id, get_admin()->id);
        $repository->queue($draft->id, $company->id, get_admin()->id);
        $repository->mark_generating($draft->id, $company->id, get_admin()->id, 0);
        $repository->save_generated(
            $draft->id,
            $company->id,
            get_admin()->id,
            sample_definition::create('publisher'),
            'test-provider',
            'test-model'
        );
        $repository->approve($draft->id, $company->id, get_admin()->id);

        $course = (new course_publisher())->publish($draft->id, $company->id, get_admin()->id);
        $saved = $repository->get($draft->id, $company->id);
        $modinfo = get_fast_modinfo($course);

        $this->assertSame('published', $saved->status);
        $this->assertSame((int)$course->id, (int)$saved->courseid);
        $this->assertSame(0, (int)$course->visible);
        $this->assertCount(2, $modinfo->get_instances_of('page'));
        $this->assertCount(1, $modinfo->get_instances_of('url'));
        $this->assertCount(1, $modinfo->get_instances_of('quiz'));
        $this->assertSame(2, $DB->count_records('question'));
        $this->assertTrue($DB->record_exists('local_iomad_company_courses', [
            'companyid' => $company->id,
            'courseid' => $course->id,
        ]));
    }
}
