<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster;

use local_tenantmaster\local\native_user_service;
use local_tenantmaster\local\role_service;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/tenantmaster_testcase.php');

/**
 * Native user workflow tests.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class native_user_test extends tenantmaster_testcase {
    /**
     * User identity stays native while company role and mail are applied.
     *
     * @covers \local_tenantmaster\local\native_user_service
     */
    public function test_create_user_stores_no_plugin_identity_or_password(): void {
        global $DB;

        $this->resetAfterTest();
        $tenant = $this->create_tenant();
        (new role_service())->ensure_defaults((int)$tenant->id);
        $sink = $this->redirectEmails();
        $user = (new native_user_service())->create($tenant, (object)[
            'username' => 'tenant.learner',
            'idnumber' => 'USER_TENANT_LEARNER',
            'firstname' => 'Tenant',
            'lastname' => 'Learner',
            'email' => 'tenant.learner@example.com',
            'city' => 'Ahmedabad',
            'country' => 'IN',
            'rolekey' => 'principal_registrar',
            'departmentid' => 0,
            'courseid' => 0,
        ]);

        $this->assertSame('sent', $user->notificationstatus);
        $this->assertCount(1, $sink->get_messages());
        $this->assertTrue($DB->record_exists('local_iomad_company_users', [
            'companyid' => $tenant->companyid,
            'userid' => $user->id,
        ]));
        $audit = $DB->get_record('local_tenantmaster_audit', [
            'tenantid' => $tenant->id,
            'action' => 'people.native_user.created',
        ], '*', MUST_EXIST);
        $this->assertStringNotContainsString('tenant.learner', $audit->detailjson);
        $this->assertStringNotContainsString('password', $audit->detailjson);
    }
}
