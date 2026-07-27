<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster;

use local_tenantmaster\local\catalogue_service;
use local_tenantmaster\local\default_service;
use local_tenantmaster\local\master_service;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/tenantmaster_testcase.php');

/**
 * Global catalogue and tenant inheritance tests.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class catalogue_service_test extends tenantmaster_testcase {
    /**
     * Catalogue can be administered before a company and is adopted later.
     *
     * @covers \local_tenantmaster\local\catalogue_service
     */
    public function test_pre_company_catalogue_item_is_adopted_with_provenance(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $service = new catalogue_service();
        $service->ensure_seeded();
        $saved = $service->save((object)[
            'id' => 0,
            'scope' => 'school',
            'mastertype' => 'board',
            'externalid' => 'BOARD_TEST',
            'code' => 'TEST_BOARD',
            'name' => 'Test Board',
            'description' => '',
            'payloadjson' => '{}',
            'parentitemid' => 0,
            'active' => 1,
            'sortorder' => 99,
        ]);
        $this->assertSame(0, $DB->count_records('local_tenantmaster_tenant'));

        $tenant = $this->create_tenant('school');
        (new default_service())->adopt($tenant);
        $master = $DB->get_record('local_tenantmaster_master', [
            'tenantid' => $tenant->id,
            'mastertype' => 'board',
            'externalid' => 'BOARD_TEST',
        ], '*', MUST_EXIST);

        $this->assertSame((int)$saved->id, (int)$master->catalogitemid);
        $this->assertSame((int)$saved->version, (int)$master->catalogversion);
        $this->assertSame((string)$saved->managedhash, (string)$master->inheritedhash);
    }

    /**
     * Propagation updates inherited records and preserves tenant customisation.
     *
     * @covers \local_tenantmaster\local\catalogue_service::propagate
     */
    public function test_propagation_preserves_customised_tenant_values(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $service = new catalogue_service();
        $service->ensure_seeded();
        $firsttenant = $this->create_tenant('school');
        $secondtenant = $this->create_tenant('school');
        (new default_service())->adopt($firsttenant);
        (new default_service())->adopt($secondtenant);

        $secondmaster = $DB->get_record('local_tenantmaster_master', [
            'tenantid' => $secondtenant->id,
            'mastertype' => 'board',
            'externalid' => 'BOARD_CBSE',
        ], '*', MUST_EXIST);
        $secondmaster->name = 'Tenant-specific board name';
        (new master_service())->save($secondmaster);

        $catalogue = $DB->get_record('local_tenantmaster_catitem', [
            'scope' => 'school',
            'mastertype' => 'board',
            'externalid' => 'BOARD_CBSE',
        ], '*', MUST_EXIST);
        $updated = $service->save((object)[
            'id' => $catalogue->id,
            'scope' => $catalogue->scope,
            'mastertype' => $catalogue->mastertype,
            'externalid' => $catalogue->externalid,
            'code' => $catalogue->code,
            'name' => 'Updated Central Board',
            'description' => $catalogue->description,
            'payloadjson' => $catalogue->payloadjson,
            'parentitemid' => 0,
            'active' => 1,
            'sortorder' => $catalogue->sortorder,
        ]);
        $result = $service->propagate((int)$updated->id);

        $this->assertSame(1, $result['updated']);
        $this->assertSame(1, $result['conflicts']);
        $this->assertSame('Updated Central Board', $DB->get_field(
            'local_tenantmaster_master',
            'name',
            ['tenantid' => $firsttenant->id, 'mastertype' => 'board', 'externalid' => 'BOARD_CBSE'],
            MUST_EXIST,
        ));
        $this->assertSame('Tenant-specific board name', $DB->get_field(
            'local_tenantmaster_master',
            'name',
            ['tenantid' => $secondtenant->id, 'mastertype' => 'board', 'externalid' => 'BOARD_CBSE'],
            MUST_EXIST,
        ));
        $this->assertSame('complete', $DB->get_field(
            'local_tenantmaster_catitem',
            'propagationstate',
            ['id' => $updated->id],
            MUST_EXIST,
        ));
    }
}
