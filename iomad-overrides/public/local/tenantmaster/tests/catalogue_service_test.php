<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster;

use local_tenantmaster\local\catalogue_service;
use local_tenantmaster\local\default_service;
use local_tenantmaster\local\master_repository;
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

    /**
     * Removal deactivates inherited records and restoration reactivates them.
     *
     * @covers \local_tenantmaster\local\catalogue_service::remove
     * @covers \local_tenantmaster\local\catalogue_service::restore
     */
    public function test_remove_and_restore_synchronize_inherited_tenant_record(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $service = new catalogue_service();
        $tenant = $this->create_tenant('school');
        $saved = $service->save((object)[
            'id' => 0,
            'scope' => 'school',
            'mastertype' => 'subject',
            'externalid' => 'SUBJECT_REMOVE_TEST',
            'code' => 'REMOVE_TEST',
            'name' => 'Removal Test Subject',
            'description' => '',
            'payloadjson' => '{}',
            'parentitemid' => 0,
            'active' => 1,
            'sortorder' => 500,
        ]);
        $service->propagate((int)$saved->id);
        $impact = $service->removal_impact((int)$saved->id);
        $this->assertSame(1, $impact->linkedtenants);
        $this->assertSame(0, $impact->customisedtenants);
        $this->assertTrue($impact->canremove);

        $removed = $service->remove((int)$saved->id);
        $this->assertSame(1, (int)$removed->deleted);
        $this->assertSame(0, (int)$removed->active);
        $this->assertArrayNotHasKey(
            (int)$saved->id,
            $service->list('school', 'subject'),
        );
        $this->assertArrayHasKey(
            (int)$saved->id,
            $service->list('school', 'subject', true),
        );
        $result = $service->propagate((int)$saved->id);
        $this->assertSame(1, $result['updated']);
        $this->assertSame(0, (int)$DB->get_field('local_tenantmaster_master', 'active', [
            'tenantid' => $tenant->id,
            'catalogitemid' => $saved->id,
        ], MUST_EXIST));

        $restored = $service->restore((int)$saved->id);
        $this->assertSame(0, (int)$restored->deleted);
        $this->assertSame(1, (int)$restored->active);
        $result = $service->propagate((int)$saved->id);
        $this->assertSame(1, $result['updated']);
        $this->assertSame(1, (int)$DB->get_field('local_tenantmaster_master', 'active', [
            'tenantid' => $tenant->id,
            'catalogitemid' => $saved->id,
        ], MUST_EXIST));
    }

    /**
     * Removal never deactivates a tenant-customized inherited record.
     *
     * @covers \local_tenantmaster\local\catalogue_service::remove
     */
    public function test_remove_preserves_customised_tenant_record_as_conflict(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $service = new catalogue_service();
        $tenant = $this->create_tenant('school');
        $saved = $service->save((object)[
            'id' => 0,
            'scope' => 'school',
            'mastertype' => 'subject',
            'externalid' => 'SUBJECT_REMOVE_CONFLICT',
            'code' => 'REMOVE_CONFLICT',
            'name' => 'Inherited Subject',
            'description' => '',
            'payloadjson' => '{}',
            'parentitemid' => 0,
            'active' => 1,
            'sortorder' => 501,
        ]);
        $service->propagate((int)$saved->id);
        $master = $DB->get_record('local_tenantmaster_master', [
            'tenantid' => $tenant->id,
            'catalogitemid' => $saved->id,
        ], '*', MUST_EXIST);
        $master->name = 'Tenant Customized Subject';
        (new master_service())->save($master);

        $impact = $service->removal_impact((int)$saved->id);
        $this->assertSame(1, $impact->customisedtenants);
        $service->remove((int)$saved->id);
        $result = $service->propagate((int)$saved->id);

        $this->assertSame(1, $result['conflicts']);
        $this->assertSame('Tenant Customized Subject', $DB->get_field(
            'local_tenantmaster_master',
            'name',
            ['id' => $master->id],
            MUST_EXIST,
        ));
        $this->assertSame(1, (int)$DB->get_field(
            'local_tenantmaster_master',
            'active',
            ['id' => $master->id],
            MUST_EXIST,
        ));
    }

    /**
     * A parent cannot be removed while non-removed child templates depend on it.
     *
     * @covers \local_tenantmaster\local\catalogue_service::remove
     */
    public function test_remove_blocks_dependent_child_templates(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $service = new catalogue_service();
        $parent = $service->save((object)[
            'id' => 0,
            'scope' => 'school',
            'mastertype' => 'grade',
            'externalid' => 'GRADE_REMOVE_PARENT',
            'code' => 'REMOVE_PARENT',
            'name' => 'Removal Parent',
            'description' => '',
            'payloadjson' => '{}',
            'parentitemid' => 0,
            'active' => 1,
            'sortorder' => 502,
        ]);
        $service->save((object)[
            'id' => 0,
            'scope' => 'school',
            'mastertype' => 'grade',
            'externalid' => 'GRADE_REMOVE_CHILD',
            'code' => 'REMOVE_CHILD',
            'name' => 'Removal Child',
            'description' => '',
            'payloadjson' => '{}',
            'parentitemid' => $parent->id,
            'active' => 1,
            'sortorder' => 503,
        ]);
        $impact = $service->removal_impact((int)$parent->id);
        $this->assertSame(1, $impact->dependentchildren);
        $this->assertFalse($impact->canremove);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('catalogueremoveblocked', 'local_tenantmaster'));
        $service->remove((int)$parent->id);
    }

    /**
     * Removed built-in defaults remain tombstones and are not silently reseeded.
     *
     * @covers \local_tenantmaster\local\catalogue_service::ensure_seeded
     */
    public function test_removed_builtin_is_not_reseeded(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $service = new catalogue_service();
        $service->ensure_seeded();
        $item = $DB->get_record('local_tenantmaster_catitem', [
            'scope' => 'school',
            'mastertype' => 'board',
            'externalid' => 'BOARD_CBSE',
        ], '*', MUST_EXIST);
        $service->remove((int)$item->id);
        $service->ensure_seeded();

        $this->assertSame(1, $DB->count_records('local_tenantmaster_catitem', [
            'scope' => 'school',
            'mastertype' => 'board',
            'externalid' => 'BOARD_CBSE',
        ]));
        $this->assertSame(1, (int)$DB->get_field(
            'local_tenantmaster_catitem',
            'deleted',
            ['id' => $item->id],
            MUST_EXIST,
        ));
    }

    /**
     * Restoration retains an item's inactive state from before removal.
     *
     * @covers \local_tenantmaster\local\catalogue_service::remove
     * @covers \local_tenantmaster\local\catalogue_service::restore
     */
    public function test_restore_preserves_pre_removal_inactive_state(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $service = new catalogue_service();
        $saved = $service->save((object)[
            'id' => 0,
            'scope' => 'school',
            'mastertype' => 'subject',
            'externalid' => 'SUBJECT_INACTIVE_RESTORE',
            'code' => 'INACTIVE_RESTORE',
            'name' => 'Inactive Restore Subject',
            'description' => '',
            'payloadjson' => '{}',
            'parentitemid' => 0,
            'active' => 0,
            'sortorder' => 504,
        ]);

        $removed = $service->remove((int)$saved->id);
        $this->assertSame(0, (int)$removed->activebeforedelete);
        $restored = $service->restore((int)$saved->id);

        $this->assertSame(0, (int)$restored->deleted);
        $this->assertSame(0, (int)$restored->active);
    }

    /**
     * Removing a catalogue item does not create missing tenant masters.
     *
     * @covers \local_tenantmaster\local\catalogue_service::propagate
     */
    public function test_removed_item_does_not_create_missing_tenant_record(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $service = new catalogue_service();
        $tenant = $this->create_tenant('school');
        $saved = $service->save((object)[
            'id' => 0,
            'scope' => 'school',
            'mastertype' => 'subject',
            'externalid' => 'SUBJECT_REMOVE_WITHOUT_LINK',
            'code' => 'REMOVE_WITHOUT_LINK',
            'name' => 'Unlinked Removal Subject',
            'description' => '',
            'payloadjson' => '{}',
            'parentitemid' => 0,
            'active' => 1,
            'sortorder' => 505,
        ]);
        $service->remove((int)$saved->id);
        $result = $service->propagate((int)$saved->id);

        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['unchanged']);
        $this->assertFalse($DB->record_exists('local_tenantmaster_master', [
            'tenantid' => $tenant->id,
            'catalogitemid' => $saved->id,
        ]));
    }

    /**
     * Impact preview includes legacy records resolved by the stable key.
     *
     * @covers \local_tenantmaster\local\catalogue_service::removal_impact
     */
    public function test_removal_impact_includes_unlinked_stable_key_match(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $service = new catalogue_service();
        $tenant = $this->create_tenant('school');
        $saved = $service->save((object)[
            'id' => 0,
            'scope' => 'school',
            'mastertype' => 'subject',
            'externalid' => 'SUBJECT_UNLINKED_IMPACT',
            'code' => 'UNLINKED_IMPACT',
            'name' => 'Catalogue Subject',
            'description' => '',
            'payloadjson' => '{}',
            'parentitemid' => 0,
            'active' => 1,
            'sortorder' => 506,
        ]);
        (new master_repository())->save((object)[
            'tenantid' => $tenant->id,
            'acadyearid' => 0,
            'parentid' => 0,
            'mastertype' => 'subject',
            'externalid' => 'SUBJECT_UNLINKED_IMPACT',
            'code' => 'UNLINKED_IMPACT',
            'name' => 'Tenant Customized Subject',
            'description' => '',
            'payloadjson' => '{}',
            'active' => 1,
            'sortorder' => 506,
            'catalogitemid' => 0,
            'catalogversion' => 0,
            'inheritedhash' => null,
        ]);

        $impact = $service->removal_impact((int)$saved->id);

        $this->assertSame(1, $impact->linkedtenants);
        $this->assertSame(1, $impact->customisedtenants);
    }
}
