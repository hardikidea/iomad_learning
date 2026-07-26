<?php
// This file is part of Moodle - http://moodle.org/

use local_tenantmaster\form\academic_year;
use local_tenantmaster\form\company_adoption;
use local_tenantmaster\form\import_package;
use local_tenantmaster\form\master;
use local_tenantmaster\form\rollover;
use local_tenantmaster\form\school_year_setup;
use local_tenantmaster\form\student_placement;
use local_tenantmaster\form\student_progression;
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
use local_tenantmaster\local\onboarding_service;
use local_tenantmaster\local\people_service;
use local_tenantmaster\local\queue_service;
use local_tenantmaster\local\rollover_service;
use local_tenantmaster\local\school_year_setup_service;
use local_tenantmaster\local\student_placement_service;
use local_tenantmaster\local\student_progression_service;
use local_tenantmaster\local\tenant_repository;
use local_tenantmaster\local\tenant_service;
use local_tenantmaster\local\validation_service;

require_once(__DIR__ . '/../../config.php');

require_login();

$section = optional_param('section', 'dashboard', PARAM_ALPHA);
$companyid = optional_param('companyid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHANUMEXT);
$editid = optional_param('editid', 0, PARAM_INT);
$yeareditid = optional_param('yeareditid', 0, PARAM_INT);
$placementeditid = optional_param('placementeditid', 0, PARAM_INT);
$typefilter = optional_param('type', '', PARAM_ALPHANUMEXT);
$access = access::resolve($companyid);
$companyid = $access->companyid();
$tenantrepository = new tenant_repository();
$tenant = $companyid > 0 ? $tenantrepository->get_by_company($companyid) : null;
$notice = '';

// Keep native IOMAD administration links on the selected site-admin company.
if (is_siteadmin() && $companyid > 0) {
    $SESSION->currenteditingcompany = $companyid;
}

if ($tenant && in_array($section, ['organisation', 'people', 'access'], true)) {
    $destination = match ($section) {
        'organisation' => '/blocks/iomad_company_admin/company_departments.php',
        'people' => '/blocks/iomad_company_admin/editusers.php',
        default => '/blocks/iomad_company_admin/index.php',
    };
    redirect(new moodle_url($destination, ['company' => $companyid]));
}
if ($tenant && in_array($section, ['assessments', 'certificates'], true)) {
    redirect(new moodle_url('/local/tenantmaster/index.php', [
        'section' => 'academic',
        'companyid' => $companyid,
        'type' => $section === 'assessments' ? 'assessment_policy' : 'certificate_rule',
    ]));
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
        case 'applyprogression':
            $access->require('local/tenantmaster:manageacademic');
            (new student_progression_service())->apply(
                $tenant,
                required_param('progressid', PARAM_INT),
            );
            $notice = get_string('progressionapplied', 'local_tenantmaster');
            break;
    }
}

$adoptionform = null;
if (is_siteadmin() && $companyid === 0 && $section === 'tenants') {
    $companyoptions = [];
    $companies = $DB->get_records_sql(
        "SELECT c.id, c.name, c.shortname, c.code
           FROM {local_iomad_companies} c
      LEFT JOIN {local_tenantmaster_tenant} t ON t.companyid = c.id
          WHERE c.suspended = 0
            AND t.id IS NULL
       ORDER BY c.name",
    );
    foreach ($companies as $company) {
        $companyoptions[(int)$company->id] = format_string($company->name)
            . ' [' . s($company->code ?: get_string('missingcompanycode', 'local_tenantmaster')) . ']';
    }
    if ($companyoptions) {
        $adoptionform = new company_adoption($pageurl, ['companies' => $companyoptions]);
    }
    if ($adoptionform && ($data = $adoptionform->get_data())) {
        require_sesskey();
        $createdtenant = (new onboarding_service())->adopt_existing(
            (int)$data->adoptcompanyid,
            (string)$data->tenanttype,
        );
        redirect(
            new moodle_url('/local/tenantmaster/index.php', [
                'section' => 'dashboard',
                'companyid' => $createdtenant->companyid,
            ]),
            get_string('tenantinitialised', 'local_tenantmaster'),
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
        $metadata = [
            'trustlegalname' => $data->trustlegalname,
            'trustregistrationnumber' => $data->trustregistrationnumber,
            'udisecode' => $data->udisecode,
            'boardaffiliationnumber' => $data->boardaffiliationnumber,
            'recognitionnumber' => $data->recognitionnumber,
            'establishmentyear' => $data->establishmentyear,
            'schoolstage' => $data->schoolstage,
            'managementtype' => $data->managementtype,
            'academicsession' => $data->academicsession,
            'district' => $data->district,
            'block' => $data->block,
            'preferredlanguages' => $data->preferredlanguages,
            'institutioncode' => $data->institutioncode,
            'aishecode' => $data->aishecode,
            'universitytype' => $data->universitytype,
            'accreditationbody' => $data->accreditationbody,
            'accreditationgrade' => $data->accreditationgrade,
            'regulatoryauthority' => $data->regulatoryauthority,
            'approvalnumber' => $data->approvalnumber,
            'academiccalendar' => $data->academiccalendar,
            'creditframework' => $data->creditframework,
        ];
        $record->profilejson = json::encode(array_filter(
            $metadata,
            static fn(mixed $value): bool => $value !== '' && $value !== 0,
        ));
        $tenant = (new tenant_service())->save($record);
        $notice = get_string('profilesaved', 'local_tenantmaster');
    }
    $profileform->set_data((object)(array_merge(
        json::decode_object($tenant->profilejson),
        [
            'id' => $tenant->id,
            'companyid' => $tenant->companyid,
            'trustcode' => $tenant->trustcode,
            'tenanttype' => $tenant->tenanttype,
        ],
    )));
}

$masterrepository = new master_repository();
$masterform = null;
$academicyearform = null;
$schoolyearsetupform = null;
if ($tenant && $section === 'academic') {
    $academicyearform = new academic_year($pageurl, ['editing' => $yeareditid > 0]);
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
            'status' => $data->yearstatus,
            'payloadjson' => '{}',
        ]);
        redirect($pageurl, get_string('academicyearsaved', 'local_tenantmaster'));
    }
    if ($yeareditid > 0) {
        $editingyear = $DB->get_record('local_tenantmaster_acadyear', [
            'id' => $yeareditid,
            'tenantid' => $tenant->id,
        ], '*', MUST_EXIST);
        $academicyearform->set_data((object)[
            'yearid' => $editingyear->id,
            'yearexternalid' => $editingyear->externalid,
            'yearcode' => $editingyear->code,
            'yearname' => $editingyear->name,
            'yearstartdate' => $editingyear->startdate,
            'yearenddate' => $editingyear->enddate,
            'yeariscurrent' => $editingyear->iscurrent,
            'yearstatus' => $editingyear->status,
        ]);
    }
    $parentoptions = [0 => get_string('none')];
    foreach ($masterrepository->list((int)$tenant->id) as $parent) {
        if ((int)$parent->id !== $editid) {
            $parentoptions[(int)$parent->id] = format_string($parent->name)
                . ' [' . s($parent->mastertype) . ']';
        }
    }
    $yearoptions = [];
    foreach ((new academic_year_service())->list((int)$tenant->id) as $yearoption) {
        $yearoptions[(int)$yearoption->id] = format_string($yearoption->name);
    }
    $masterform = new master($pageurl, [
        'parents' => $parentoptions,
        'editing' => $editid > 0,
        'years' => $yearoptions,
    ]);
    if ($data = $masterform->get_data()) {
        require_sesskey();
        $access->require('local/tenantmaster:manageacademic');
        if ((int)$data->parentid > 0) {
            $masterrepository->get((int)$tenant->id, (int)$data->parentid);
        }
        $data->tenantid = $tenant->id;
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
            'acadyearid' => 0,
            'mastertype' => $typefilter ?: 'grade',
            'payloadjson' => '{}',
            'active' => 1,
        ]);
    }
    if ((string)$tenant->tenanttype === 'school') {
        $schoolyearsetupform = new school_year_setup($pageurl, [
            'years' => $yearoptions,
            'boards' => tenantmaster_shared_master_options((int)$tenant->id, 'board'),
            'mediums' => tenantmaster_shared_master_options((int)$tenant->id, 'medium'),
            'grades' => tenantmaster_shared_master_options((int)$tenant->id, 'grade'),
            'streams' => tenantmaster_shared_master_options((int)$tenant->id, 'stream'),
            'subjects' => tenantmaster_shared_master_options((int)$tenant->id, 'subject'),
        ]);
        if ($data = $schoolyearsetupform->get_data()) {
            require_sesskey();
            $access->require('local/tenantmaster:manageacademic');
            $result = (new school_year_setup_service())->generate($tenant, $data);
            redirect($pageurl, get_string('schoolyeargenerated', 'local_tenantmaster', (object)$result));
        }
    }
}

$placementform = null;
if ($tenant && $section === 'classes') {
    if ((string)$tenant->tenanttype !== 'school') {
        throw new moodle_exception('schooltenantrequired', 'local_tenantmaster');
    }
    $useroptions = [];
    foreach ((new people_service())->list($tenant) as $person) {
        $useroptions[(int)$person->id] = fullname($person) . ' [' . s($person->idnumber) . ']';
    }
    $yearoptions = [];
    foreach ((new academic_year_service())->list((int)$tenant->id) as $yearoption) {
        $yearoptions[(int)$yearoption->id] = format_string($yearoption->name);
    }
    $placementform = new student_placement($pageurl, [
        'editing' => $placementeditid > 0,
        'users' => $useroptions,
        'years' => $yearoptions,
        'boards' => tenantmaster_master_options((int)$tenant->id, 'board'),
        'mediums' => tenantmaster_master_options((int)$tenant->id, 'medium'),
        'grades' => tenantmaster_master_options((int)$tenant->id, 'grade'),
        'streams' => tenantmaster_master_options((int)$tenant->id, 'stream'),
        'divisions' => tenantmaster_master_options((int)$tenant->id, 'division'),
    ]);
    if ($data = $placementform->get_data()) {
        require_sesskey();
        $access->require('local/tenantmaster:managepeople');
        $savedplacement = (new student_placement_service())->save($tenant, $data);
        redirect(
            $pageurl,
            get_string('placementsaved', 'local_tenantmaster', $savedplacement->provisionedcourses),
        );
    }
    if ($placementeditid > 0) {
        $placementform->set_data($DB->get_record('local_tenantmaster_placement', [
            'id' => $placementeditid,
            'tenantid' => $tenant->id,
        ], '*', MUST_EXIST));
    } else if ((int)$tenant->activeyearid > 0) {
        $placementform->set_data((object)[
            'acadyearid' => (int)$tenant->activeyearid,
            'status' => 'active',
            'startdate' => time(),
        ]);
    }
}

$rolloverform = null;
$studentprogressionform = null;
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
    if ((string)$tenant->tenanttype === 'school') {
        $placementoptions = [];
        foreach ((new student_placement_service())->list($tenant) as $placement) {
            if ((string)$placement->status !== 'active') {
                continue;
            }
            $placementoptions[(int)$placement->id] = fullname($placement)
                . ' - ' . format_string($placement->yearname)
                . ' - ' . format_string($placement->gradename)
                . ' / ' . format_string($placement->divisionname);
        }
        $studentprogressionform = new student_progression($pageurl, [
            'placements' => $placementoptions,
            'years' => $years,
            'grades' => tenantmaster_master_options((int)$tenant->id, 'grade'),
            'streams' => tenantmaster_master_options((int)$tenant->id, 'stream'),
            'divisions' => tenantmaster_master_options((int)$tenant->id, 'division'),
        ]);
        if ($data = $studentprogressionform->get_data()) {
            require_sesskey();
            $access->require('local/tenantmaster:manageacademic');
            (new student_progression_service())->plan($tenant, $data);
            redirect($pageurl, get_string('progressionplanned', 'local_tenantmaster'));
        }
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
    echo $OUTPUT->notification(get_string('selectinitialisedtenant', 'local_tenantmaster'), 'info', false);
    if (is_siteadmin()) {
        echo $OUTPUT->single_button(
            new moodle_url('/local/tenantmaster/index.php', ['section' => 'tenants']),
            get_string('managetenantmasterinstitutions', 'local_tenantmaster'),
            'get',
        );
        echo $OUTPUT->single_button(
            new moodle_url('/blocks/iomad_company_admin/company_edit_form.php', ['createnew' => 1]),
            get_string('createnativeiomadcompany', 'local_tenantmaster'),
            'get',
        );
    }
    echo tenantmaster_tenant_table($tenantrepository->list());
    echo $OUTPUT->footer();
    exit;
}

switch ($section) {
    case 'tenants':
        echo $OUTPUT->heading(get_string('managedinstitutions', 'local_tenantmaster'));
        echo tenantmaster_global_native_actions();
        echo tenantmaster_tenant_table($tenantrepository->list($companyid > 0 && !is_siteadmin() ? $companyid : 0));
        if ($adoptionform) {
            echo $OUTPUT->heading(get_string('initialiseexistingcompany', 'local_tenantmaster'), 3);
            $adoptionform->display();
        }
        break;
    case 'profile':
        echo $OUTPUT->heading(get_string('institutionmasterdata', 'local_tenantmaster'));
        echo tenantmaster_native_actions($tenant, 'company');
        echo tenantmaster_native_company_summary($tenant);
        echo $OUTPUT->heading(get_string('regulatoryandacademicmetadata', 'local_tenantmaster'), 3);
        $profileform->display();
        break;
    case 'academic':
        echo $OUTPUT->heading(get_string('academicmasters', 'local_tenantmaster'));
        echo tenantmaster_academic_year_table($tenant);
        echo $OUTPUT->heading(
            $yeareditid
                ? get_string('editacademicyear', 'local_tenantmaster')
                : get_string('addacademicyear', 'local_tenantmaster'),
            3,
        );
        $academicyearform->display();
        if ($schoolyearsetupform) {
            echo $OUTPUT->heading(get_string('schoolyearsetup', 'local_tenantmaster'), 3);
            $schoolyearsetupform->display();
        }
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
        echo $OUTPUT->heading(get_string('academiccourseprojections', 'local_tenantmaster'));
        echo tenantmaster_native_actions($tenant, 'courses');
        echo tenantmaster_mapping_table($tenant, ['core_course/category', 'core/course']);
        echo tenantmaster_course_copy_table($tenant);
        break;
    case 'classes':
        echo $OUTPUT->heading(get_string('classmanagement', 'local_tenantmaster'));
        echo tenantmaster_native_actions($tenant, 'people');
        echo tenantmaster_placement_table($tenant);
        echo $OUTPUT->heading(
            $placementeditid
                ? get_string('editplacement', 'local_tenantmaster')
                : get_string('addplacement', 'local_tenantmaster'),
            3,
        );
        $placementform->display();
        break;
    case 'progression':
        echo $OUTPUT->heading(get_string('progression', 'local_tenantmaster'));
        echo tenantmaster_policy_table($tenant, ['progression_rule']);
        echo tenantmaster_rollover_table($tenant);
        echo $OUTPUT->heading(get_string('academicroollover', 'local_tenantmaster'), 3);
        $rolloverform->display();
        if ($studentprogressionform) {
            echo tenantmaster_student_progression_table($tenant);
            echo $OUTPUT->heading(get_string('studentprogression', 'local_tenantmaster'), 3);
            $studentprogressionform->display();
        }
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
        'profile' => 'institutionmasterdata',
        'academic' => 'academicstructure',
        'courses' => 'academiccourseprojections',
        'classes' => 'classmanagement',
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

    $sections = ['dashboard'];
    if (is_siteadmin()) {
        $sections[] = 'tenants';
    }
    if ($companyid > 0) {
        $sections = array_merge($sections, [
            'profile',
            'academic',
            'courses',
        ]);
        if ($GLOBALS['DB']->get_field('local_tenantmaster_tenant', 'tenanttype', ['companyid' => $companyid]) === 'school') {
            $sections[] = 'classes';
        }
        $sections = array_merge($sections, [
            'progression',
            'imports',
            'sync',
            'validation',
            'audit',
        ]);
    }

    $tabs = [];
    foreach ($sections as $section) {
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
        . tenantmaster_native_actions($tenant, 'overview')
        . $actions . html_writer::table($table);
}

/**
 * Site-wide native IOMAD company actions.
 *
 * @return string
 */
function tenantmaster_global_native_actions(): string {
    global $OUTPUT;

    $actions = [
        [
            '/blocks/iomad_company_admin/company_edit_form.php',
            ['createnew' => 1],
            'createnativeiomadcompany',
            't/add',
        ],
        [
            '/blocks/iomad_company_admin/editcompanies.php',
            [],
            'managenativecompanies',
            'i/settings',
        ],
    ];
    $links = [];
    foreach ($actions as [$path, $params, $stringkey, $icon]) {
        $links[] = html_writer::link(
            new moodle_url($path, $params),
            $OUTPUT->pix_icon($icon, '') . html_writer::span(get_string($stringkey, 'local_tenantmaster')),
            ['class' => 'btn btn-secondary'],
        );
    }
    return html_writer::div(implode('', $links), 'd-flex flex-wrap gap-2 mb-3');
}

/**
 * Native IOMAD administration actions for the selected company.
 *
 * @param object $tenant Tenant.
 * @param string $area Action area.
 * @return string
 */
function tenantmaster_native_actions(object $tenant, string $area): string {
    global $OUTPUT;

    $context = \local_iomad\custom_context\context_company::instance((int)$tenant->companyid);
    $definitions = [
        'dashboard' => [
            '/blocks/iomad_company_admin/index.php',
            'openiomaddashboard',
            'i/dashboard',
            null,
        ],
        'company' => [
            '/blocks/iomad_company_admin/company_edit_form.php',
            'editnativecompany',
            'i/settings',
            'block/iomad_company_admin:company_edit',
        ],
        'departments' => [
            '/blocks/iomad_company_admin/company_departments.php',
            'managenativedepartments',
            'i/group',
            'block/iomad_company_admin:edit_departments',
        ],
        'profiles' => [
            '/blocks/iomad_company_admin/company_user_profiles.php',
            'manageusercustomfields',
            'i/field',
            'block/iomad_company_admin:company_user_profiles',
        ],
        'users' => [
            '/blocks/iomad_company_admin/editusers.php',
            'managenativeusers',
            'i/users',
            'block/iomad_company_admin:view_editusers',
        ],
        'createuser' => [
            '/blocks/iomad_company_admin/company_user_create_form.php',
            'createnativeuser',
            't/add',
            'block/iomad_company_admin:user_create',
        ],
        'managers' => [
            '/blocks/iomad_company_admin/company_managers_form.php',
            'managenativemanagers',
            'i/cohort',
            'block/iomad_company_admin:company_manager',
        ],
        'courses' => [
            '/blocks/iomad_company_admin/iomad_courses_form.php',
            'managenativecourses',
            'i/course',
            'block/iomad_company_admin:viewcourses',
        ],
        'createcourse' => [
            '/blocks/iomad_company_admin/company_course_create_form.php',
            'createnativecourse',
            't/add',
            'block/iomad_company_admin:createcourse',
        ],
        'groups' => [
            '/blocks/iomad_company_admin/company_groups_create_form.php',
            'managenativegroups',
            'i/group',
            'block/iomad_company_admin:edit_groups',
        ],
        'licences' => [
            '/blocks/iomad_company_admin/company_license_list.php',
            'managenativelicences',
            'i/key',
            'block/iomad_company_admin:view_licenses',
        ],
    ];
    $areas = match ($area) {
        'company' => ['dashboard', 'company', 'departments', 'profiles'],
        'people' => ['dashboard', 'users', 'createuser', 'managers', 'groups'],
        'courses' => ['dashboard', 'courses', 'createcourse', 'groups', 'licences'],
        default => ['dashboard', 'company', 'users', 'courses', 'licences'],
    };

    $links = [];
    foreach ($areas as $key) {
        [$path, $stringkey, $icon, $capability] = $definitions[$key];
        if ($capability && !is_siteadmin() && !has_capability($capability, $context)) {
            continue;
        }
        $links[] = html_writer::link(
            new moodle_url($path, ['company' => $tenant->companyid, 'companyid' => $tenant->companyid]),
            $OUTPUT->pix_icon($icon, '') . html_writer::span(get_string($stringkey, 'local_tenantmaster')),
            ['class' => $key === 'dashboard' ? 'btn btn-primary' : 'btn btn-secondary'],
        );
    }
    return html_writer::div(implode('', $links), 'd-flex flex-wrap gap-2 mb-3');
}

/**
 * Read-only native company summary.
 *
 * @param object $tenant Tenant.
 * @return string
 */
function tenantmaster_native_company_summary(object $tenant): string {
    global $DB;

    $company = $DB->get_record('local_iomad_companies', ['id' => $tenant->companyid], '*', MUST_EXIST);
    $table = new html_table();
    $table->head = [
        get_string('nativeiomadfield', 'local_tenantmaster'),
        get_string('value'),
    ];
    $table->data = [
        [get_string('institutionname', 'local_tenantmaster'), format_string($company->name)],
        [get_string('shortname'), s($company->shortname)],
        [get_string('nativecompanycode', 'local_tenantmaster'), s($company->code)],
        [get_string('hostname', 'local_tenantmaster'), s($company->hostname)],
        [get_string('city'), s($company->city)],
        [get_string('country'), s($company->country)],
        [get_string('theme'), s($company->theme ?: get_string('default'))],
    ];
    return html_writer::table($table);
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
        get_string('actions'),
    ];
    foreach ($records as $record) {
        $table->data[] = [
            s($record->code),
            format_string($record->name),
            userdate($record->startdate, get_string('strftimedatefullshort')),
            userdate($record->enddate, get_string('strftimedatefullshort')),
            $record->iscurrent ? get_string('yes') : get_string('no'),
            s($record->status),
            html_writer::link(
                new moodle_url('/local/tenantmaster/index.php', [
                    'section' => 'academic',
                    'companyid' => $tenant->companyid,
                    'yeareditid' => $record->id,
                ]),
                get_string('edit'),
            ),
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

/**
 * Active academic-master options with explicit year scope.
 *
 * @param int $tenantid Tenant.
 * @param string $type Master type.
 * @return array<int, string>
 */
function tenantmaster_master_options(int $tenantid, string $type): array {
    global $DB;

    $records = $DB->get_records_sql(
        "SELECT m.*, y.name AS yearname
           FROM {local_tenantmaster_master} m
      LEFT JOIN {local_tenantmaster_acadyear} y ON y.id = m.acadyearid
          WHERE m.tenantid = :tenantid
            AND m.mastertype = :mastertype
            AND m.active = 1
       ORDER BY m.sortorder, m.name, y.startdate",
        ['tenantid' => $tenantid, 'mastertype' => $type],
    );
    $options = [];
    foreach ($records as $record) {
        $scope = $record->yearname
            ? format_string($record->yearname)
            : get_string('sharedallacademicyears', 'local_tenantmaster');
        $options[(int)$record->id] = format_string($record->name) . ' [' . $scope . ']';
    }
    return $options;
}

/**
 * Shared source masters used by the school-year generator.
 *
 * @param int $tenantid Tenant.
 * @param string $type Master type.
 * @return array<int, string>
 */
function tenantmaster_shared_master_options(int $tenantid, string $type): array {
    global $DB;

    $records = $DB->get_records(
        'local_tenantmaster_master',
        [
            'tenantid' => $tenantid,
            'mastertype' => $type,
            'acadyearid' => 0,
            'active' => 1,
        ],
        'sortorder, name',
    );
    return array_map(
        static fn(object $record): string => format_string($record->name),
        $records,
    );
}

/**
 * School class placements and their native cohort mappings.
 *
 * @param object $tenant Tenant.
 * @return string
 */
function tenantmaster_placement_table(object $tenant): string {
    $records = (new student_placement_service())->list($tenant);
    $table = new html_table();
    $table->head = [
        get_string('student', 'local_tenantmaster'),
        get_string('academicyear', 'local_tenantmaster'),
        get_string('mastertype_grade', 'local_tenantmaster'),
        get_string('mastertype_division', 'local_tenantmaster'),
        get_string('mastertype_medium', 'local_tenantmaster'),
        get_string('mastertype_stream', 'local_tenantmaster'),
        get_string('rollnumber', 'local_tenantmaster'),
        get_string('status'),
        get_string('nativecohort', 'local_tenantmaster'),
        get_string('actions'),
    ];
    foreach ($records as $record) {
        $table->data[] = [
            fullname($record) . ' [' . s($record->useridnumber) . ']',
            format_string($record->yearname),
            format_string($record->gradename),
            format_string($record->divisionname),
            format_string($record->mediumname ?? ''),
            format_string($record->streamname ?? ''),
            s($record->rollnumber ?? ''),
            get_string('placementstatus_' . $record->status, 'local_tenantmaster'),
            $record->cohortid ? '#' . (int)$record->cohortid : '-',
            html_writer::link(
                new moodle_url('/local/tenantmaster/index.php', [
                    'section' => 'classes',
                    'companyid' => $tenant->companyid,
                    'placementeditid' => $record->id,
                ]),
                get_string('edit'),
            ),
        ];
    }
    return html_writer::table($table);
}

/**
 * Reviewed learner progression decisions.
 *
 * @param object $tenant Tenant.
 * @return string
 */
function tenantmaster_student_progression_table(object $tenant): string {
    global $DB;

    $records = $DB->get_records_sql(
        "SELECT p.*, u.firstname, u.lastname, u.idnumber,
                fy.name AS fromyearname, ty.name AS toyearname,
                sg.name AS sourcegradename, tg.name AS targetgradename
           FROM {local_tenantmaster_progress} p
           JOIN {local_tenantmaster_placement} sp ON sp.id = p.sourceplaceid
           JOIN {user} u ON u.id = sp.userid
           JOIN {local_tenantmaster_acadyear} fy ON fy.id = sp.acadyearid
           JOIN {local_tenantmaster_acadyear} ty ON ty.id = p.toyearid
           JOIN {local_tenantmaster_master} sg ON sg.id = sp.gradeid
      LEFT JOIN {local_tenantmaster_master} tg ON tg.id = p.targetgradeid
          WHERE p.tenantid = :tenantid
       ORDER BY p.timecreated DESC",
        ['tenantid' => $tenant->id],
    );
    $table = new html_table();
    $table->head = [
        get_string('student', 'local_tenantmaster'),
        get_string('fromyear', 'local_tenantmaster'),
        get_string('toyear', 'local_tenantmaster'),
        get_string('progressiondecision', 'local_tenantmaster'),
        get_string('targetgrade', 'local_tenantmaster'),
        get_string('status'),
        get_string('actions'),
    ];
    foreach ($records as $record) {
        $actions = '-';
        if ($record->status === 'planned' && $record->decision !== 'conditional') {
            $actions = tenantmaster_action_button(
                $tenant,
                'applyprogression',
                get_string('applyapprovedprogression', 'local_tenantmaster'),
                'primary',
                ['progressid' => $record->id],
            );
        }
        $table->data[] = [
            fullname($record) . ' [' . s($record->idnumber) . ']',
            format_string($record->fromyearname) . ' / ' . format_string($record->sourcegradename),
            format_string($record->toyearname),
            get_string('decision_' . $record->decision, 'local_tenantmaster'),
            format_string($record->targetgradename ?? ''),
            s($record->status),
            $actions,
        ];
    }
    return html_writer::table($table);
}

/**
 * Idempotent native course-content copy history.
 *
 * @param object $tenant Tenant.
 * @return string
 */
function tenantmaster_course_copy_table(object $tenant): string {
    global $DB, $OUTPUT;

    $records = $DB->get_records_sql(
        "SELECT cp.*, source.fullname AS sourcename, target.fullname AS targetname
           FROM {local_tenantmaster_crscopy} cp
           JOIN {course} source ON source.id = cp.sourcecourseid
           JOIN {course} target ON target.id = cp.targetcourseid
          WHERE cp.tenantid = :tenantid
       ORDER BY cp.timecreated DESC",
        ['tenantid' => $tenant->id],
    );
    if (!$records) {
        return '';
    }
    $table = new html_table();
    $table->head = [
        get_string('sourcecourse', 'local_tenantmaster'),
        get_string('targetcourse', 'local_tenantmaster'),
        get_string('status'),
        get_string('time'),
    ];
    foreach ($records as $record) {
        $table->data[] = [
            format_string($record->sourcename),
            format_string($record->targetname),
            s($record->status),
            $record->timefinished ? userdate($record->timefinished) : userdate($record->timecreated),
        ];
    }
    return $OUTPUT->heading(get_string('coursecopyhistory', 'local_tenantmaster'), 3)
        . html_writer::table($table);
}
