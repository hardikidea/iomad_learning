<?php
// This file is part of IOMAD - http://www.iomad.org/

namespace local_institutionpack;

defined('MOODLE_INTERNAL') || die();

use block_iomad_company_admin_external;
use context_system;
use context_user;
use core\context\coursecat as context_coursecat;
use core_course_category;
use local_iomad\company;
use local_iomad\company_user;
use local_iomad\custom_context\context_company;
use stdClass;
use tool_iomadpolicy\api as policy_api;
use tool_iomadpolicy\iomadpolicy_version;

class importer {
    private pack $pack;
    private array $summary = [];

    public function __construct(pack $pack) {
        global $CFG;

        $this->pack = $pack;
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/cohort/lib.php');
        require_once($CFG->dirroot . '/group/lib.php');
        require_once($CFG->dirroot . '/user/lib.php');
    }

    public function doctor(): array {
        global $CFG, $DB;

        return [
            'ok' => class_exists(company::class)
                && class_exists(company_user::class)
                && class_exists(context_company::class)
                && function_exists('create_course'),
            'dirroot' => $CFG->dirroot,
            'wwwroot' => $CFG->wwwroot,
            'dbtype' => $CFG->dbtype,
            'iomad_company_api' => class_exists(company::class),
            'iomad_company_user_api' => class_exists(company_user::class),
            'companies_table_exists' => $DB->get_manager()->table_exists('local_iomad_companies'),
            'pack_id' => $this->pack->id(),
            'checksums' => $this->pack->checksums(),
        ];
    }

    public function apply(bool $dryrun = false): array {
        global $DB;

        $validation = (new validator($this->pack))->validate();
        if (!$validation['ok']) {
            return ['ok' => false, 'validation' => $validation];
        }
        if ($dryrun) {
            return ['ok' => true, 'dryrun' => true, 'plan' => (new planner($this->pack))->plan()];
        }

        $this->set_admin_user();
        $this->summary = [];

        $transaction = $DB->start_delegated_transaction();
        try {
            $this->ensure_custom_roles();
            $this->apply_companies();
            $this->apply_departments();
            $this->apply_categories();
            $this->apply_courses();
            $this->apply_users();
            $this->apply_role_assignments();
            $this->apply_cohorts();
            $this->apply_groups();
            $this->apply_enrolments();
            $this->apply_parent_links();
            $this->apply_policies();
            $this->apply_licenses();
            $transaction->allow_commit();
        } catch (\Throwable $exception) {
            $transaction->rollback($exception);
        }

        $report = [
            'ok' => true,
            'pack_id' => $this->pack->id(),
            'schema_version' => $this->pack->schema_version(),
            'checksums' => $this->pack->checksums(),
            'summary' => $this->summary,
            'report_path' => null,
        ];
        $report['report_path'] = $this->write_report($report);
        return $report;
    }

    public static function latest_report(): ?array {
        global $CFG;

        $dir = $CFG->dataroot . '/local_institutionpack/reports';
        if (!is_dir($dir)) {
            return null;
        }
        $files = glob($dir . '/*.json') ?: [];
        rsort($files);
        if (empty($files)) {
            return null;
        }
        $data = json_decode(file_get_contents($files[0]), true);
        return is_array($data) ? $data : null;
    }

    private function set_admin_user(): void {
        global $USER;
        $USER = get_admin();
    }

    private function bump(string $entity, string $action): void {
        $this->summary[$entity][$action] = ($this->summary[$entity][$action] ?? 0) + 1;
    }

    private function apply_companies(): void {
        global $DB;

        $domains = [];
        foreach ($this->pack->rows('domains') as $row) {
            $domains[$row['company_shortname']][] = $row['domain'];
        }
        $branding = [];
        foreach ($this->pack->rows('branding') as $row) {
            $branding[$row['company_shortname']] = $row;
        }

        foreach ($this->pack->rows('companies') as $row) {
            $shortname = $row['company_shortname'];
            $record = $DB->get_record('local_iomad_companies', ['shortname' => $shortname]);

            $data = (object)[
                'name' => $row['name'],
                'shortname' => $shortname,
                'city' => $row['city'],
                'country' => $row['country'],
                'theme' => $row['theme'] ?: (getenv('IOMAD_THEME') ?: 'iomad_learning'),
                'hostname' => $row['hostname'] ?? '',
                'parentid' => $this->company_id($row['parent_company_shortname'] ?? '') ?: 0,
                'companydomains' => implode("\n", $domains[$shortname] ?? []),
                'code' => $row['institution_id'] ?? '',
            ];

            foreach (['maincolor', 'headingcolor', 'linkcolor'] as $field) {
                $data->$field = $branding[$shortname][$field] ?? ($row[$field] ?? '');
            }
            if (!empty($branding[$shortname]['customcss'])) {
                $data->customcss = $branding[$shortname]['customcss'];
            }

            if ($record) {
                $data->id = $record->id;
                $this->bump('companies', 'update');
            } else {
                $this->bump('companies', 'create');
            }
            company::create_company($data);
        }
    }

    private function apply_departments(): void {
        global $DB;

        foreach ($this->pack->rows('departments') as $row) {
            $companyid = $this->company_id($row['company_shortname']);
            if (!$companyid) {
                continue;
            }
            $existing = $DB->get_record('local_iomad_company_departments', [
                'companyid' => $companyid,
                'shortname' => $row['department_shortname'],
            ]);
            $parentid = $this->department_id($companyid, $row['parent_department_shortname'] ?? '');
            if (!$parentid) {
                $parentid = company::get_company_parentnode($companyid)->id;
            }
            company::create_department((int)($existing->id ?? 0), $companyid, $row['name'], $row['department_shortname'], $parentid);
            $this->bump('departments', $existing ? 'update' : 'create');
        }
    }

    private function apply_categories(): void {
        global $DB;

        foreach ($this->pack->rows('categories') as $row) {
            $idnumber = $row['category_idnumber'];
            $existingid = $DB->get_field('course_categories', 'id', ['idnumber' => $idnumber]);
            $parentid = $this->category_id($row['parent_idnumber'] ?? '');
            if (!$parentid && !empty($row['company_shortname']) && ($company = $this->company_record($row['company_shortname']))) {
                $parentid = (int)$company->coursecategoryid;
            }
            $data = [
                'name' => $row['name'],
                'idnumber' => $idnumber,
                'parent' => $parentid ?: 0,
                'visible' => 1,
            ];
            if ($existingid) {
                core_course_category::get($existingid)->update($data);
                $this->bump('categories', 'update');
            } else {
                core_course_category::create((object)$data);
                $this->bump('categories', 'create');
            }
        }
    }

    private function apply_courses(): void {
        global $DB;

        foreach ($this->pack->rows('courses') as $row) {
            $categoryid = $this->category_id($row['category_idnumber']);
            if (!$categoryid) {
                continue;
            }
            $existing = $DB->get_record('course', ['shortname' => $row['course_shortname']]);
            $course = (object)[
                'fullname' => $row['fullname'],
                'shortname' => $row['course_shortname'],
                'category' => $categoryid,
                'summary' => $row['summary'] ?? '',
                'summaryformat' => FORMAT_HTML,
                'format' => $row['format'] ?: 'topics',
                'visible' => ($row['visible'] ?? '1') === '0' ? 0 : 1,
                'startdate' => time(),
            ];
            if ($existing) {
                $course->id = $existing->id;
                update_course($course);
                $courseid = $existing->id;
                $this->bump('courses', 'update');
            } else {
                $created = create_course($course);
                $courseid = $created->id;
                $this->bump('courses', 'create');
            }

            $companyid = $this->company_id($row['company_shortname']);
            if ($companyid) {
                $company = new company($companyid);
                $departmentid = $this->department_id($companyid, $row['department_shortname'] ?? '');
                $company->add_course((object)['id' => $courseid], $departmentid ?: 0, true, false);
            }
        }
    }

    private function apply_users(): void {
        global $DB;

        foreach ($this->pack->rows('users') as $row) {
            $companyid = $this->company_id($row['company_shortname']);
            if (!$companyid) {
                continue;
            }
            $userid = $this->user_id($row['user_external_id'], $row['username']);
            $departmentid = $this->department_id($companyid, $row['department_shortname'] ?? '');
            $rolekey = $row['role_key'];
            $password = $this->password_for($row);

            $user = (object)[
                'username' => $row['username'],
                'firstname' => $row['firstname'],
                'lastname' => $row['lastname'],
                'email' => $row['email'],
                'idnumber' => $row['user_external_id'],
                'auth' => 'manual',
                'companyid' => $companyid,
                'departmentid' => $departmentid ?: company::get_company_parentnode($companyid)->id,
                'managertype' => $this->manager_type($rolekey),
                'educator' => in_array($rolekey, ['teacher_faculty'], true) ? 1 : 0,
                'sendnewpasswordemails' => 0,
                'preference_auth_forcepasswordchange' => 0,
                'use_email_as_username' => 0,
            ];
            if ($password !== '') {
                $user->newpassword = $password;
            }

            if ($userid) {
                $update = clone($user);
                $update->id = $userid;
                user_update_user($update, false, false);
                $this->bump('users', 'update');
            } else {
                $this->bump('users', 'create');
            }
            company_user::create($user, $companyid);
        }
    }

    private function apply_role_assignments(): void {
        foreach ($this->pack->rows('users') as $row) {
            $userid = $this->user_id($row['user_external_id'], $row['username']);
            $companyid = $this->company_id($row['company_shortname']);
            if (!$userid || !$companyid) {
                continue;
            }
            $shortname = $this->company_role_for($row['role_key']);
            if ($shortname === '') {
                continue;
            }
            $roleid = $this->role_id($shortname);
            if ($roleid) {
                role_assign($roleid, $userid, context_company::instance($companyid)->id);
                $this->bump('role_assignments', 'assign');
            }
        }
    }

    private function apply_cohorts(): void {
        global $DB;

        foreach ($this->pack->rows('cohorts') as $row) {
            $existing = $DB->get_record('cohort', ['idnumber' => $row['cohort_idnumber']]);
            $cohort = (object)[
                'contextid' => context_system::instance()->id,
                'name' => $row['name'],
                'idnumber' => $row['cohort_idnumber'],
                'description' => $row['description'] ?? '',
                'descriptionformat' => FORMAT_HTML,
                'visible' => 1,
            ];
            if ($existing) {
                $cohort->id = $existing->id;
                cohort_update_cohort($cohort);
                $this->bump('cohorts', 'update');
            } else {
                cohort_add_cohort($cohort);
                $this->bump('cohorts', 'create');
            }
        }
    }

    private function apply_groups(): void {
        global $DB;

        foreach ($this->pack->rows('groups') as $row) {
            $courseid = $this->course_id($row['course_shortname']);
            if (!$courseid) {
                continue;
            }
            $existing = $DB->get_record('groups', ['courseid' => $courseid, 'idnumber' => $row['group_idnumber']]);
            $group = (object)[
                'courseid' => $courseid,
                'idnumber' => $row['group_idnumber'],
                'name' => $row['name'],
                'description' => $row['description'] ?? '',
                'descriptionformat' => FORMAT_HTML,
            ];
            if ($existing) {
                $group->id = $existing->id;
                groups_update_group($group);
                $this->bump('groups', 'update');
            } else {
                groups_create_group($group);
                $this->bump('groups', 'create');
            }
        }
    }

    private function apply_enrolments(): void {
        global $DB;

        foreach ($this->pack->rows('enrolments') as $row) {
            $userid = $this->user_id($row['user_external_id']);
            $courseid = $this->course_id($row['course_shortname']);
            $companyid = $this->company_id($row['company_shortname']);
            $roleid = $this->role_id($row['role_shortname']);
            if (!$userid || !$courseid || !$companyid || !$roleid) {
                continue;
            }
            $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
            company_user::enrol($user, [$courseid], $companyid, $roleid);
            if (!empty($row['group_idnumber']) && ($groupid = $this->group_id($courseid, $row['group_idnumber']))) {
                groups_add_member($groupid, $userid);
            }
            $this->bump('enrolments', 'apply');
        }
    }

    private function apply_parent_links(): void {
        foreach ($this->pack->rows('parent_links') as $row) {
            $parentid = $this->user_id($row['parent_user_external_id']);
            $learnerid = $this->user_id($row['learner_user_external_id']);
            $roleid = $this->role_id('parentguardian');
            if ($parentid && $learnerid && $roleid) {
                role_assign($roleid, $parentid, context_user::instance($learnerid)->id);
                $this->bump('parent_links', 'assign');
            }
        }
    }

    private function apply_policies(): void {
        global $DB;

        if (!class_exists(policy_api::class)) {
            $this->bump('policies', 'skipped');
            return;
        }

        foreach ($this->pack->rows('policies') as $row) {
            $companyid = $this->company_id($row['company_shortname']);
            if (!$companyid) {
                continue;
            }
            $existing = $DB->get_record_sql(
                "SELECT d.id
                   FROM {tool_iomadpolicy} d
                   JOIN {tool_iomadpolicy_versions} v ON v.iomadpolicyid = d.id
                  WHERE d.companyid = :companyid AND " . $DB->sql_compare_text('v.name') . " = :name",
                ['companyid' => $companyid, 'name' => $row['name']]
            );
            if ($existing) {
                $this->bump('policies', 'exists');
                continue;
            }

            $form = new stdClass();
            $form->companyid = $companyid;
            $form->name = $row['name'];
            $form->revision = $row['revision'] ?: date('Ymd');
            $form->type = iomadpolicy_version::TYPE_SITE;
            $form->audience = $this->policy_audience($row['audience']);
            $form->agreementstyle = iomadpolicy_version::AGREEMENTSTYLE_CONSENTPAGE;
            $form->optional = iomadpolicy_version::AGREEMENT_COMPULSORY;
            $form->summary_editor = ['text' => $row['summary'] ?? $row['name'], 'format' => FORMAT_HTML, 'itemid' => 0];
            $form->content_editor = ['text' => $row['content'], 'format' => FORMAT_HTML, 'itemid' => 0];
            $version = policy_api::form_iomadpolicydoc_add($form);
            policy_api::make_current($version->get('id'));
            $this->bump('policies', 'create');
        }
    }

    private function apply_licenses(): void {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/blocks/iomad_company_admin/externallib.php');

        foreach ($this->pack->rows('licenses') as $row) {
            $companyid = $this->company_id($row['company_shortname']);
            if (!$companyid) {
                continue;
            }
            if ($DB->record_exists('local_iomad_company_licenses', ['companyid' => $companyid, 'reference' => $row['license_key']])) {
                $this->bump('licenses', 'exists');
                continue;
            }
            $courses = [];
            foreach (preg_split('/[|;]/', $row['course_shortnames']) as $shortname) {
                $shortname = trim($shortname);
                if ($shortname !== '' && ($courseid = $this->course_id($shortname))) {
                    $courses[] = ['courseid' => $courseid];
                }
            }
            if (empty($courses)) {
                $this->bump('licenses', 'skipped');
                continue;
            }
            $license = [
                'name' => $row['name'],
                'allocation' => (int)$row['allocation'],
                'validlength' => (int)($row['validlength'] ?: 0),
                'startdate' => $this->timestamp($row['start_date'] ?? '') ?: time(),
                'expirydate' => $this->timestamp($row['expiry_date'] ?? '') ?: strtotime('+1 year'),
                'used' => 0,
                'companyid' => $companyid,
                'parentid' => 0,
                'type' => (int)($row['type'] ?: 0),
                'program' => (int)($row['program'] ?: 0),
                'reference' => $row['license_key'],
                'instant' => (int)($row['instant'] ?: 0),
                'clearonexpire' => (int)($row['clearonexpire'] ?: 0),
                'cutoffdate' => 0,
                'courses' => $courses,
            ];
            block_iomad_company_admin_external::create_licenses([$license]);
            $this->bump('licenses', 'create');
        }
    }

    private function ensure_custom_roles(): void {
        $itrole = $this->role_id('institutionitcoordinator');
        if (!$itrole) {
            $itrole = create_role('IT coordinator', 'institutionitcoordinator', 'Scoped IOMAD company administration without site administrator access.');
            set_role_contextlevels($itrole, [CONTEXT_COMPANY]);
        }
        foreach (
            [
            'block/iomad_company_admin:company_view_all',
            'block/iomad_company_admin:company_user_create',
            'block/iomad_company_admin:company_user_update',
            'block/iomad_company_admin:company_user_upload',
            ] as $capability
        ) {
            if (get_capability_info($capability)) {
                assign_capability($capability, CAP_ALLOW, $itrole, context_system::instance(), true);
            }
        }

        $parentrole = $this->role_id('parentguardian');
        if (!$parentrole) {
            $parentrole = create_role('Parent/Guardian mentor', 'parentguardian', 'Explicit learner mentor relationship for parent and guardian access.');
            set_role_contextlevels($parentrole, [CONTEXT_USER]);
        }
        foreach (['moodle/user:viewdetails', 'moodle/user:readuserposts'] as $capability) {
            if (get_capability_info($capability)) {
                assign_capability($capability, CAP_ALLOW, $parentrole, context_system::instance(), true);
            }
        }
    }

    private function write_report(array $report): string {
        global $CFG;

        $dir = $CFG->dataroot . '/local_institutionpack/reports';
        make_writable_directory($dir);
        $name = date('Ymd-His') . '-' . clean_param($this->pack->id(), PARAM_FILE) . '.json';
        $path = $dir . '/' . $name;
        file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $path;
    }

    private function company_id(string $shortname): int {
        $record = $this->company_record($shortname);
        return $record ? (int)$record->id : 0;
    }

    private function company_record(string $shortname): ?stdClass {
        global $DB;
        if ($shortname === '') {
            return null;
        }
        $record = $DB->get_record('local_iomad_companies', ['shortname' => $shortname]);
        return $record ?: null;
    }

    private function department_id(int $companyid, string $shortname): int {
        global $DB;
        if ($shortname === '') {
            return 0;
        }
        return (int)$DB->get_field('local_iomad_company_departments', 'id', [
            'companyid' => $companyid,
            'shortname' => $shortname,
        ]);
    }

    private function category_id(string $idnumber): int {
        global $DB;
        if ($idnumber === '') {
            return 0;
        }
        return (int)$DB->get_field('course_categories', 'id', ['idnumber' => $idnumber]);
    }

    private function course_id(string $shortname): int {
        global $DB;
        if ($shortname === '') {
            return 0;
        }
        return (int)$DB->get_field('course', 'id', ['shortname' => $shortname]);
    }

    private function user_id(string $externalid, string $username = ''): int {
        global $DB;
        if ($externalid !== '') {
            $id = (int)$DB->get_field('user', 'id', ['idnumber' => $externalid, 'deleted' => 0]);
            if ($id) {
                return $id;
            }
        }
        if ($username !== '') {
            return (int)$DB->get_field('user', 'id', ['username' => $username, 'deleted' => 0]);
        }
        return 0;
    }

    private function role_id(string $shortname): int {
        global $DB;
        if ($shortname === '') {
            return 0;
        }
        return (int)$DB->get_field('role', 'id', ['shortname' => $shortname]);
    }

    private function group_id(int $courseid, string $idnumber): int {
        global $DB;
        return (int)$DB->get_field('groups', 'id', ['courseid' => $courseid, 'idnumber' => $idnumber]);
    }

    private function password_for(array $row): string {
        $password = $row['password'] ?? '';
        if ($password !== '') {
            return $password;
        }
        if ((getenv('INSTITUTIONPACK_ALLOW_DEMO_PASSWORDS') ?: 'false') === 'true') {
            return getenv('INSTITUTIONPACK_DEFAULT_PASSWORD') ?: 'DemoOnly2026!';
        }
        return '';
    }

    private function manager_type(string $rolekey): int {
        return match ($rolekey) {
            'principal_registrar', 'trustee_management' => 1,
            'hod_dean' => 2,
            default => 0,
        };
    }

    private function company_role_for(string $rolekey): string {
        return match ($rolekey) {
            'principal_registrar' => 'companymanager',
            'trustee_management' => 'companyreporter',
            'it_coordinator' => 'institutionitcoordinator',
            'hod_dean' => 'companydepartmentmanager',
            default => '',
        };
    }

    private function policy_audience(string $audience): int {
        return match ($audience) {
            'guest' => iomadpolicy_version::AUDIENCE_GUESTS,
            'authenticated', 'loggedin' => iomadpolicy_version::AUDIENCE_LOGGEDIN,
            default => iomadpolicy_version::AUDIENCE_ALL,
        };
    }

    private function timestamp(string $date): int {
        if ($date === '') {
            return 0;
        }
        $timestamp = strtotime($date);
        return $timestamp === false ? 0 : $timestamp;
    }
}
