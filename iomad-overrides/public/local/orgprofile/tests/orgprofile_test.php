<?php
// This file is part of Moodle - https://moodle.org/

namespace local_orgprofile;

use context_user;
use core_privacy\local\request\approved_contextlist;
use local_orgprofile\local\service\authorization_service;
use local_orgprofile\local\service\configuration_import_service;
use local_orgprofile\local\service\form_service;
use local_orgprofile\local\service\organization_service;
use local_orgprofile\local\service\profile_service;
use local_orgprofile\local\service\provisioning_service;
use local_orgprofile\local\service\validation_service;
use local_orgprofile\local\ui\listing;
use local_orgprofile\privacy\provider;

/** Service, tenancy, authorization, and privacy tests. */
final class orgprofile_test extends \advanced_testcase {

    /** Listing state accepts only allow-listed sorting and supported page sizes. */
    public function test_listing_state_normalizes_untrusted_controls(): void {
        $listing = new listing(
            ['name' => 'name', 'enabled' => 'enabled'],
            'name',
            '<b>School</b>',
            -5,
            999,
            'name DESC; DELETE',
            'sideways'
        );
        $this->assertSame('School', $listing->query());
        $this->assertSame(0, $listing->page());
        $this->assertSame(20, $listing->perpage());
        $this->assertSame('name ASC', $listing->order_by());
    }

    /** Valid listing controls produce deterministic offsets and trusted ordering. */
    public function test_listing_state_keeps_valid_controls(): void {
        $listing = new listing(
            ['name' => 'name', 'enabled' => 'enabled'],
            'name',
            'Hospital',
            2,
            50,
            'enabled',
            'desc'
        );
        $this->assertSame(100, $listing->offset());
        $this->assertSame('enabled DESC', $listing->order_by());
        $this->assertSame('Hospital', $listing->url_params()['q']);
    }

    /** Maintained CSV validates and resolves every reusable configuration record. */
    public function test_maintained_configuration_dry_run(): void {
        $this->resetAfterTest();
        $summary = (new configuration_import_service())->import(
            __DIR__ . '/../data/organization_profiles_master.csv'
        );
        $this->assertSame('dry-run', $summary['mode']);
        $this->assertSame(10, $summary['organizations']);
        $this->assertSame(45, $summary['usertypes']);
        $this->assertSame(45, $summary['forms']);
        $this->assertSame(178, $summary['fields']);
        $this->assertSame(1399, $summary['placements']);
        $this->assertSame(8, $summary['ownershiprules']);
    }

    /** Organization type CRUD through the service layer. */
    public function test_organization_type_crud_service_logic(): void {
        global $DB;
        $this->resetAfterTest();
        $service = new organization_service();
        $id = $service->save_organization_type((object) [
            'name' => 'School', 'shortname' => 'school', 'description' => '', 'enabled' => 1, 'sortorder' => 1,
        ]);
        $service->save_organization_type((object) [
            'id' => $id, 'name' => 'K-12 School', 'shortname' => 'school', 'description' => '',
            'enabled' => 1, 'sortorder' => 2,
        ]);
        $this->assertEquals('K-12 School', $DB->get_field('local_orgprofile_orgtype', 'name', ['id' => $id]));
        $service->delete('orgtype', $id);
        $this->assertFalse($DB->record_exists('local_orgprofile_orgtype', ['id' => $id]));
    }

    /** User type and explicit form resolution stay within one organization type. */
    public function test_user_type_and_form_resolution(): void {
        [$company, $user, $definition] = $this->create_assigned_profile();
        $resolved = (new form_service())->resolve_form($company->id, $user->id);
        $this->assertEquals($definition['formid'], $resolved->id);
        $this->assertEquals($definition['usertypeid'],
            $this->get_assignment($user->id, $company->id)->usertypeid);
    }

    /** Company mapping persists the verified IOMAD company identifier. */
    public function test_company_to_organization_type_mapping(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->plugin_generator();
        $company = $generator->create_company();
        $definition = $generator->create_school_definition();
        $id = (new organization_service())->map_company($company->id, $definition['orgtypeid'], $definition['formid']);
        $mapping = $DB->get_record('local_orgprofile_company', ['id' => $id], '*', MUST_EXIST);
        $this->assertEquals($company->id, $mapping->companyid);
        $this->assertEquals($definition['orgtypeid'], $mapping->orgtypeid);
    }

    /** A company organization type cannot be reinterpreted after initial mapping. */
    public function test_company_organization_type_is_immutable(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $company = $this->plugin_generator()->create_company();
        $organizations = new organization_service();
        $schoolid = $organizations->save_organization_type((object) [
            'name' => 'School', 'shortname' => 'school', 'description' => '', 'enabled' => 1, 'sortorder' => 10,
        ]);
        $corporateid = $organizations->save_organization_type((object) [
            'name' => 'Corporate', 'shortname' => 'corporate', 'description' => '', 'enabled' => 1, 'sortorder' => 20,
        ]);
        $organizations->map_company($company->id, $schoolid);
        $this->expectException(\invalid_parameter_exception::class);
        $organizations->map_company($company->id, $corporateid);
    }

    /** Company and user type resolve a form before a Moodle user record exists. */
    public function test_creation_form_resolution_from_company_and_user_type(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $company = $this->plugin_generator()->create_company();
        $definition = $this->plugin_generator()->create_school_definition();
        (new organization_service())->map_company($company->id, $definition['orgtypeid']);
        $form = (new form_service())->resolve_form_for_user_type($company->id, $definition['usertypeid']);
        $this->assertEquals($definition['formid'], $form->id);
    }

    /** Guided company creation persists the required mapping in the same workflow. */
    public function test_profiled_company_creation_workflow(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $orgtypeid = (new organization_service())->save_organization_type((object) [
            'name' => 'Hospital', 'shortname' => 'hospital', 'description' => '', 'enabled' => 1, 'sortorder' => 10,
        ]);
        $company = (new provisioning_service())->create_company((object) [
            'orgtypeid' => $orgtypeid,
            'name' => 'Test Hospital',
            'shortname' => 'test_hospital',
            'code' => 'TEST-HOSPITAL',
            'address' => '',
            'city' => 'Pune',
            'region' => 'Maharashtra',
            'postcode' => '411001',
            'country' => 'IN',
            'maxusers' => 0,
        ]);
        $this->assertEquals($orgtypeid, $DB->get_field('local_orgprofile_company', 'orgtypeid', [
            'companyid' => $company->id,
        ]));
    }

    /** Guided creation stores the account, exact membership, assignment, and validated value. */
    public function test_profiled_company_user_creation_workflow(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $company = $this->plugin_generator()->create_company();
        $definition = $this->plugin_generator()->create_school_definition();
        (new organization_service())->map_company($company->id, $definition['orgtypeid']);
        $fieldid = $this->create_field('Admission Number', 'admission_number', 'text', [
            'required' => 1,
            'uniquescope' => 'company',
            'validationjson' => '{"minlength":3,"maxlength":20}',
        ]);
        $this->attach_field($definition['formid'], $definition['categoryid'], $fieldid);
        $submitted = [
            'core_firstname' => 'Asha',
            'core_lastname' => 'Patel',
            'core_email' => 'asha.patel@example.invalid',
            'username' => 'asha.patel',
            'use_email_as_username' => 0,
            'newpassword' => 'Test-Password-42!',
            'preference_auth_forcepasswordchange' => 1,
            'sendnewpasswordemails' => 0,
            'field_' . $fieldid => 'ADM-100',
        ];
        $service = new provisioning_service();
        $this->assertSame([], $service->validate_company_user(
            $company->id,
            $definition['usertypeid'],
            $submitted
        ));
        $userid = $service->create_company_user($company->id, $definition['usertypeid'], $submitted);
        $this->assertTrue($DB->record_exists('local_iomad_company_users', [
            'userid' => $userid,
            'companyid' => $company->id,
        ]));
        $this->assertTrue($DB->record_exists('local_orgprofile_user', [
            'userid' => $userid,
            'companyid' => $company->id,
            'usertypeid' => $definition['usertypeid'],
        ]));
        $this->assertEquals('ADM-100', $DB->get_field('local_orgprofile_value', 'value', [
            'userid' => $userid,
            'companyid' => $company->id,
            'fieldid' => $fieldid,
        ]));
    }

    /** Default mapping resolves when no explicit user form is assigned. */
    public function test_default_form_resolution(): void {
        [$company, $user, $definition] = $this->create_assigned_profile(false);
        $this->assertEquals($definition['formid'], (new form_service())->resolve_form($company->id, $user->id)->id);
    }

    /** Categories and fields use stable sortorder/id ordering. */
    public function test_category_and_field_ordering(): void {
        [$company, $user, $definition] = $this->create_assigned_profile();
        $forms = new form_service();
        $secondcategory = $forms->save_category((object) [
            'formid' => $definition['formid'], 'name' => 'Later', 'shortname' => 'later',
            'sortorder' => 20, 'collapsed' => 0,
        ]);
        $latefield = $this->create_field('Late', 'late', 'text');
        $earlyfield = $this->create_field('Early', 'early', 'text');
        $this->attach_field($definition['formid'], $definition['categoryid'], $latefield, 20);
        $this->attach_field($definition['formid'], $definition['categoryid'], $earlyfield, 10);
        $another = $this->create_field('Another', 'another', 'text');
        $this->attach_field($definition['formid'], $secondcategory, $another, 10);
        $structure = $forms->get_form_structure($definition['formid']);
        $this->assertEquals(['Identity', 'Later'], array_column($structure, 'name'));
        $this->assertEquals(['Early', 'Late'], array_column($structure[0]->fields, 'name'));
    }

    /** Required rules are enforced server-side. */
    public function test_required_field_validation(): void {
        [$company, $user, $definition] = $this->create_assigned_profile();
        $fieldid = $this->create_field('Admission Number', 'admission_number', 'text', ['required' => 1]);
        $this->attach_field($definition['formid'], $definition['categoryid'], $fieldid);
        $errors = (new profile_service())->validate_submission($user->id, $company->id, ['field_' . $fieldid => '']);
        $this->assertArrayHasKey('field_' . $fieldid, $errors);
    }

    /** Menu submissions must match configured JSON choices. */
    public function test_menu_option_validation(): void {
        $this->resetAfterTest();
        $field = (object) ['datatype' => 'menu', 'optionsjson' => '["Student","Teacher"]'];
        $validator = new validation_service();
        $this->assertEquals('Student', $validator->normalize_value($field, 'Student'));
        $this->expectException(\invalid_parameter_exception::class);
        $validator->normalize_value($field, 'Administrator');
    }

    /** A core Country mapping uses Moodle country codes rather than administrator menu options. */
    public function test_core_country_mapping_uses_moodle_country_list(): void {
        $field = (object) ['datatype' => 'menu', 'corefield' => 'country', 'optionsjson' => ''];
        $validator = new validation_service();

        $this->assertSame('IN', $validator->normalize_value($field, 'IN'));
    }

    /** Optional and configured date values use the integer type required by Moodle date controls. */
    public function test_date_values_are_prepared_for_moodle_form_controls(): void {
        $validator = new validation_service();
        $datefield = (object) ['datatype' => 'date'];
        $datetimefield = (object) ['datatype' => 'datetime'];

        $this->assertSame(0, $validator->form_value($datefield, ''));
        $this->assertSame(0, $validator->form_value($datetimefield, null));
        $this->assertIsInt($validator->form_value($datefield, '2026-08-09'));
        $this->assertSame(1786233600, $validator->form_value($datetimefield, '1786233600'));
    }

    /** Identifiers configured as company unique cannot collide inside a company. */
    public function test_company_scoped_uniqueness(): void {
        [$company, $firstuser, $definition] = $this->create_assigned_profile();
        $fieldid = $this->create_field('Admission Number', 'admission_number', 'text', ['uniquescope' => 'company']);
        $this->attach_field($definition['formid'], $definition['categoryid'], $fieldid);
        $service = new profile_service();
        $service->save_profile($firstuser->id, $company->id, ['field_' . $fieldid => 'A-100']);
        $seconduser = $this->getDataGenerator()->create_user();
        $this->plugin_generator()->add_company_user($seconduser->id, $company->id);
        $service->assign_user_type($seconduser->id, $company->id, $definition['usertypeid'], $definition['formid']);
        $errors = $service->validate_submission($seconduser->id, $company->id, ['field_' . $fieldid => 'a-100']);
        $this->assertArrayHasKey('field_' . $fieldid, $errors);
    }

    /** Site unique identifiers cannot collide in different companies. */
    public function test_site_scoped_uniqueness(): void {
        [$company, $firstuser, $definition] = $this->create_assigned_profile();
        $fieldid = $this->create_field('External Identifier', 'external_identifier', 'text', ['uniquescope' => 'site']);
        $this->attach_field($definition['formid'], $definition['categoryid'], $fieldid);
        $service = new profile_service();
        $service->save_profile($firstuser->id, $company->id, ['field_' . $fieldid => 'GLOBAL-1']);
        $secondcompany = $this->plugin_generator()->create_company();
        (new organization_service())->map_company($secondcompany->id, $definition['orgtypeid'], $definition['formid']);
        $seconduser = $this->getDataGenerator()->create_user();
        $this->plugin_generator()->add_company_user($seconduser->id, $secondcompany->id);
        $service->assign_user_type($seconduser->id, $secondcompany->id, $definition['usertypeid'], $definition['formid']);
        $errors = $service->validate_submission($seconduser->id, $secondcompany->id,
            ['field_' . $fieldid => 'global-1']);
        $this->assertArrayHasKey('field_' . $fieldid, $errors);
    }

    /** Custom values are stored under user, company, and field. */
    public function test_user_company_scoped_value_storage(): void {
        global $DB;
        [$company, $user, $definition] = $this->create_assigned_profile();
        $fieldid = $this->create_field('GR Number', 'gr_number', 'text');
        $this->attach_field($definition['formid'], $definition['categoryid'], $fieldid);
        (new profile_service())->save_profile($user->id, $company->id, ['field_' . $fieldid => 'GR-42']);
        $value = $DB->get_record('local_orgprofile_value', [
            'userid' => $user->id, 'companyid' => $company->id, 'fieldid' => $fieldid,
        ], '*', MUST_EXIST);
        $this->assertEquals('GR-42', $value->value);
    }

    /** Selected native fields are updated through Moodle's user API, not duplicated in plugin storage. */
    public function test_native_core_field_update(): void {
        global $DB;
        [$company, $user, $definition] = $this->create_assigned_profile();
        $fieldid = $this->create_field('City', 'core_city', 'text', ['corefield' => 'city']);
        $this->attach_field($definition['formid'], $definition['categoryid'], $fieldid);
        (new profile_service())->save_profile($user->id, $company->id, ['core_city' => 'Pune']);
        $this->assertEquals('Pune', $DB->get_field('user', 'city', ['id' => $user->id]));
        $this->assertFalse($DB->record_exists('local_orgprofile_value', ['fieldid' => $fieldid]));
    }

    /** One Moodle user can hold independent values in multiple companies. */
    public function test_user_belonging_to_multiple_companies(): void {
        global $DB;
        [$firstcompany, $user, $definition] = $this->create_assigned_profile();
        $fieldid = $this->create_field('Local Identifier', 'local_identifier', 'text');
        $this->attach_field($definition['formid'], $definition['categoryid'], $fieldid);
        $secondcompany = $this->plugin_generator()->create_company();
        $this->plugin_generator()->add_company_user($user->id, $secondcompany->id);
        (new organization_service())->map_company($secondcompany->id, $definition['orgtypeid'], $definition['formid']);
        $service = new profile_service();
        $service->assign_user_type($user->id, $secondcompany->id, $definition['usertypeid'], $definition['formid']);
        $service->save_profile($user->id, $firstcompany->id, ['field_' . $fieldid => 'FIRST']);
        $service->save_profile($user->id, $secondcompany->id, ['field_' . $fieldid => 'SECOND']);
        $values = $DB->get_records('local_orgprofile_value', ['userid' => $user->id, 'fieldid' => $fieldid]);
        $this->assertCount(2, $values);
        $this->assertEqualsCanonicalizing(['FIRST', 'SECOND'], array_column($values, 'value'));
    }

    /** A manager/user in another company cannot access the target profile. */
    public function test_unauthorized_cross_company_profile_access(): void {
        [$targetcompany, $target, $definition] = $this->create_assigned_profile();
        $othercompany = $this->plugin_generator()->create_company();
        $outsider = $this->getDataGenerator()->create_user();
        $this->plugin_generator()->add_company_user($outsider->id, $othercompany->id, 1);
        $this->setUser($outsider);
        $authorization = new authorization_service();
        $this->assertFalse($authorization->can_view_profile($target->id, $targetcompany->id));
        $this->expectException(\required_capability_exception::class);
        (new profile_service())->get_profile($target->id, $targetcompany->id);
    }

    /** Sensitive fields are omitted entirely without the dedicated view capability. */
    public function test_sensitive_field_view_denial(): void {
        [$company, $user, $definition] = $this->create_assigned_profile();
        $fieldid = $this->create_field('Medical Information', 'medical_information', 'textarea', ['sensitive' => 1]);
        $this->attach_field($definition['formid'], $definition['categoryid'], $fieldid);
        $this->setUser($user);
        $profile = (new profile_service())->get_profile($user->id, $company->id);
        $this->assertEmpty($profile->categories[0]->fields);
    }

    /** A crafted request cannot overwrite a hidden sensitive value. */
    public function test_sensitive_field_edit_denial(): void {
        global $DB;
        [$company, $user, $definition] = $this->create_assigned_profile();
        $fieldid = $this->create_field('Government Identifier', 'government_identifier', 'text', ['sensitive' => 1]);
        $this->attach_field($definition['formid'], $definition['categoryid'], $fieldid);
        $service = new profile_service();
        $service->save_profile($user->id, $company->id, ['field_' . $fieldid => 'ORIGINAL']);
        set_config('allowownedit', 1, 'local_orgprofile');
        $this->setUser($user);
        $service->save_profile($user->id, $company->id, ['field_' . $fieldid => 'ATTACK']);
        $this->assertEquals('ORIGINAL', $DB->get_field('local_orgprofile_value', 'value', [
            'userid' => $user->id, 'companyid' => $company->id, 'fieldid' => $fieldid,
        ]));
    }

    /** IOMAD company managers receive only their company-scoped profile access. */
    public function test_permitted_company_manager_access(): void {
        [$company, $target, $definition] = $this->create_assigned_profile();
        $manager = $this->getDataGenerator()->create_user();
        $this->plugin_generator()->add_company_user($manager->id, $company->id, 1);
        $this->setUser($manager);
        $authorization = new authorization_service();
        $this->assertTrue($authorization->can_view_profile($target->id, $company->id));
        $this->assertTrue($authorization->can_edit_profile($target->id, $company->id));
    }

    /** A company user can view and, when enabled, edit their own permitted fields. */
    public function test_user_viewing_and_editing_own_permitted_fields(): void {
        [$company, $user, $definition] = $this->create_assigned_profile();
        set_config('allowownedit', 1, 'local_orgprofile');
        $this->setUser($user);
        $authorization = new authorization_service();
        $this->assertTrue($authorization->can_view_profile($user->id, $company->id));
        $this->assertTrue($authorization->can_edit_profile($user->id, $company->id));
    }

    /** Privacy deletion removes assignments and all company-scoped values. */
    public function test_profile_deletion_privacy_behavior(): void {
        global $DB;
        [$company, $user, $definition] = $this->create_assigned_profile();
        $fieldid = $this->create_field('Admission Number', 'admission_number', 'text');
        $this->attach_field($definition['formid'], $definition['categoryid'], $fieldid);
        (new profile_service())->save_profile($user->id, $company->id, ['field_' . $fieldid => 'A-1']);
        $context = context_user::instance($user->id);
        $approved = new approved_contextlist($user, 'local_orgprofile', [$context->id]);
        provider::delete_data_for_user($approved);
        $this->assertFalse($DB->record_exists('local_orgprofile_user', ['userid' => $user->id]));
        $this->assertFalse($DB->record_exists('local_orgprofile_value', ['userid' => $user->id]));
    }

    /** Create a complete mapped and assigned school profile. */
    private function create_assigned_profile(bool $explicitform = true): array {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->plugin_generator();
        $company = $generator->create_company();
        $user = $this->getDataGenerator()->create_user();
        $generator->add_company_user($user->id, $company->id);
        $definition = $generator->create_school_definition();
        (new organization_service())->map_company($company->id, $definition['orgtypeid'], $definition['formid']);
        (new profile_service())->assign_user_type(
            $user->id,
            $company->id,
            $definition['usertypeid'],
            $explicitform ? $definition['formid'] : null
        );
        return [$company, $user, $definition];
    }

    /** Create a reusable field with secure defaults. */
    private function create_field(string $name, string $shortname, string $datatype, array $overrides = []): int {
        $record = array_merge([
            'name' => $name, 'shortname' => $shortname, 'datatype' => $datatype, 'corefield' => '',
            'description' => '', 'defaultvalue' => '', 'required' => 0, 'uniquescope' => 'none',
            'readonly' => 0, 'visible' => 1, 'sensitive' => 0, 'optionsjson' => '',
            'validationjson' => '', 'enabled' => 1,
        ], $overrides);
        return (new form_service())->save_field((object) $record);
    }

    /** Attach a reusable field to the specified form/category. */
    private function attach_field(int $formid, int $categoryid, int $fieldid, int $sortorder = 10): int {
        return (new form_service())->save_form_field((object) [
            'formid' => $formid, 'categoryid' => $categoryid, 'fieldid' => $fieldid,
            'sortorder' => $sortorder, 'requiredoverride' => '', 'readonlyoverride' => '', 'visibleoverride' => '',
        ]);
    }

    /** Fetch a company-scoped assignment. */
    private function get_assignment(int $userid, int $companyid): \stdClass {
        global $DB;
        return $DB->get_record('local_orgprofile_user', compact('userid', 'companyid'), '*', MUST_EXIST);
    }

    /** Return the plugin's test generator. */
    private function plugin_generator(): \local_orgprofile_generator {
        return $this->getDataGenerator()->get_plugin_generator('local_orgprofile');
    }
}
