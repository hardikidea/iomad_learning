<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantanalytics;

use local_iomad\company;
use local_tenantanalytics\local\tenant_scope;

/**
 * Company, child-company, department, and learner boundary tests.
 *
 * @package    local_tenantanalytics
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_tenantanalytics\local\tenant_scope
 */
final class tenant_scope_test extends \advanced_testcase {
    /**
     * A department scope contains only users in that department tree.
     */
    public function test_department_predicate_rejects_sibling_and_other_company_users(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $companya = $this->company('Analytics Company A', 'analytics_a');
        $companyb = $this->company('Analytics Company B', 'analytics_b');
        $root = company::get_company_parentnode($companya->id);
        company::create_department(0, $companya->id, 'Science', 'science', $root->id);
        company::create_department(0, $companya->id, 'Arts', 'arts', $root->id);
        $scienceid = (int)$DB->get_field(
            'local_iomad_company_departments',
            'id',
            ['companyid' => $companya->id, 'shortname' => 'science'],
            MUST_EXIST
        );
        $artsid = (int)$DB->get_field(
            'local_iomad_company_departments',
            'id',
            ['companyid' => $companya->id, 'shortname' => 'arts'],
            MUST_EXIST
        );
        $scienceuser = $this->getDataGenerator()->create_user();
        $artsuser = $this->getDataGenerator()->create_user();
        $otheruser = $this->getDataGenerator()->create_user();
        company::upsert_company_user($scienceuser->id, $companya->id, $scienceid, 0);
        company::upsert_company_user($artsuser->id, $companya->id, $artsid, 0);
        $companyb->assign_user_to_company($otheruser->id);

        $scope = new tenant_scope($companya->id, get_admin()->id, false, [$scienceid]);
        [$where, $params] = $scope->user_predicate('u.id');
        $ids = $DB->get_fieldset_sql("SELECT u.id FROM {user} u WHERE {$where}", $params);

        $this->assertContains((string)$scienceuser->id, array_map('strval', $ids));
        $this->assertNotContains((string)$artsuser->id, array_map('strval', $ids));
        $this->assertNotContains((string)$otheruser->id, array_map('strval', $ids));
    }

    /**
     * Own-data mode always resolves only the requesting user.
     */
    public function test_own_scope_ignores_company_memberships(): void {
        global $DB;

        $this->resetAfterTest(true);
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $scope = new tenant_scope(0, $user->id, true);
        [$where, $params] = $scope->user_predicate('u.id');
        $ids = $DB->get_fieldset_sql("SELECT u.id FROM {user} u WHERE {$where}", $params);

        $this->assertSame([(string)$user->id], array_map('strval', $ids));
        $this->assertNotContains((string)$other->id, array_map('strval', $ids));
    }

    /**
     * Create a company through the supported IOMAD API.
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
