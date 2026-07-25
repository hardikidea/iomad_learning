<?php
// This file is part of IOMAD - http://www.iomad.org/

namespace local_iomadpagebuilder;

use local_iomad\company;

defined('MOODLE_INTERNAL') || die();

final class page_repository_test extends \advanced_testcase {
    public function test_page_reads_cannot_cross_company_scope(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $companya = company::create_company((object)[
            'name' => 'Page Company A',
            'shortname' => 'page_company_a',
            'city' => 'Pune',
            'country' => 'IN',
        ]);
        $companyb = company::create_company((object)[
            'name' => 'Page Company B',
            'shortname' => 'page_company_b',
            'city' => 'Pune',
            'country' => 'IN',
        ]);
        $repository = new page_repository();
        $page = $repository->save([
            'name' => 'Company A home',
            'slug' => 'home',
            'target' => 'frontpage',
            'definition' => catalog::template('school_home'),
        ], $companya->id, get_admin()->id);

        $this->expectException(\required_capability_exception::class);
        $repository->get($page->id, $companyb->id);
    }

    public function test_save_is_idempotent_and_revisions_are_immutable(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $company = company::create_company((object)[
            'name' => 'Revision Company',
            'shortname' => 'revision_company',
            'city' => 'Pune',
            'country' => 'IN',
        ]);
        $repository = new page_repository();
        $input = [
            'name' => 'Home',
            'slug' => 'home',
            'target' => 'frontpage',
            'definition' => catalog::template('school_home'),
        ];
        $page = $repository->save($input, $company->id, get_admin()->id);
        $unchanged = $repository->save($input, $company->id, get_admin()->id);

        $this->assertSame((int)$page->id, (int)$unchanged->id);
        $this->assertSame(1, (int)$unchanged->revision);
        $this->assertSame(1, $DB->count_records('local_iomadpagebuilder_rev', ['pageid' => $page->id]));

        $input['definition']['sections'][0]['title'] = 'Changed title';
        $changed = $repository->save($input, $company->id, get_admin()->id);
        $this->assertSame(2, (int)$changed->revision);
        $this->assertSame(2, $DB->count_records('local_iomadpagebuilder_rev', ['pageid' => $page->id]));
    }
}
