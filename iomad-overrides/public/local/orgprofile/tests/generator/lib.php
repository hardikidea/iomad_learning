<?php
// This file is part of Moodle - https://moodle.org/

/** Test data generator for local_orgprofile. */
final class local_orgprofile_generator extends component_generator_base {

    /** @var int Generated company counter. */
    private int $companycount = 0;

    /** Create an IOMAD company through the pinned IOMAD API. */
    public function create_company(array $data = []): \local_iomad\company {
        $number = ++$this->companycount;
        $defaults = [
            'name' => 'Organization profile company ' . $number,
            'shortname' => 'orgprofile' . $number,
            'city' => 'Test city',
            'country' => 'GB',
            'theme' => 'boost',
        ];
        return \local_iomad\company::create_company((object) array_merge($defaults, $data));
    }

    /** Associate an existing Moodle user with an exact company/department using IOMAD. */
    public function add_company_user(int $userid, int $companyid, int $managertype = 0): void {
        $department = \local_iomad\company::get_company_parentnode($companyid);
        \local_iomad\company::upsert_company_user(
            $userid,
            $companyid,
            (int) $department->id,
            $managertype
        );
    }

    /** Create the School/Student baseline used by tests and documentation examples. */
    public function create_school_definition(): array {
        $organizations = new \local_orgprofile\local\service\organization_service();
        $forms = new \local_orgprofile\local\service\form_service();
        $orgtypeid = $organizations->save_organization_type((object) [
            'name' => 'School', 'shortname' => 'school', 'description' => '', 'enabled' => 1, 'sortorder' => 10,
        ]);
        $usertypeid = $organizations->save_user_type((object) [
            'orgtypeid' => $orgtypeid, 'name' => 'Student', 'shortname' => 'student', 'enabled' => 1, 'sortorder' => 10,
        ]);
        $formid = $forms->save_form((object) [
            'orgtypeid' => $orgtypeid, 'usertypeid' => $usertypeid, 'name' => 'School Student Profile',
            'shortname' => 'school_student', 'description' => '', 'enabled' => 1,
        ]);
        $categoryid = $forms->save_category((object) [
            'formid' => $formid, 'name' => 'Identity', 'shortname' => 'identity', 'sortorder' => 10, 'collapsed' => 0,
        ]);
        return compact('orgtypeid', 'usertypeid', 'formid', 'categoryid');
    }
}
