<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/tenantmaster_testcase.php');

/**
 * Administrator navigation contracts.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class navigation_test extends tenantmaster_testcase {
    /**
     * Admin Tools must not pass pre-escaped moodle_url objects to Mustache.
     */
    public function test_admin_tools_renderer_escapes_menu_urls_once(): void {
        global $CFG;

        $blocksource = file_get_contents(
            $CFG->dirroot . '/blocks/iomad_company_admin/block_iomad_company_admin.php',
        );
        $pagesource = file_get_contents(
            $CFG->dirroot . '/blocks/iomad_company_admin/index.php',
        );

        $this->assertNotFalse($blocksource);
        $this->assertNotFalse($pagesource);
        $this->assertStringContainsString("\$menu['url'] = \$url->out(false);", $blocksource);
        $this->assertStringContainsString("\$menu['url'] = \$url->out(false);", $pagesource);
    }

    /**
     * School tenant operations use a separate selected-company tab.
     */
    public function test_iomad_admin_tools_menu_has_school_tenant_scoped_tiles(): void {
        global $CFG, $SESSION;

        $this->resetAfterTest();
        $this->setAdminUser();
        require_once($CFG->dirroot . '/local/tenantmaster/db/iomadmenu.php');
        $tenant = $this->create_tenant('school');
        $SESSION->currenteditingcompany = (int)$tenant->companyid;
        $menu = local_tenantmaster_menu();

        $this->assertArrayHasKey('tenantmaster', $menu);
        $this->assertArrayHasKey('tenantmasteracademicyears', $menu);
        $this->assertArrayHasKey('tenantmaster_board', $menu);
        $this->assertArrayHasKey('tenantmaster_medium', $menu);
        $this->assertArrayHasKey('tenantmaster_grade', $menu);
        $this->assertArrayHasKey('tenantmaster_stream', $menu);
        $this->assertArrayHasKey('tenantmaster_division', $menu);
        $this->assertArrayHasKey('tenantmaster_subject', $menu);
        $this->assertArrayHasKey('tenantmasterclasses', $menu);
        $this->assertArrayNotHasKey('tenantmaster_programme', $menu);
        $this->assertArrayNotHasKey('tenantmastercatalogue', $menu);
        $this->assertStringContainsString('section=dashboard', $menu['tenantmaster']['url']);
        $this->assertStringContainsString('companyid=' . $tenant->companyid, $menu['tenantmaster']['url']);
        $this->assertStringNotContainsString('&amp;', $menu['tenantmaster']['url']);
        $this->assertSame('local/tenantmaster:view', $menu['tenantmaster']['cap']);
        $this->assertSame('TenantAdmin', $menu['tenantmaster']['category']);
        $this->assertSame('fa-building-columns', $menu['tenantmaster']['tabicon']);
        $this->assertSame(9, $menu['tenantmaster']['tab']);
        $this->assertTrue($menu['tenantmaster']['tabcustom']);
        $this->assertStringContainsString('academicview=years', $menu['tenantmasteracademicyears']['url']);
        $this->assertStringContainsString('type=grade', $menu['tenantmaster_grade']['url']);
    }

    /**
     * University tenants receive their own academic vocabulary.
     */
    public function test_iomad_admin_tools_menu_has_university_master_tiles(): void {
        global $CFG, $SESSION;

        $this->resetAfterTest();
        require_once($CFG->dirroot . '/local/tenantmaster/db/iomadmenu.php');
        $tenant = $this->create_tenant('university');
        $SESSION->currenteditingcompany = (int)$tenant->companyid;
        $menu = local_tenantmaster_menu();

        $this->assertArrayHasKey('tenantmaster_programme', $menu);
        $this->assertArrayHasKey('tenantmaster_semester', $menu);
        $this->assertArrayHasKey('tenantmaster_specialisation', $menu);
        $this->assertArrayHasKey('tenantmaster_credit', $menu);
        $this->assertArrayHasKey('tenantmaster_subject', $menu);
        $this->assertArrayNotHasKey('tenantmaster_board', $menu);
        $this->assertArrayNotHasKey('tenantmaster_grade', $menu);
        $this->assertArrayNotHasKey('tenantmasterclasses', $menu);
    }

    /**
     * Native companies remain onboarding-only until academic management is initialised.
     */
    public function test_uninitialised_company_has_only_onboarding_tiles(): void {
        global $CFG, $SESSION;

        $this->resetAfterTest();
        $this->setAdminUser();
        require_once($CFG->dirroot . '/local/tenantmaster/db/iomadmenu.php');
        $company = \local_iomad\company::create_company((object)[
            'name' => 'Uninitialised School',
            'shortname' => 'UNINITIALISED',
            'code' => 'UNINITIALISED_SCHOOL',
            'address' => '',
            'city' => 'Ahmedabad',
            'region' => 'Gujarat',
            'postcode' => '380001',
            'country' => 'IN',
            'theme' => '',
            'parentid' => 0,
            'hostname' => '',
            'custom1' => '',
            'custom2' => '',
            'custom3' => '',
            'templates' => [],
        ]);
        $SESSION->currenteditingcompany = (int)$company->id;
        $menu = local_tenantmaster_menu();

        $this->assertSame(['tenantmaster', 'tenantmasterinstitutions'], array_keys($menu));
    }
}
