<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster;

use local_iomad\company;
use local_iomad\custom_context\context_company;
use local_tenantmaster\local\people_service;
use local_tenantmaster\local\role_service;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/tenantmaster_testcase.php');

/**
 * Canonical tenant role mapping and native assignment tests.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_tenantmaster\local\role_service
 * @covers     \local_tenantmaster\local\people_service
 */
final class role_service_test extends tenantmaster_testcase {
    /**
     * Reporter and IT roles retain their reviewed manager types and scope.
     */
    public function test_company_roles_use_least_privilege_native_assignments(): void {
        global $DB;

        $this->resetAfterTest();
        $tenant = $this->create_tenant();
        $roleservice = new role_service();
        $roleservice->ensure_defaults((int)$tenant->id);
        $companycontext = context_company::instance((int)$tenant->companyid);
        $rootdepartment = company::get_company_parentnode((int)$tenant->companyid);

        $reporter = $this->getDataGenerator()->create_user();
        company::upsert_company_user(
            (int)$reporter->id,
            (int)$tenant->companyid,
            (int)$rootdepartment->id,
            0
        );
        (new people_service())->assign_role($tenant, (int)$reporter->id, 'trustee_management');
        $reportermapping = $DB->get_record('local_tenantmaster_rolemap', [
            'tenantid' => $tenant->id,
            'rolekey' => 'trustee_management',
        ], '*', MUST_EXIST);
        $reportercompanyuser = $DB->get_record('local_iomad_company_users', [
            'companyid' => $tenant->companyid,
            'userid' => $reporter->id,
        ], '*', MUST_EXIST);
        $this->assertSame(4, (int)$reportermapping->managertype);
        $this->assertSame(4, (int)$reportercompanyuser->managertype);
        $this->assertTrue($DB->record_exists('role_assignments', [
            'roleid' => $reportermapping->roleid,
            'userid' => $reporter->id,
            'contextid' => $companycontext->id,
        ]));

        $itcoordinator = $this->getDataGenerator()->create_user();
        company::upsert_company_user(
            (int)$itcoordinator->id,
            (int)$tenant->companyid,
            (int)$rootdepartment->id,
            0
        );
        (new people_service())->assign_role($tenant, (int)$itcoordinator->id, 'it_coordinator');
        $itmapping = $DB->get_record('local_tenantmaster_rolemap', [
            'tenantid' => $tenant->id,
            'rolekey' => 'it_coordinator',
        ], '*', MUST_EXIST);
        $itrole = $DB->get_record('role', ['id' => $itmapping->roleid], '*', MUST_EXIST);
        $itcompanyuser = $DB->get_record('local_iomad_company_users', [
            'companyid' => $tenant->companyid,
            'userid' => $itcoordinator->id,
        ], '*', MUST_EXIST);
        $this->assertSame('institutionitcoordinator', $itrole->shortname);
        $this->assertSame(0, (int)$itmapping->managertype);
        $this->assertSame(0, (int)$itcompanyuser->managertype);
        $this->assertTrue($DB->record_exists('role_assignments', [
            'roleid' => $itrole->id,
            'userid' => $itcoordinator->id,
            'contextid' => $companycontext->id,
        ]));
        $this->assertFalse(is_siteadmin($itcoordinator));
        $this->assertTrue(has_capability(
            'local/tenantmaster:managepeople',
            $companycontext,
            $itcoordinator
        ));
        $this->assertFalse(has_capability(
            'local/tenantmaster:sync',
            $companycontext,
            $itcoordinator
        ));
    }

    /**
     * Guardian relationships use one user-context role across all import paths.
     */
    public function test_guardian_relationship_uses_canonical_role(): void {
        global $DB;

        $this->resetAfterTest();
        $tenant = $this->create_tenant();
        (new role_service())->ensure_defaults((int)$tenant->id);
        $rootdepartment = company::get_company_parentnode((int)$tenant->companyid);
        $guardian = $this->getDataGenerator()->create_user();
        $learner = $this->getDataGenerator()->create_user();
        foreach ([$guardian, $learner] as $user) {
            company::upsert_company_user(
                (int)$user->id,
                (int)$tenant->companyid,
                (int)$rootdepartment->id,
                0
            );
        }

        (new people_service())->link_guardian($tenant, (int)$guardian->id, (int)$learner->id);
        $roleid = (int)$DB->get_field('role', 'id', ['shortname' => 'tenantguardian'], MUST_EXIST);
        $this->assertTrue($DB->record_exists('role_assignments', [
            'roleid' => $roleid,
            'userid' => $guardian->id,
            'contextid' => \context_user::instance((int)$learner->id)->id,
        ]));
    }
}
