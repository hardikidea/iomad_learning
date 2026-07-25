<?php
// This file is part of Moodle - http://moodle.org/

use local_tenantmaster\form\department;
use local_tenantmaster\form\academic_year;
use local_tenantmaster\form\access_assignment;
use local_tenantmaster\form\cohort_member;
use local_tenantmaster\form\guardian_link;
use local_tenantmaster\form\import_package;
use local_tenantmaster\form\master;
use local_tenantmaster\form\native_cohort;
use local_tenantmaster\form\native_group;
use local_tenantmaster\form\native_user;
use local_tenantmaster\form\onboarding;
use local_tenantmaster\form\role_assignment;
use local_tenantmaster\form\rollover;
use local_tenantmaster\form\tenant_profile;
use local_tenantmaster\local\access;
use local_tenantmaster\local\academic_year_service;
use local_tenantmaster\local\catalog;
use local_tenantmaster\local\default_service;
use local_tenantmaster\local\drift_service;
use local_tenantmaster\local\import_service;
use local_tenantmaster\local\json;
use local_tenantmaster\local\master_repository;
use local_tenantmaster\local\master_service;
use local_tenantmaster\local\native_user_service;
use local_tenantmaster\local\onboarding_service;
use local_tenantmaster\local\learning_access_service;
use local_tenantmaster\local\organisation_service;
use local_tenantmaster\local\people_service;
use local_tenantmaster\local\queue_service;
use local_tenantmaster\local\role_service;
use local_tenantmaster\local\rollover_service;
use local_tenantmaster\local\tenant_repository;
use local_tenantmaster\local\tenant_service;
use local_tenantmaster\local\validation_service;

require_once(__DIR__ . '/../../config.php');

require_login();

$section = optional_param('section', 'dashboard', PARAM_ALPHA);
$companyid = optional_param('companyid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHANUMEXT);
$editid = optional_param('editid', 0, PARAM_INT);
$typefilter = optional_param('type', '', PARAM_ALPHANUMEXT);
$access = access::resolve($companyid);
$companyid = $access->companyid();
$tenantrepository = new tenant_repository();
$tenant = $companyid > 0 ? $tenantrepository->get_by_company($companyid) : null;
$notice = '';

if ($companyid > 0 && !$tenant) {
    $company = $DB->get_record('local_iomad_companies', ['id' => $companyid], '*', MUST_EXIST);
    $searchname = strtolower($company->name . ' ' . $company->shortname);
    $inferredtype = str_contains($searchname, 'school') ? 'school'
        : (str_contains($searchname, 'university') ? 'university'
            : (str_contains($searchname, 'college') ? 'college' : 'training'));
    $tenant = $tenantrepository->ensure_for_company($companyid, $inferredtype);
    (new role_service())->ensure_defaults((int)$tenant->id);
    (new default_service())->adopt($tenant);
    $tenant = $tenantrepository->get((int)$tenant->id);
    $notice = get_string('tenantactivated', 'local_tenantmaster');
}

$urlparams = ['section' => $section];
if ($companyid > 0) {
    $urlparams['companyid'] = $companyid;
}
$pageurl = new moodle_url('/local/tenantmaster/index.php', $urlparams);
$PAGE->set_url($pageurl);
$PAGE->set_context($access->context());
$PAGE->set_pagelayout('admin');
$sectionstring = tenantmaster_section_string($section);
$PAGE->set_title(get_string($sectionstring, 'local_tenantmaster'));
$PAGE->set_heading($tenant
    ? format_string($DB->get_field('local_iomad_companies', 'name', ['id' => $tenant->companyid]))
    : get_string('pluginname', 'local_tenantmaster'));
$PAGE->navbar->add(get_string('pluginname', 'local_tenantmaster'), new moodle_url('/local/tenantmaster/index.php'));
$PAGE->navbar->add(get_string($sectionstring, 'local_tenantmaster'));

if ($action !== '') {
    require_sesskey();
    if (!$tenant) {
        throw new moodle_exception('selecttenant', 'local_tenantmaster');
    }
    switch ($action) {
        case 'syncall':
            $access->require('local/tenantmaster:sync');
            (new queue_service())->sync_all((int)$tenant->id, 'manual_sync_all');
            $notice = get_string('syncqueued', 'local_tenantmaster');
            break;
        case 'validate':
            $access->require('local/tenantmaster:viewaudit');
            $result = (new validation_service())->validate((int)$tenant->id);
            $notice = get_string('validationcomplete', 'local_tenantmaster', (object)$result);
            break;
        case 'adoptdefaults':
            $access->require('local/tenantmaster:manageacademic');
            $result = (new default_service())->adopt($tenant);
            $notice = get_string('defaultsadopted', 'local_tenantmaster', (object)$result);
            break;
        case 'retry':
            $access->require('local/tenantmaster:sync');
            $dirtyid = required_param('dirtyid', PARAM_INT);
            $dirty = $DB->get_record('local_tenantmaster_dirty', [
                'id' => $dirtyid,
                'tenantid' => $tenant->id,
            ], '*', MUST_EXIST);
            (new queue_service())->mark_dirty(
                (int)$tenant->id,
                (string)$dirty->module,
                (string)$dirty->entitytable,
                (int)$dirty->entityid,
                'manual_retry',
                true,
            );
            $notice = get_string('retryqueued', 'local_tenantmaster');
            break;
        case 'resolvedrift':
            $access->require('local/tenantmaster:resolvedrift');
            (new drift_service())->resolve(
                (int)$tenant->id,
                required_param('driftid', PARAM_INT),
                required_param('resolution', PARAM_ALPHANUMEXT),
            );
            $notice = get_string('driftresolved', 'local_tenantmaster');
            break;
        case 'importapply':
            $access->require('local/tenantmaster:import');
            $result = (new import_service())->apply(
                $tenant,
                required_param('batchid', PARAM_INT),
            );
            $notice = get_string('importcomplete', 'local_tenantmaster', $result);
            break;
    }
}

$onboardingform = null;
if (is_siteadmin() && $companyid === 0 && $section === 'tenants') {
    $parentcompanies = [0 => get_string('none')];
    foreach ($DB->get_records('local_iomad_companies', ['suspended' => 0], 'name') as $parentcompany) {
        $parentcompanies[(int)$parentcompany->id] = format_string($parentcompany->name);
    }
    $onboardingform = new onboarding($pageurl, ['parentcompanies' => $parentcompanies]);
    if ($data = $onboardingform->get_data()) {
        require_sesskey();
        $createdtenant = (new onboarding_service())->create($data);
        redirect(
            new moodle_url('/local/tenantmaster/index.php', [
                'section' => 'dashboard',
                'companyid' => $createdtenant->companyid,
            ]),
            get_string('tenantcreated', 'local_tenantmaster'),
        );
    }
}

$profileform = null;
if ($tenant && $section === 'profile') {
    $profileform = new tenant_profile($pageurl);
    if ($data = $profileform->get_data()) {
        require_sesskey();
        $access->require('local/tenantmaster:manageprofile');
        $record = clone $tenant;
        $record->tenanttype = $data->tenanttype;
        $record->profilejson = json::encode([
            'name' => $data->name,
            'address' => $data->address,
            'city' => $data->city,
            'region' => $data->region,
            'postcode' => $data->postcode,
            'country' => $data->country,
            'hostname' => $data->hostname,
            'maincolor' => $data->maincolor,
            'headingcolor' => $data->headingcolor,
            'linkcolor' => $data->linkcolor,
            'customcss' => $data->customcss,
        ]);
        $tenant = (new tenant_service())->save($record);
        $notice = get_string('profilesaved', 'local_tenantmaster');
    }
    $nativecompany = $DB->get_record('local_iomad_companies', ['id' => $tenant->companyid], '*', MUST_EXIST);
    $profileform->set_data((object)(array_merge(
        (array)$nativecompany,
        json::decode_object($tenant->profilejson),
        [
            'id' => $tenant->id,
            'companyid' => $tenant->companyid,
            'trustcode' => $tenant->trustcode,
            'tenanttype' => $tenant->tenanttype,
        ],
    )));
}

$organisationservice = new organisation_service();
$departmentform = null;
if ($tenant && $section === 'organisation') {
    $departments = $organisationservice->list($tenant);
    $parentoptions = [];
    foreach ($departments as $native) {
        $parentoptions[(int)$native->id] = format_string($native->name);
    }
    $departmentform = new department($pageurl, ['parents' => $parentoptions]);
    if ($data = $departmentform->get_data()) {
        require_sesskey();
        $access->require('local/tenantmaster:manageorganisation');
        $organisationservice->save($tenant, $data);
        redirect($pageurl, get_string('departmentsaved', 'local_tenantmaster'));
    }
    if ($editid > 0) {
        $departmentform->set_data($DB->get_record('local_iomad_company_departments', [
            'id' => $editid,
            'companyid' => $tenant->companyid,
        ], '*', MUST_EXIST));
    }
}

$masterrepository = new master_repository();
$masterform = null;
$academicyearform = null;
if ($tenant && $section === 'academic') {
    $academicyearform = new academic_year($pageurl);
    if ($data = $academicyearform->get_data()) {
        require_sesskey();
        $access->require('local/tenantmaster:manageacademic');
        (new academic_year_service())->save((object)[
            'id' => $data->yearid,
            'tenantid' => $tenant->id,
            'externalid' => $data->yearexternalid,
            'code' => $data->yearcode,
            'name' => $data->yearname,
            'startdate' => $data->yearstartdate,
            'enddate' => $data->yearenddate,
            'iscurrent' => $data->yeariscurrent,
            'status' => 'active',
            'payloadjson' => '{}',
        ]);
        redirect($pageurl, get_string('academicyearsaved', 'local_tenantmaster'));
    }
    $parentoptions = [0 => get_string('none')];
    foreach ($masterrepository->list((int)$tenant->id) as $parent) {
        if ((int)$parent->id !== $editid) {
            $parentoptions[(int)$parent->id] = format_string($parent->name)
                . ' [' . s($parent->mastertype) . ']';
        }
    }
    $masterform = new master($pageurl, ['parents' => $parentoptions]);
    if ($data = $masterform->get_data()) {
        require_sesskey();
        $access->require('local/tenantmaster:manageacademic');
        if ((int)$data->parentid > 0) {
            $masterrepository->get((int)$tenant->id, (int)$data->parentid);
        }
        $data->tenantid = $tenant->id;
        $data->acadyearid = 0;
        (new master_service())->save($data);
        redirect(
            new moodle_url('/local/tenantmaster/index.php', [
                'section' => 'academic',
                'companyid' => $tenant->companyid,
                'type' => $data->mastertype,
            ]),
            get_string('mastersaved', 'local_tenantmaster'),
        );
    }
    if ($editid > 0) {
        $masterform->set_data($masterrepository->get((int)$tenant->id, $editid));
    } else {
        $masterform->set_data((object)[
            'tenantid' => $tenant->id,
            'mastertype' => $typefilter ?: 'grade',
            'payloadjson' => '{}',
            'active' => 1,
        ]);
    }
}

$rolloverform = null;
if ($tenant && $section === 'progression') {
    $years = [];
    foreach ((new academic_year_service())->list((int)$tenant->id) as $year) {
        $years[(int)$year->id] = format_string($year->name);
    }
    $plans = [];
    foreach (
        $DB->get_records('local_tenantmaster_rollover', [
        'tenantid' => $tenant->id,
        'status' => 'planned',
        ], 'timecreated DESC') as $plan
    ) {
        $plans[(int)$plan->id] = '#' . (int)$plan->id . ' '
            . ($years[$plan->fromyearid] ?? $plan->fromyearid)
            . ' -> ' . ($years[$plan->toyearid] ?? $plan->toyearid);
    }
    $rolloverform = new rollover($pageurl, ['years' => $years, 'plans' => $plans]);
    if ($data = $rolloverform->get_data()) {
        require_sesskey();
        if ($data->rolloperation === 'plan') {
            $access->require('local/tenantmaster:manageacademic');
            (new rollover_service())->plan($tenant, (int)$data->fromyearid, (int)$data->toyearid);
            redirect($pageurl, get_string('rolloverplanned', 'local_tenantmaster'));
        }
        require_capability('local/tenantmaster:destructive', context_system::instance());
        (new rollover_service())->apply($tenant, (int)$data->rolloverid, (string)$data->backupref);
        redirect($pageurl, get_string('rolloverapplied', 'local_tenantmaster'));
    }
}

$roleservice = new role_service();
$peopleform = null;
$nativeuserform = null;
$guardianform = null;
if ($tenant && $section === 'people') {
    $roleservice->ensure_defaults((int)$tenant->id);
    $peopleservice = new people_service();
    $people = $peopleservice->list($tenant);
    $useroptions = [];
    foreach ($people as $person) {
        $useroptions[(int)$person->id] = fullname($person) . ' [' . s($person->username) . ']';
    }
    $departmentoptions = [];
    foreach ($organisationservice->list($tenant) as $native) {
        $departmentoptions[(int)$native->id] = format_string($native->name);
    }
    $courseoptions = [0 => get_string('notapplicable', 'local_tenantmaster')];
    foreach (
        $DB->get_records_sql(
            "SELECT c.id, c.fullname
           FROM {local_iomad_company_courses} cc
           JOIN {course} c ON c.id = cc.courseid
          WHERE cc.companyid = :companyid
       ORDER BY c.fullname",
            ['companyid' => $tenant->companyid],
        ) as $course
    ) {
        $courseoptions[(int)$course->id] = format_string($course->fullname);
    }
    $peopleform = new role_assignment($pageurl, [
        'users' => $useroptions,
        'roles' => catalog::localise(catalog::ROLE_KEYS),
        'departments' => $departmentoptions,
        'courses' => $courseoptions,
    ]);
    if ($data = $peopleform->get_data()) {
        require_sesskey();
        $access->require('local/tenantmaster:manageroles');
        $peopleservice->assign_role(
            $tenant,
            (int)$data->assignmentuserid,
            (string)$data->assignmentrolekey,
            (int)$data->assignmentdepartmentid,
            (int)$data->assignmentcourseid,
        );
        redirect($pageurl, get_string('roleassigned', 'local_tenantmaster'));
    }
    $nativeuserform = new native_user($pageurl, [
        'roles' => catalog::localise(catalog::ROLE_KEYS),
        'departments' => $departmentoptions,
        'courses' => $courseoptions,
    ]);
    if ($data = $nativeuserform->get_data()) {
        require_sesskey();
        $access->require('local/tenantmaster:managepeople');
        $nativeuser = (new native_user_service())->create($tenant, $data);
        $message = $nativeuser->notificationstatus === 'sent'
            ? get_string('nativeusercreated', 'local_tenantmaster')
            : get_string('nativeusercreatedmailfailed', 'local_tenantmaster');
        redirect($pageurl, $message);
    }
    $guardianform = new guardian_link($pageurl, ['users' => $useroptions]);
    if ($data = $guardianform->get_data()) {
        require_sesskey();
        $access->require('local/tenantmaster:manageroles');
        $peopleservice->link_guardian($tenant, (int)$data->guardianid, (int)$data->learnerid);
        redirect($pageurl, get_string('guardianlinked', 'local_tenantmaster'));
    }
}

$cohortform = null;
$cohortmemberform = null;
$groupform = null;
$accessform = null;
if ($tenant && $section === 'access') {
    $accessservice = new learning_access_service();
    $nativepeople = (new people_service())->list($tenant);
    $useroptions = [];
    foreach ($nativepeople as $person) {
        $useroptions[(int)$person->id] = fullname($person) . ' [' . s($person->idnumber) . ']';
    }
    $courseoptions = [];
    foreach (
        $DB->get_records_sql(
            "SELECT c.id, c.fullname
           FROM {local_iomad_company_courses} cc
           JOIN {course} c ON c.id = cc.courseid
          WHERE cc.companyid = :companyid
       ORDER BY c.fullname",
            ['companyid' => $tenant->companyid],
        ) as $course
    ) {
        $courseoptions[(int)$course->id] = format_string($course->fullname);
    }
    $cohortoptions = [];
    foreach (
        $DB->get_records_select(
            'cohort',
            'idnumber LIKE :prefix',
            ['prefix' => 'TM:' . $tenant->trustcode . ':COHORT:%'],
            'name',
        ) as $cohort
    ) {
        $cohortoptions[(int)$cohort->id] = format_string($cohort->name);
    }
    $groupoptions = [0 => get_string('notapplicable', 'local_tenantmaster')];
    foreach (
        $DB->get_records_sql(
            "SELECT g.id, g.name, c.shortname
           FROM {groups} g
           JOIN {course} c ON c.id = g.courseid
           JOIN {local_iomad_company_courses} cc ON cc.courseid = c.id
          WHERE cc.companyid = :companyid
            AND g.idnumber LIKE :groupprefix
       ORDER BY c.shortname, g.name",
            [
            'companyid' => $tenant->companyid,
            'groupprefix' => 'TM:' . $tenant->trustcode . ':GROUP:%',
            ],
        ) as $group
    ) {
        $groupoptions[(int)$group->id] = format_string($group->name) . ' [' . s($group->shortname) . ']';
    }

    $cohortform = new native_cohort($pageurl);
    if ($data = $cohortform->get_data()) {
        require_sesskey();
        $access->require('local/tenantmaster:managepeople');
        $accessservice->ensure_cohort(
            $tenant,
            (string)$data->cohortexternalid,
            (string)$data->cohortname,
            (string)$data->cohortdescription,
        );
        redirect($pageurl, get_string('cohortsaved', 'local_tenantmaster'));
    }
    $cohortmemberform = new cohort_member($pageurl, ['cohorts' => $cohortoptions, 'users' => $useroptions]);
    if ($data = $cohortmemberform->get_data()) {
        require_sesskey();
        $access->require('local/tenantmaster:managepeople');
        $accessservice->add_cohort_member($tenant, (int)$data->cohortid, (int)$data->cohortuserid);
        redirect($pageurl, get_string('cohortmemberadded', 'local_tenantmaster'));
    }
    $groupform = new native_group($pageurl, ['courses' => $courseoptions]);
    if ($data = $groupform->get_data()) {
        require_sesskey();
        $access->require('local/tenantmaster:managepeople');
        $accessservice->ensure_group(
            $tenant,
            (int)$data->groupcourseid,
            (string)$data->groupexternalid,
            (string)$data->groupname,
        );
        redirect($pageurl, get_string('groupsaved', 'local_tenantmaster'));
    }
    $accessform = new access_assignment($pageurl, [
        'users' => $useroptions,
        'courses' => $courseoptions,
        'groups' => $groupoptions,
    ]);
    if ($data = $accessform->get_data()) {
        require_sesskey();
        $access->require('local/tenantmaster:managepeople');
        $rolemap = $DB->get_record('local_tenantmaster_rolemap', [
            'tenantid' => $tenant->id,
            'rolekey' => $data->accessrolekey,
            'active' => 1,
        ], '*', MUST_EXIST);
        $accessservice->enrol_user(
            $tenant,
            (int)$data->accesscourseid,
            (int)$data->accessuserid,
            (int)$rolemap->roleid,
            (int)$data->accessgroupid,
        );
        redirect($pageurl, get_string('nativeuserenrolled', 'local_tenantmaster'));
    }
}

$importform = null;
if ($tenant && $section === 'imports') {
    $importform = new import_package($pageurl);
    if ($data = $importform->get_data()) {
        require_sesskey();
        $access->require('local/tenantmaster:import');
        $content = $importform->get_file_content('packagefile');
        $filename = $importform->get_new_filename('packagefile');
        if ($content === false || $filename === false) {
            throw new moodle_exception('invalidpackagefile', 'local_tenantmaster');
        }
        (new import_service())->inspect($tenant, $filename, $content, (string)$data->importmode);
        redirect($pageurl, get_string('packageplanned', 'local_tenantmaster'));
    }
}

echo $OUTPUT->header();
echo tenantmaster_tabs($section, $companyid);
if ($notice !== '') {
    echo $OUTPUT->notification($notice, 'success', false);
}
if (!$tenant && !($section === 'tenants' && is_siteadmin())) {
    echo $OUTPUT->notification(get_string('selecttenant', 'local_tenantmaster'), 'info', false);
    echo tenantmaster_tenant_table($tenantrepository->list());
    echo $OUTPUT->footer();
    exit;
}

switch ($section) {
    case 'tenants':
        echo $OUTPUT->heading(get_string('tenants', 'local_tenantmaster'));
        echo tenantmaster_tenant_table($tenantrepository->list($companyid > 0 && !is_siteadmin() ? $companyid : 0));
        if ($onboardingform) {
            echo $OUTPUT->heading(get_string('createtenant', 'local_tenantmaster'), 3);
            $onboardingform->display();
        }
        break;
    case 'profile':
        echo $OUTPUT->heading(get_string('institutionprofile', 'local_tenantmaster'));
        $profileform->display();
        break;
    case 'organisation':
        echo $OUTPUT->heading(get_string('organisation', 'local_tenantmaster'));
        echo tenantmaster_department_table($tenant, $organisationservice->list($tenant));
        echo $OUTPUT->heading($editid ? get_string('editdepartment', 'local_tenantmaster')
            : get_string('adddepartment', 'local_tenantmaster'), 3);
        $departmentform->display();
        break;
    case 'academic':
        echo $OUTPUT->heading(get_string('academicstructure', 'local_tenantmaster'));
        echo tenantmaster_academic_year_table($tenant);
        echo $OUTPUT->heading(get_string('addacademicyear', 'local_tenantmaster'), 3);
        $academicyearform->display();
        echo tenantmaster_master_filters($companyid, $typefilter);
        echo tenantmaster_master_table(
            $tenant,
            $masterrepository->list((int)$tenant->id, $typefilter),
        );
        echo $OUTPUT->heading($editid ? get_string('editmaster', 'local_tenantmaster')
            : get_string('addmaster', 'local_tenantmaster'), 3);
        $masterform->display();
        break;
    case 'courses':
        echo $OUTPUT->heading(get_string('courses', 'local_tenantmaster'));
        echo tenantmaster_mapping_table($tenant, ['core_course/category', 'core/course']);
        break;
    case 'people':
        echo $OUTPUT->heading(get_string('usersandroles', 'local_tenantmaster'));
        echo tenantmaster_role_table($roleservice->list((int)$tenant->id));
        echo tenantmaster_people_table((new people_service())->list($tenant));
        echo $OUTPUT->heading(get_string('assignbusinessrole', 'local_tenantmaster'), 3);
        $peopleform->display();
        echo $OUTPUT->heading(get_string('createnativeuser', 'local_tenantmaster'), 3);
        $nativeuserform->display();
        echo $OUTPUT->heading(get_string('guardianrelationship', 'local_tenantmaster'), 3);
        $guardianform->display();
        break;
    case 'access':
        echo $OUTPUT->heading(get_string('cohortsandenrolments', 'local_tenantmaster'));
        echo tenantmaster_access_summary($tenant);
        echo tenantmaster_access_details($tenant);
        echo $OUTPUT->heading(get_string('createcohort', 'local_tenantmaster'), 3);
        $cohortform->display();
        echo $OUTPUT->heading(get_string('addcohortmember', 'local_tenantmaster'), 3);
        $cohortmemberform->display();
        echo $OUTPUT->heading(get_string('creategroup', 'local_tenantmaster'), 3);
        $groupform->display();
        echo $OUTPUT->heading(get_string('enrolnativeuser', 'local_tenantmaster'), 3);
        $accessform->display();
        break;
    case 'assessments':
        echo $OUTPUT->heading(get_string('assessments', 'local_tenantmaster'));
        echo tenantmaster_policy_table($tenant, ['assessment_policy', 'attendance_policy']);
        break;
    case 'certificates':
        echo $OUTPUT->heading(get_string('certificates', 'local_tenantmaster'));
        echo tenantmaster_policy_table($tenant, ['certificate_rule']);
        break;
    case 'progression':
        echo $OUTPUT->heading(get_string('progression', 'local_tenantmaster'));
        echo tenantmaster_policy_table($tenant, ['progression_rule']);
        echo tenantmaster_rollover_table($tenant);
        echo $OUTPUT->heading(get_string('academicroollover', 'local_tenantmaster'), 3);
        $rolloverform->display();
        break;
    case 'imports':
        echo $OUTPUT->heading(get_string('imports', 'local_tenantmaster'));
        echo tenantmaster_import_table($tenant);
        echo $OUTPUT->heading(get_string('uploadpackage', 'local_tenantmaster'), 3);
        $importform->display();
        break;
    case 'sync':
        echo $OUTPUT->heading(get_string('synchronization', 'local_tenantmaster'));
        echo tenantmaster_action_button($tenant, 'syncall', get_string('syncall', 'local_tenantmaster'), 'primary');
        echo tenantmaster_sync_tables($tenant);
        break;
    case 'validation':
        echo $OUTPUT->heading(get_string('validation', 'local_tenantmaster'));
        echo tenantmaster_action_button($tenant, 'validate', get_string('validateall', 'local_tenantmaster'), 'secondary');
        echo tenantmaster_validation_table($tenant);
        break;
    case 'audit':
        echo $OUTPUT->heading(get_string('audit', 'local_tenantmaster'));
        echo tenantmaster_audit_table($tenant);
        break;
    default:
        echo tenantmaster_dashboard($tenant);
        break;
}

echo $OUTPUT->footer();

/**
 * Resolve section string key.
 *
 * @param string $section Section.
 * @return string
 */
function tenantmaster_section_string(string $section): string {
    return [
        'dashboard' => 'dashboard',
        'tenants' => 'tenants',
        'profile' => 'institutionprofile',
        'organisation' => 'organisation',
        'academic' => 'academicstructure',
        'courses' => 'courses',
        'people' => 'usersandroles',
        'access' => 'cohortsandenrolments',
        'assessments' => 'assessments',
        'certificates' => 'certificates',
        'progression' => 'progression',
        'imports' => 'imports',
        'sync' => 'synchronization',
        'validation' => 'validation',
        'audit' => 'audit',
    ][$section] ?? 'dashboard';
}

/**
 * Standard theme tabs.
 *
 * @param string $active Active section.
 * @param int $companyid Company.
 * @return string
 */
function tenantmaster_tabs(string $active, int $companyid): string {
    global $OUTPUT;

    $tabs = [];
    foreach (
        [
        'dashboard',
        'profile',
        'organisation',
        'academic',
        'courses',
        'people',
        'access',
        'assessments',
        'certificates',
        'progression',
        'imports',
        'sync',
        'validation',
        'audit',
        ] as $section
    ) {
        $tabs[] = new tabobject(
            $section,
            new moodle_url('/local/tenantmaster/index.php', ['section' => $section, 'companyid' => $companyid]),
            get_string(tenantmaster_section_string($section), 'local_tenantmaster'),
        );
    }
    return $OUTPUT->tabtree($tabs, $active);
}

/**
 * Dashboard.
 *
 * @param object $tenant Tenant.
 * @return string
 */
function tenantmaster_dashboard(object $tenant): string {
    global $DB, $OUTPUT;

    $counts = [
        get_string('academicmasters', 'local_tenantmaster') =>
            $DB->count_records('local_tenantmaster_master', ['tenantid' => $tenant->id, 'active' => 1]),
        get_string('nativedepartments', 'local_tenantmaster') =>
            $DB->count_records('local_iomad_company_departments', ['companyid' => $tenant->companyid]),
        get_string('nativeusers', 'local_tenantmaster') =>
            $DB->count_records('local_iomad_company_users', ['companyid' => $tenant->companyid]),
        get_string('nativecourses', 'local_tenantmaster') =>
            $DB->count_records('local_iomad_company_courses', ['companyid' => $tenant->companyid]),
        get_string('pendingwork', 'local_tenantmaster') =>
            $DB->count_records_select(
                'local_tenantmaster_dirty',
                'tenantid = :tenantid AND state <> :state',
                ['tenantid' => $tenant->id, 'state' => 'synced']
            ),
        get_string('blockingissues', 'local_tenantmaster') =>
            $DB->count_records(
                'local_tenantmaster_valissue',
                ['tenantid' => $tenant->id, 'status' => 'open', 'blocking' => 1]
            ),
    ];
    $table = new html_table();
    $table->head = [
        get_string('measure', 'local_tenantmaster'),
        get_string('count', 'local_tenantmaster'),
    ];
    foreach ($counts as $label => $count) {
        $table->data[] = [s($label), (int)$count];
    }
    $actions = html_writer::div(
        tenantmaster_action_button($tenant, 'syncall', get_string('syncall', 'local_tenantmaster'), 'primary')
        . tenantmaster_action_button($tenant, 'validate', get_string('validateall', 'local_tenantmaster'), 'secondary')
        . tenantmaster_action_button(
            $tenant,
            'adoptdefaults',
            get_string('adoptdefaults', 'local_tenantmaster'),
            'secondary',
        ),
        'd-flex flex-wrap gap-2 mb-3',
    );
    return $OUTPUT->heading(get_string('dashboard', 'local_tenantmaster'), 2)
        . $actions . html_writer::table($table);
}

/**
 * POST action button.
 *
 * @param object $tenant Tenant.
 * @param string $action Action.
 * @param string $label Label.
 * @param string $type Button type.
 * @param array<string, int|string> $extra Extra URL params.
 * @return string
 */
function tenantmaster_action_button(
    object $tenant,
    string $action,
    string $label,
    string $type,
    array $extra = [],
): string {
    global $OUTPUT;

    $params = array_merge([
        'section' => optional_param('section', 'dashboard', PARAM_ALPHA),
        'companyid' => $tenant->companyid,
        'action' => $action,
        'sesskey' => sesskey(),
    ], $extra);
    return $OUTPUT->single_button(
        new moodle_url('/local/tenantmaster/index.php', $params),
        $label,
        'post',
        ['type' => $type],
    );
}

/**
 * Tenant list.
 *
 * @param array<int, object> $tenants Tenants.
 * @return string
 */
function tenantmaster_tenant_table(array $tenants): string {
    $table = new html_table();
    $table->head = [
        get_string('institutionname', 'local_tenantmaster'),
        get_string('trustcode', 'local_tenantmaster'),
        get_string('tenanttype', 'local_tenantmaster'),
        get_string('status'),
        get_string('actions'),
    ];
    foreach ($tenants as $tenant) {
        $table->data[] = [
            format_string($tenant->companyname),
            s($tenant->trustcode),
            get_string(catalog::TENANT_TYPES[$tenant->tenanttype], 'local_tenantmaster'),
            s($tenant->status),
            html_writer::link(
                new moodle_url('/local/tenantmaster/index.php', [
                    'section' => 'dashboard',
                    'companyid' => $tenant->companyid,
                ]),
                get_string('open', 'local_tenantmaster'),
            ),
        ];
    }
    return html_writer::table($table);
}

/**
 * Native department table.
 *
 * @param object $tenant Tenant.
 * @param array<int, object> $departments Departments.
 * @return string
 */
function tenantmaster_department_table(object $tenant, array $departments): string {
    $names = array_map(static fn(object $department): string => format_string($department->name), $departments);
    $table = new html_table();
    $table->head = [
        get_string('departmentname', 'local_tenantmaster'),
        get_string('shortname'),
        get_string('parent', 'local_tenantmaster'),
        get_string('actions'),
    ];
    foreach ($departments as $department) {
        $table->data[] = [
            format_string($department->name),
            s($department->shortname),
            $department->parentid ? ($names[$department->parentid] ?? '-') : get_string('root', 'local_tenantmaster'),
            html_writer::link(
                new moodle_url('/local/tenantmaster/index.php', [
                    'section' => 'organisation',
                    'companyid' => $tenant->companyid,
                    'editid' => $department->id,
                ]),
                get_string('edit'),
            ),
        ];
    }
    return html_writer::table($table);
}

/**
 * Master type filters.
 *
 * @param int $companyid Company.
 * @param string $active Active type.
 * @return string
 */
function tenantmaster_master_filters(int $companyid, string $active): string {
    $links = [];
    $links[] = html_writer::link(
        new moodle_url('/local/tenantmaster/index.php', ['section' => 'academic', 'companyid' => $companyid]),
        get_string('all'),
        ['class' => $active === '' ? 'font-weight-bold' : ''],
    );
    foreach (catalog::MASTER_TYPES as $type => $stringkey) {
        $links[] = html_writer::link(
            new moodle_url('/local/tenantmaster/index.php', [
                'section' => 'academic',
                'companyid' => $companyid,
                'type' => $type,
            ]),
            get_string($stringkey, 'local_tenantmaster'),
            ['class' => $active === $type ? 'font-weight-bold' : ''],
        );
    }
    return html_writer::div(implode(' | ', $links), 'mb-3');
}

/**
 * Academic year table.
 *
 * @param object $tenant Tenant.
 * @return string
 */
function tenantmaster_academic_year_table(object $tenant): string {
    $records = (new academic_year_service())->list((int)$tenant->id);
    $table = new html_table();
    $table->head = [
        get_string('code', 'local_tenantmaster'),
        get_string('name'),
        get_string('startdate'),
        get_string('enddate'),
        get_string('currentacademicyear', 'local_tenantmaster'),
        get_string('status'),
    ];
    foreach ($records as $record) {
        $table->data[] = [
            s($record->code),
            format_string($record->name),
            userdate($record->startdate, get_string('strftimedatefullshort')),
            userdate($record->enddate, get_string('strftimedatefullshort')),
            $record->iscurrent ? get_string('yes') : get_string('no'),
            s($record->status),
        ];
    }
    return html_writer::table($table);
}

/**
 * Academic master table.
 *
 * @param object $tenant Tenant.
 * @param array<int, object> $masters Masters.
 * @return string
 */
function tenantmaster_master_table(object $tenant, array $masters): string {
    $table = new html_table();
    $table->head = [
        get_string('mastertype', 'local_tenantmaster'),
        get_string('code', 'local_tenantmaster'),
        get_string('name'),
        get_string('active', 'local_tenantmaster'),
        get_string('nativeprojection', 'local_tenantmaster'),
        get_string('actions'),
    ];
    foreach ($masters as $master) {
        $mappings = $GLOBALS['DB']->get_records('local_tenantmaster_mapping', [
            'tenantid' => $tenant->id,
            'masterid' => $master->id,
        ]);
        $projection = $mappings
            ? implode(', ', array_map(static fn(object $mapping): string =>
                s($mapping->component) . ' #' . (int)$mapping->targetid, $mappings))
            : get_string('pending', 'local_tenantmaster');
        $table->data[] = [
            get_string(catalog::MASTER_TYPES[$master->mastertype], 'local_tenantmaster'),
            s($master->code),
            format_string($master->name),
            $master->active ? get_string('yes') : get_string('no'),
            $projection,
            html_writer::link(
                new moodle_url('/local/tenantmaster/index.php', [
                    'section' => 'academic',
                    'companyid' => $tenant->companyid,
                    'editid' => $master->id,
                    'type' => $master->mastertype,
                ]),
                get_string('edit'),
            ),
        ];
    }
    return html_writer::table($table);
}

/**
 * Native mapping table.
 *
 * @param object $tenant Tenant.
 * @param string[] $components Components.
 * @return string
 */
function tenantmaster_mapping_table(object $tenant, array $components): string {
    global $DB;

    [$insql, $params] = $DB->get_in_or_equal($components, SQL_PARAMS_NAMED, 'component');
    $params['tenantid'] = $tenant->id;
    $mappings = $DB->get_records_select(
        'local_tenantmaster_mapping',
        "tenantid = :tenantid AND component $insql",
        $params,
        'component, externalkey',
    );
    $table = new html_table();
    $table->head = [
        get_string('component', 'local_tenantmaster'),
        get_string('externalid', 'local_tenantmaster'),
        get_string('nativeid', 'local_tenantmaster'),
        get_string('status'),
        get_string('lastsync', 'local_tenantmaster'),
    ];
    foreach ($mappings as $mapping) {
        $table->data[] = [
            s($mapping->component),
            s($mapping->externalkey),
            (int)$mapping->targetid,
            s($mapping->status),
            $mapping->lastsynced ? userdate($mapping->lastsynced) : '-',
        ];
    }
    return html_writer::table($table);
}

/**
 * Business role mapping table.
 *
 * @param array<int, object> $roles Roles.
 * @return string
 */
function tenantmaster_role_table(array $roles): string {
    $table = new html_table();
    $table->head = [
        get_string('businessrole', 'local_tenantmaster'),
        get_string('nativerole', 'local_tenantmaster'),
        get_string('scope', 'local_tenantmaster'),
        get_string('managertype', 'local_tenantmaster'),
    ];
    foreach ($roles as $role) {
        $table->data[] = [
            get_string(catalog::ROLE_KEYS[$role->rolekey], 'local_tenantmaster'),
            format_string($role->rolename ?: $role->roleshortname ?: get_string('unmapped', 'local_tenantmaster')),
            s($role->scope),
            (int)$role->managertype,
        ];
    }
    return html_writer::table($table);
}

/**
 * Native company people table.
 *
 * @param array<int, object> $people People.
 * @return string
 */
function tenantmaster_people_table(array $people): string {
    $table = new html_table();
    $table->head = [
        get_string('name'),
        get_string('idnumber'),
        get_string('department'),
        get_string('managertype', 'local_tenantmaster'),
        get_string('educator', 'local_tenantmaster'),
    ];
    foreach ($people as $person) {
        $table->data[] = [
            fullname($person),
            s($person->idnumber),
            format_string($person->departmentname ?? ''),
            (int)$person->managertype,
            $person->educator ? get_string('yes') : get_string('no'),
        ];
    }
    return html_writer::table($table);
}

/**
 * Native access summary.
 *
 * @param object $tenant Tenant.
 * @return string
 */
function tenantmaster_access_summary(object $tenant): string {
    global $DB;

    $table = new html_table();
    $table->head = [
        get_string('nativeentity', 'local_tenantmaster'),
        get_string('count', 'local_tenantmaster'),
    ];
    $table->data = [
        [get_string('companymemberships', 'local_tenantmaster'),
            $DB->count_records('local_iomad_company_users', ['companyid' => $tenant->companyid])],
        [get_string('companycourses', 'local_tenantmaster'),
            $DB->count_records('local_iomad_company_courses', ['companyid' => $tenant->companyid])],
        [get_string('cohorts', 'local_tenantmaster'), $DB->count_records_select(
            'cohort',
            'idnumber LIKE :prefix',
            ['prefix' => 'TM:' . $tenant->trustcode . ':%'],
        )],
        [get_string('groups'), $DB->count_records_sql(
            "SELECT COUNT(DISTINCT g.id)
               FROM {groups} g
               JOIN {local_iomad_company_courses} cc ON cc.courseid = g.courseid
              WHERE cc.companyid = :companyid
                AND g.idnumber LIKE :groupprefix",
            [
                'companyid' => $tenant->companyid,
                'groupprefix' => 'TM:' . $tenant->trustcode . ':GROUP:%',
            ],
        )],
        [get_string('enrolments', 'local_tenantmaster'), $DB->count_records_sql(
            "SELECT COUNT(DISTINCT ue.id)
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
               JOIN {local_iomad_company_courses} cc ON cc.courseid = e.courseid
               JOIN {local_iomad_company_users} cu
                 ON cu.userid = ue.userid AND cu.companyid = cc.companyid
              WHERE cc.companyid = :companyid",
            ['companyid' => $tenant->companyid],
        )],
    ];
    return html_writer::table($table);
}

/**
 * Detailed native cohorts, groups and enrolments.
 *
 * @param object $tenant Tenant.
 * @return string
 */
function tenantmaster_access_details(object $tenant): string {
    global $DB, $OUTPUT;

    $cohorts = $DB->get_records_select(
        'cohort',
        'idnumber LIKE :prefix',
        ['prefix' => 'TM:' . $tenant->trustcode . ':COHORT:%'],
        'name',
    );
    $cohorttable = new html_table();
    $cohorttable->head = [
        get_string('name'),
        get_string('idnumber'),
        get_string('members', 'local_tenantmaster'),
    ];
    foreach ($cohorts as $cohort) {
        $cohorttable->data[] = [
            format_string($cohort->name),
            s($cohort->idnumber),
            $DB->count_records('cohort_members', ['cohortid' => $cohort->id]),
        ];
    }

    $groups = $DB->get_records_sql(
        "SELECT g.id, g.name, g.idnumber, c.fullname AS coursename,
                COUNT(gm.id) AS membercount
           FROM {groups} g
           JOIN {course} c ON c.id = g.courseid
           JOIN {local_iomad_company_courses} cc ON cc.courseid = c.id
      LEFT JOIN {groups_members} gm ON gm.groupid = g.id
          WHERE cc.companyid = :companyid
            AND g.idnumber LIKE :groupprefix
       GROUP BY g.id, g.name, g.idnumber, c.fullname
       ORDER BY c.fullname, g.name",
        [
            'companyid' => $tenant->companyid,
            'groupprefix' => 'TM:' . $tenant->trustcode . ':GROUP:%',
        ],
    );
    $grouptable = new html_table();
    $grouptable->head = [
        get_string('name'),
        get_string('course'),
        get_string('idnumber'),
        get_string('members', 'local_tenantmaster'),
    ];
    foreach ($groups as $group) {
        $grouptable->data[] = [
            format_string($group->name),
            format_string($group->coursename),
            s($group->idnumber),
            (int)$group->membercount,
        ];
    }

    $enrolments = $DB->get_records_sql(
        "SELECT ue.id, u.idnumber AS useridnumber, c.fullname AS coursename
           FROM {user_enrolments} ue
           JOIN {enrol} e ON e.id = ue.enrolid
           JOIN {course} c ON c.id = e.courseid
           JOIN {local_iomad_company_courses} cc ON cc.courseid = c.id
           JOIN {local_iomad_company_users} cu
             ON cu.userid = ue.userid AND cu.companyid = cc.companyid
           JOIN {user} u ON u.id = ue.userid
          WHERE cc.companyid = :companyid
       ORDER BY c.fullname, u.idnumber",
        ['companyid' => $tenant->companyid],
        0,
        200,
    );
    $enroltable = new html_table();
    $enroltable->head = [get_string('user'), get_string('course')];
    foreach ($enrolments as $enrolment) {
        $enroltable->data[] = [
            s($enrolment->useridnumber),
            format_string($enrolment->coursename),
        ];
    }

    return $OUTPUT->heading(get_string('cohorts', 'local_tenantmaster'), 3)
        . html_writer::table($cohorttable)
        . $OUTPUT->heading(get_string('groups'), 3)
        . html_writer::table($grouptable)
        . $OUTPUT->heading(get_string('enrolments', 'local_tenantmaster'), 3)
        . html_writer::table($enroltable);
}

/**
 * Policy masters.
 *
 * @param object $tenant Tenant.
 * @param string[] $types Types.
 * @return string
 */
function tenantmaster_policy_table(object $tenant, array $types): string {
    global $DB;

    [$insql, $params] = $DB->get_in_or_equal($types, SQL_PARAMS_NAMED, 'type');
    $params['tenantid'] = $tenant->id;
    $records = $DB->get_records_select(
        'local_tenantmaster_master',
        "tenantid = :tenantid AND mastertype $insql",
        $params,
        'mastertype, name',
    );
    $table = new html_table();
    $table->head = [get_string('mastertype', 'local_tenantmaster'), get_string('name'),
        get_string('configurationjson', 'local_tenantmaster'), get_string('active', 'local_tenantmaster')];
    foreach ($records as $record) {
        $table->data[] = [
            get_string(catalog::MASTER_TYPES[$record->mastertype], 'local_tenantmaster'),
            format_string($record->name),
            html_writer::tag('code', s($record->payloadjson)),
            $record->active ? get_string('yes') : get_string('no'),
        ];
    }
    return html_writer::table($table);
}

/**
 * Rollover table.
 *
 * @param object $tenant Tenant.
 * @return string
 */
function tenantmaster_rollover_table(object $tenant): string {
    global $DB;

    $records = $DB->get_records('local_tenantmaster_rollover', ['tenantid' => $tenant->id], 'timecreated DESC');
    $table = new html_table();
    $table->head = [
        get_string('fromyear', 'local_tenantmaster'),
        get_string('toyear', 'local_tenantmaster'),
        get_string('status'),
        get_string('backupreference', 'local_tenantmaster'),
    ];
    foreach ($records as $record) {
        $table->data[] = [(int)$record->fromyearid, (int)$record->toyearid, s($record->status), s($record->backupref)];
    }
    return html_writer::table($table);
}

/**
 * Import batches.
 *
 * @param object $tenant Tenant.
 * @return string
 */
function tenantmaster_import_table(object $tenant): string {
    global $DB;

    $records = $DB->get_records('local_tenantmaster_batch', ['tenantid' => $tenant->id], 'timecreated DESC');
    $table = new html_table();
    $table->head = [
        get_string('checksum', 'local_tenantmaster'),
        get_string('schemaversion', 'local_tenantmaster'),
        get_string('mode', 'local_tenantmaster'),
        get_string('status'),
        get_string('rowcount', 'local_tenantmaster'),
        get_string('actions'),
    ];
    foreach ($records as $record) {
        $action = '';
        if (in_array($record->status, ['planned', 'applying', 'completed_with_errors'], true)) {
            $action = tenantmaster_action_button(
                $tenant,
                'importapply',
                $record->status === 'completed_with_errors'
                    ? get_string('resumeimport', 'local_tenantmaster')
                    : get_string('applypackage', 'local_tenantmaster'),
                'primary',
                ['batchid' => $record->id],
            );
        }
        $table->data[] = [
            s(substr($record->checksum, 0, 16)),
            s($record->schemaversion),
            s($record->mode),
            s($record->status),
            (int)$record->rowcount,
            $action,
        ];
    }
    return html_writer::table($table);
}

/**
 * Synchronization work, jobs and drift.
 *
 * @param object $tenant Tenant.
 * @return string
 */
function tenantmaster_sync_tables(object $tenant): string {
    global $DB, $OUTPUT;

    $dirty = $DB->get_records('local_tenantmaster_dirty', ['tenantid' => $tenant->id], 'timemodified DESC', '*', 0, 100);
    $table = new html_table();
    $table->head = [get_string('module', 'local_tenantmaster'), get_string('state', 'local_tenantmaster'),
        get_string('attempts', 'local_tenantmaster'), get_string('lasterror', 'local_tenantmaster'),
        get_string('actions')];
    foreach ($dirty as $record) {
        $retry = in_array($record->state, ['blocked', 'retryable'], true)
            ? tenantmaster_action_button(
                $tenant,
                'retry',
                get_string('retry', 'local_tenantmaster'),
                'secondary',
                ['dirtyid' => $record->id]
            )
            : '';
        $table->data[] = [
            s($record->module),
            s($record->state),
            (int)$record->attempts,
            s($record->lasterror ?? ''),
            $retry,
        ];
    }
    $jobs = $DB->get_records('local_tenantmaster_job', ['tenantid' => $tenant->id], 'timecreated DESC', '*', 0, 50);
    $jobtable = new html_table();
    $jobtable->head = [get_string('module', 'local_tenantmaster'), get_string('status'),
        get_string('completed', 'local_tenantmaster'), get_string('failed', 'local_tenantmaster'),
        get_string('timecreated')];
    foreach ($jobs as $job) {
        $jobtable->data[] = [
            s($job->module),
            s($job->status),
            (int)$job->completeditems . '/' . (int)$job->totalitems,
            (int)$job->faileditems,
            userdate($job->timecreated),
        ];
    }
    $drifts = $DB->get_records(
        'local_tenantmaster_drift',
        ['tenantid' => $tenant->id, 'status' => 'open'],
        'timecreated DESC'
    );
    $drifttable = new html_table();
    $drifttable->head = [get_string('component', 'local_tenantmaster'), get_string('field', 'local_tenantmaster'),
        get_string('drifttype', 'local_tenantmaster'), get_string('actions')];
    foreach ($drifts as $drift) {
        $mapping = $DB->get_record('local_tenantmaster_mapping', ['id' => $drift->mappingid], '*', MUST_EXIST);
        $actions = '';
        foreach (['import_native', 'restore_managed', 'ignore'] as $resolution) {
            $actions .= tenantmaster_action_button(
                $tenant,
                'resolvedrift',
                get_string('resolution_' . $resolution, 'local_tenantmaster'),
                'secondary',
                ['driftid' => $drift->id, 'resolution' => $resolution],
            );
        }
        $drifttable->data[] = [s($mapping->component), s($drift->fieldpath), s($drift->drifttype), $actions];
    }
    return $OUTPUT->heading(get_string('pendingwork', 'local_tenantmaster'), 3)
        . html_writer::table($table)
        . $OUTPUT->heading(get_string('jobs', 'local_tenantmaster'), 3)
        . html_writer::table($jobtable)
        . $OUTPUT->heading(get_string('drift', 'local_tenantmaster'), 3)
        . html_writer::table($drifttable);
}

/**
 * Validation issues.
 *
 * @param object $tenant Tenant.
 * @return string
 */
function tenantmaster_validation_table(object $tenant): string {
    global $DB;

    $records = $DB->get_records(
        'local_tenantmaster_valissue',
        ['tenantid' => $tenant->id, 'status' => 'open'],
        'blocking DESC, severity, module'
    );
    $table = new html_table();
    $table->head = [get_string('severity', 'local_tenantmaster'), get_string('module', 'local_tenantmaster'),
        get_string('record', 'local_tenantmaster'), get_string('field', 'local_tenantmaster'),
        get_string('issue', 'local_tenantmaster'),
        get_string('correction', 'local_tenantmaster'), get_string('blocking', 'local_tenantmaster')];
    foreach ($records as $record) {
        $table->data[] = [
            s($record->severity),
            s($record->module),
            s($record->entitytable) . ' #' . (int)$record->entityid,
            s($record->fieldname),
            s($record->message),
            s($record->correction),
            $record->blocking ? get_string('yes') : get_string('no'),
        ];
    }
    return html_writer::table($table);
}

/**
 * Audit table without sensitive payload output.
 *
 * @param object $tenant Tenant.
 * @return string
 */
function tenantmaster_audit_table(object $tenant): string {
    global $DB;

    $records = $DB->get_records(
        'local_tenantmaster_audit',
        ['tenantid' => $tenant->id],
        'timecreated DESC',
        '*',
        0,
        200
    );
    $table = new html_table();
    $table->head = [get_string('time'), get_string('action'), get_string('result', 'local_tenantmaster'),
        get_string('record', 'local_tenantmaster'), get_string('nativeid', 'local_tenantmaster')];
    foreach ($records as $record) {
        $table->data[] = [
            userdate($record->timecreated),
            s($record->action),
            s($record->result),
            s($record->entitytable ?? '') . ($record->entityid ? ' #' . (int)$record->entityid : ''),
            s($record->targetcomponent ?? '') . ($record->targetid ? ' #' . (int)$record->targetid : ''),
        ];
    }
    return html_writer::table($table);
}
