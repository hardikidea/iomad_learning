<?php
// This file is part of IOMAD - http://www.iomad.org/

namespace local_aicoursecreator;

use local_iomad\company;

defined('MOODLE_INTERNAL') || die();

final class draft_repository_test extends \advanced_testcase {
    public function test_draft_cannot_be_read_from_another_company(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $companya = $this->company('AI Company A', 'ai_company_a');
        $companyb = $this->company('AI Company B', 'ai_company_b');
        $repository = new draft_repository();
        $draft = $repository->create([
            'title' => 'Company A course',
            'brief' => 'A sanitised course brief.',
        ], $companya->id, get_admin()->id);

        $this->expectException(\required_capability_exception::class);
        $repository->get($draft->id, $companyb->id);
    }

    public function test_generation_review_and_approval_are_audited(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $company = $this->company('AI Workflow Company', 'ai_workflow_company');
        $repository = new draft_repository();
        $draft = $repository->create([
            'title' => 'Workflow course',
            'brief' => 'A sanitised workflow course brief.',
        ], $company->id, get_admin()->id);
        $repository->queue($draft->id, $company->id, get_admin()->id);
        $repository->mark_generating($draft->id, $company->id, get_admin()->id, 2);
        $draft = $repository->save_generated(
            $draft->id,
            $company->id,
            get_admin()->id,
            sample_definition::create('workflow'),
            'test-provider',
            'test-model'
        );
        $draft = $repository->approve($draft->id, $company->id, get_admin()->id);

        $this->assertSame('approved', $draft->status);
        $this->assertSame(2, (int)$draft->credits);
        $this->assertNotEmpty($draft->checksum);
        $this->assertSame(5, $DB->count_records('local_aicoursecreator_audit', ['draftid' => $draft->id]));
    }

    private function company(string $name, string $shortname): company {
        return company::create_company((object)[
            'name' => $name,
            'shortname' => $shortname,
            'city' => 'Pune',
            'country' => 'IN',
        ]);
    }
}
