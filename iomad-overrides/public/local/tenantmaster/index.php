<?php
// This file is part of Moodle - http://moodle.org/

use local_tenantmaster\form\academic_year;
use local_tenantmaster\form\catalogue_item;
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
use local_tenantmaster\local\catalogue_service;
use local_tenantmaster\local\default_service;
use local_tenantmaster\local\drift_service;
use local_tenantmaster\local\import_schema;
use local_tenantmaster\local\import_service;
use local_tenantmaster\local\json;
use local_tenantmaster\local\master_repository;
use local_tenantmaster\local\master_service;
use local_tenantmaster\local\native_data_service;
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
$catalogueeditid = optional_param('catalogueeditid', 0, PARAM_INT);
$catalogueoperationid = optional_param('catalogueoperationid', 0, PARAM_INT);
$catalogueoperation = optional_param('catalogueoperation', '', PARAM_ALPHA);
$showremoved = optional_param('showremoved', 0, PARAM_BOOL);
$cataloguescope = optional_param('scope', 'shared', PARAM_ALPHA);
$academicview = optional_param('academicview', 'masters', PARAM_ALPHA);
$typefilter = optional_param('type', '', PARAM_ALPHANUMEXT);
$search = optional_param('search', '', PARAM_TEXT);
$visibility = optional_param('visibility', 'all', PARAM_ALPHA);
$allowedsections = [
    'dashboard',
    'tenants',
    'catalogue',
    'profile',
    'organisation',
    'academic',
    'courses',
    'people',
    'access',
    'assessments',
    'certificates',
    'classes',
    'progression',
    'imports',
    'sync',
    'validation',
    'audit',
];
if (!in_array($section, $allowedsections, true)) {
    throw new invalid_parameter_exception('Unknown Tenant Master section.');
}
if ($typefilter !== '' && !array_key_exists($typefilter, catalog::MASTER_TYPES)) {
    throw new invalid_parameter_exception('Unknown academic master type.');
}
if (!array_key_exists($cataloguescope, catalogue_service::SCOPES)) {
    throw new invalid_parameter_exception('Unknown catalogue scope.');
}
if (!in_array($catalogueoperation, ['', 'remove', 'restore'], true)) {
    throw new invalid_parameter_exception('Unknown catalogue operation.');
}
if (!in_array($academicview, ['masters', 'years'], true)) {
    throw new invalid_parameter_exception('Unknown academic view.');
}
if (($catalogueoperationid > 0) !== ($catalogueoperation !== '')) {
    throw new invalid_parameter_exception('Catalogue operation and item must be supplied together.');
}
if ($section === 'catalogue') {
    require_capability('local/tenantmaster:managecatalogue', context_system::instance());
}
if (!in_array($visibility, ['all', 'visible', 'hidden'], true)) {
    throw new invalid_parameter_exception('Unknown course visibility filter.');
}
$access = access::resolve($companyid);
$companyid = $access->companyid();
$tenantrepository = new tenant_repository();
$tenant = $companyid > 0 ? $tenantrepository->get_by_company($companyid) : null;
$notice = '';

// Keep native IOMAD administration links on the selected site-admin company.
if (is_siteadmin() && $companyid > 0) {
    $SESSION->currenteditingcompany = $companyid;
}

$urlparams = ['section' => $section];
if ($companyid > 0) {
    $urlparams['companyid'] = $companyid;
}
if ($section === 'academic' && $academicview === 'years') {
    $urlparams['academicview'] = 'years';
}
if ($section === 'academic' && $typefilter !== '') {
    $urlparams['type'] = $typefilter;
}
$pageurl = new moodle_url('/local/tenantmaster/index.php', $urlparams);
$PAGE->set_url($pageurl);
$PAGE->set_context($section === 'catalogue' ? context_system::instance() : $access->context());
$PAGE->set_pagelayout('admin');
$PAGE->add_body_class('tenantmaster-section-' . $section);
$sectionstring = tenantmaster_section_string($section);
$sectionlabel = get_string($sectionstring, 'local_tenantmaster');
if ($section === 'academic' && $academicview === 'years') {
    $sectionlabel = get_string('academicyears', 'local_tenantmaster');
} else if ($section === 'academic' && isset(catalog::MASTER_TYPES[$typefilter])) {
    $sectionlabel = get_string(catalog::MASTER_TYPES[$typefilter], 'local_tenantmaster');
}
$PAGE->set_title($sectionlabel);
$PAGE->set_heading($tenant
    ? format_string($DB->get_field('local_iomad_companies', 'name', ['id' => $tenant->companyid]))
    : get_string('pluginname', 'local_tenantmaster'));
$PAGE->requires->js_call_amd('local_tenantmaster/table_tools', 'init', [[
    'filter' => get_string('filtertablerows', 'local_tenantmaster'),
    'clear' => get_string('clearfilter', 'local_tenantmaster'),
    'ascending' => get_string('sortascending', 'local_tenantmaster'),
    'descending' => get_string('sortdescending', 'local_tenantmaster'),
    'actions' => get_string('actions'),
    'previous' => get_string('previous'),
    'next' => get_string('next'),
    'page' => get_string('paginationpage', 'local_tenantmaster'),
    'of' => get_string('paginationof', 'local_tenantmaster'),
    'records' => get_string('paginationrecords', 'local_tenantmaster'),
]]);
$PAGE->navbar->add(get_string('pluginname', 'local_tenantmaster'), new moodle_url('/local/tenantmaster/index.php'));
$PAGE->navbar->add($sectionlabel);

if ($action === 'cataloguetoggle') {
    require_sesskey();
    require_capability('local/tenantmaster:managecatalogue', context_system::instance());
    (new catalogue_service())->set_active(
        required_param('catalogueid', PARAM_INT),
        required_param('active', PARAM_BOOL),
    );
    redirect(
        new moodle_url('/local/tenantmaster/index.php', [
            'section' => 'catalogue',
            'scope' => $cataloguescope,
            'type' => $typefilter,
            'showremoved' => $showremoved ? 1 : 0,
        ]),
        get_string('cataloguechangesqueued', 'local_tenantmaster'),
    );
}
if (in_array($action, ['catalogueremove', 'cataloguerestore'], true)) {
    require_sesskey();
    require_capability('local/tenantmaster:managecatalogue', context_system::instance());
    $catalogueservice = new catalogue_service();
    $catalogueid = required_param('catalogueid', PARAM_INT);
    if ($action === 'catalogueremove') {
        $catalogueservice->remove($catalogueid);
        $notice = get_string('catalogueremovequeued', 'local_tenantmaster');
    } else {
        $catalogueservice->restore($catalogueid);
        $notice = get_string('cataloguerestorequeued', 'local_tenantmaster');
    }
    redirect(
        new moodle_url('/local/tenantmaster/index.php', [
            'section' => 'catalogue',
            'scope' => $cataloguescope,
            'type' => $typefilter,
            'showremoved' => $showremoved ? 1 : 0,
        ]),
        $notice,
    );
}

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
        case 'syncmaster':
            $access->require('local/tenantmaster:sync');
            (new queue_service())->sync_master(
                (int)$tenant->id,
                required_param('masterid', PARAM_INT),
            );
            $notice = get_string('mastersyncqueued', 'local_tenantmaster');
            break;
        case 'synctype':
            $access->require('local/tenantmaster:sync');
            $synctype = required_param('mastertype', PARAM_ALPHANUMEXT);
            $queuedcount = (new queue_service())->sync_master_type(
                (int)$tenant->id,
                $synctype,
            );
            $notice = get_string('mastertypesyncqueued', 'local_tenantmaster', $queuedcount);
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
if (is_siteadmin() && $section === 'tenants') {
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

$catalogueform = null;
$catalogueitems = [];
$catalogueimpact = null;
if (is_siteadmin() && $section === 'catalogue') {
    require_capability('local/tenantmaster:managecatalogue', context_system::instance());
    $catalogueservice = new catalogue_service();
    $editingcatalogue = $catalogueeditid > 0 ? $catalogueservice->get($catalogueeditid) : null;
    if ($editingcatalogue) {
        $cataloguescope = (string)$editingcatalogue->scope;
        $typefilter = (string)$editingcatalogue->mastertype;
    }
    if ($catalogueoperationid > 0) {
        $catalogueimpact = $catalogueservice->removal_impact($catalogueoperationid);
        $expectedoperation = empty($catalogueimpact->item->deleted) ? 'remove' : 'restore';
        if ($catalogueoperation !== $expectedoperation) {
            throw new invalid_parameter_exception('Catalogue operation does not match the item state.');
        }
        $cataloguescope = (string)$catalogueimpact->item->scope;
        $typefilter = (string)$catalogueimpact->item->mastertype;
    }
    $catalogueitems = $catalogueservice->list($cataloguescope, $typefilter, !empty($showremoved));
    $catalogueformtype = $typefilter ?: 'board';
    $parentoptions = [0 => get_string('none')];
    foreach ($catalogueservice->list($cataloguescope, $catalogueformtype) as $parent) {
        if (!$editingcatalogue || (int)$parent->id !== (int)$editingcatalogue->id) {
            $parentoptions[(int)$parent->id] = format_string($parent->name) . ' [' . s($parent->code) . ']';
        }
    }
    $catalogueform = new catalogue_item(new moodle_url('/local/tenantmaster/index.php', [
        'section' => 'catalogue',
        'scope' => $cataloguescope,
        'type' => $typefilter,
        'catalogueeditid' => $catalogueeditid,
    ]), [
        'editing' => $editingcatalogue !== null,
        'parents' => $parentoptions,
    ]);
    if ($editingcatalogue) {
        $parentitemid = 0;
        foreach ($catalogueservice->list($cataloguescope, $typefilter) as $candidate) {
            if ((string)$candidate->externalid === (string)$editingcatalogue->parentexternalid) {
                $parentitemid = (int)$candidate->id;
                break;
            }
        }
        $editingcatalogue->parentitemid = $parentitemid;
        $catalogueform->set_data($editingcatalogue);
    } else {
        $catalogueform->set_data((object)[
            'scope' => $cataloguescope,
            'mastertype' => $catalogueformtype,
            'payloadjson' => '{}',
            'active' => 1,
            'sortorder' => count($catalogueitems) + 1,
        ]);
    }
    if ($data = $catalogueform->get_data()) {
        require_sesskey();
        if ($editingcatalogue) {
            $data->id = $editingcatalogue->id;
            $data->scope = $editingcatalogue->scope;
            $data->mastertype = $editingcatalogue->mastertype;
            $data->externalid = $editingcatalogue->externalid;
            $data->code = $editingcatalogue->code;
        }
        $savedcatalogue = $catalogueservice->save($data);
        redirect(
            new moodle_url('/local/tenantmaster/index.php', [
                'section' => 'catalogue',
                'scope' => $savedcatalogue->scope,
                'type' => $savedcatalogue->mastertype,
            ]),
            get_string('catalogueitemsaved', 'local_tenantmaster'),
        );
    }
}

$profileform = null;
if ($tenant && $section === 'profile') {
    $profileform = new tenant_profile($pageurl, [
        'editing' => true,
        'tenanttype' => (string)$tenant->tenanttype,
    ]);
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
    $editingyear = null;
    $academicyearform = new academic_year(
        new moodle_url($pageurl, ['yeareditid' => $yeareditid]),
        ['editing' => $yeareditid > 0],
    );
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
    if ($data = $academicyearform->get_data()) {
        require_sesskey();
        $access->require('local/tenantmaster:manageacademic');
        if ($editingyear) {
            $data->yearid = $editingyear->id;
            $data->yearexternalid = $editingyear->externalid;
            $data->yearcode = $editingyear->code;
        }
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
    $editingmaster = $editid > 0
        ? $masterrepository->get((int)$tenant->id, $editid)
        : null;
    $masterform = new master(new moodle_url($pageurl, [
        'editid' => $editid,
        'type' => $typefilter,
    ]), [
        'parents' => $parentoptions,
        'editing' => $editid > 0,
        'years' => $yearoptions,
    ]);
    if ($editingmaster) {
        $masterform->set_data($editingmaster);
    } else {
        $masterform->set_data((object)[
            'tenantid' => $tenant->id,
            'acadyearid' => 0,
            'mastertype' => $typefilter ?: 'grade',
            'payloadjson' => '{}',
            'active' => 1,
        ]);
    }
    if ($data = $masterform->get_data()) {
        require_sesskey();
        $access->require('local/tenantmaster:manageacademic');
        if ($editingmaster) {
            $data->id = $editingmaster->id;
            $data->tenantid = $editingmaster->tenantid;
            $data->acadyearid = $editingmaster->acadyearid;
            $data->mastertype = $editingmaster->mastertype;
            $data->externalid = $editingmaster->externalid;
            $data->code = $editingmaster->code;
        }
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
    $editingplacement = $placementeditid > 0
        ? $DB->get_record('local_tenantmaster_placement', [
            'id' => $placementeditid,
            'tenantid' => $tenant->id,
        ], '*', MUST_EXIST)
        : null;
    $placementform = new student_placement(new moodle_url($pageurl, [
        'placementeditid' => $placementeditid,
    ]), [
        'editing' => $placementeditid > 0,
        'users' => $useroptions,
        'years' => $yearoptions,
        'boards' => tenantmaster_master_options((int)$tenant->id, 'board'),
        'mediums' => tenantmaster_master_options((int)$tenant->id, 'medium'),
        'grades' => tenantmaster_master_options((int)$tenant->id, 'grade'),
        'streams' => tenantmaster_master_options((int)$tenant->id, 'stream'),
        'divisions' => tenantmaster_master_options((int)$tenant->id, 'division'),
    ]);
    if ($editingplacement) {
        $placementform->set_data($editingplacement);
    } else if ((int)$tenant->activeyearid > 0) {
        $placementform->set_data((object)[
            'acadyearid' => (int)$tenant->activeyearid,
            'status' => 'active',
            'startdate' => time(),
        ]);
    }
    if ($data = $placementform->get_data()) {
        require_sesskey();
        $access->require('local/tenantmaster:managepeople');
        if ($editingplacement) {
            $data->id = $editingplacement->id;
            $data->userid = $editingplacement->userid;
            $data->acadyearid = $editingplacement->acadyearid;
        }
        $savedplacement = (new student_placement_service())->save($tenant, $data);
        redirect(
            $pageurl,
            get_string('placementsaved', 'local_tenantmaster', $savedplacement->provisionedcourses),
        );
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
            $data->toyearid = $data->progressiontoyearid;
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
echo tenantmaster_context_bar($tenantrepository->list(), $section, $companyid, [
    'academicview' => $section === 'academic' ? $academicview : '',
    'type' => $section === 'academic' ? $typefilter : '',
]);
echo tenantmaster_workspace_navigation($section, $tenant, $academicview, $typefilter);
if ($notice !== '') {
    echo $OUTPUT->notification($notice, 'success', false);
}
echo html_writer::start_div('tenantmaster-page tenantmaster-page--' . $section);
if (!$tenant && !(is_siteadmin() && in_array($section, ['dashboard', 'tenants', 'catalogue'], true))) {
    echo tenantmaster_section_header($section, null, $academicview, $typefilter);
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
    echo html_writer::end_div();
    echo $OUTPUT->footer();
    exit;
}

if ($section !== 'dashboard') {
    echo tenantmaster_section_header($section, $tenant, $academicview, $typefilter);
}

switch ($section) {
    case 'tenants':
        echo tenantmaster_global_native_actions();
        echo tenantmaster_tenant_table($tenantrepository->list($companyid > 0 && !is_siteadmin() ? $companyid : 0));
        if (is_siteadmin()) {
            echo $OUTPUT->heading(get_string('initialiseexistingcompany', 'local_tenantmaster'), 3);
        }
        if ($adoptionform) {
            $adoptionform->display();
        } else if (is_siteadmin()) {
            echo $OUTPUT->notification(get_string('noeligiblecompanies', 'local_tenantmaster'), 'info', false);
        }
        break;
    case 'catalogue':
        if ($catalogueimpact) {
            echo tenantmaster_catalogue_confirmation(
                $catalogueimpact,
                $catalogueoperation,
                !empty($showremoved),
            );
            break;
        }
        echo tenantmaster_catalogue_navigation($cataloguescope, $typefilter);
        echo tenantmaster_catalogue_removed_filter($cataloguescope, $typefilter, !empty($showremoved));
        echo tenantmaster_catalogue_table(
            $catalogueitems,
            $cataloguescope,
            $typefilter,
            !empty($showremoved),
        );
        echo $OUTPUT->heading(
            $catalogueeditid
                ? get_string('editcatalogueitem', 'local_tenantmaster')
                : get_string('addcatalogueitem', 'local_tenantmaster'),
            3,
        );
        $catalogueform->display();
        break;
    case 'profile':
        echo tenantmaster_origin_badge(true);
        echo tenantmaster_native_actions($tenant, 'company');
        echo tenantmaster_native_company_summary($tenant);
        echo $OUTPUT->heading(get_string('regulatoryandacademicmetadata', 'local_tenantmaster'), 3);
        echo tenantmaster_origin_badge(false);
        $profileform->display();
        break;
    case 'organisation':
        echo tenantmaster_origin_badge(true);
        echo tenantmaster_native_actions($tenant, 'company');
        echo tenantmaster_department_table($tenant);
        break;
    case 'academic':
        echo tenantmaster_tenant_scope($tenant);
        echo tenantmaster_origin_badge(false);
        echo tenantmaster_academic_navigation($tenant, $academicview, $typefilter);
        if ($academicview === 'years') {
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
            break;
        }
        echo tenantmaster_academic_actions($tenant, $typefilter);
        echo tenantmaster_master_table(
            $tenant,
            $masterrepository->list((int)$tenant->id, $typefilter),
            $typefilter === '',
        );
        $masterlabel = $typefilter !== ''
            ? get_string(catalog::MASTER_TYPES[$typefilter], 'local_tenantmaster')
            : get_string('academicmaster', 'local_tenantmaster');
        echo $OUTPUT->heading(get_string(
            $editid ? 'editmastertype' : 'addmastertype',
            'local_tenantmaster',
            $masterlabel,
        ), 3);
        $masterform->display();
        break;
    case 'courses':
        echo tenantmaster_origin_badge(true);
        echo tenantmaster_native_actions($tenant, 'courses');
        echo tenantmaster_course_filter($tenant, $search, $visibility);
        echo tenantmaster_native_course_table($tenant, $search, $visibility);
        echo $OUTPUT->heading(get_string('managedprojections', 'local_tenantmaster'), 3);
        echo tenantmaster_origin_badge(false);
        echo tenantmaster_mapping_table($tenant, ['core_course/category', 'core/course']);
        echo tenantmaster_course_custom_field_table();
        echo tenantmaster_course_copy_table($tenant);
        break;
    case 'people':
        echo tenantmaster_origin_badge(true);
        echo tenantmaster_native_actions($tenant, 'people');
        echo tenantmaster_people_filter($tenant, $search);
        echo tenantmaster_native_user_table($tenant, $search);
        echo $OUTPUT->heading(get_string('additionaluserfields', 'local_tenantmaster'), 3);
        echo tenantmaster_user_profile_field_table($tenant);
        break;
    case 'access':
        echo tenantmaster_origin_badge(true);
        echo tenantmaster_native_actions($tenant, 'courses');
        echo tenantmaster_native_access_tables($tenant);
        break;
    case 'assessments':
        echo tenantmaster_origin_badge(false);
        echo tenantmaster_master_domain_actions($tenant, 'assessment_policy');
        echo tenantmaster_policy_table($tenant, ['assessment_policy', 'attendance_policy']);
        echo tenantmaster_native_actions($tenant, 'courses');
        break;
    case 'certificates':
        echo tenantmaster_origin_badge(false);
        echo tenantmaster_master_domain_actions($tenant, 'certificate_rule');
        echo tenantmaster_policy_table($tenant, ['certificate_rule']);
        echo tenantmaster_native_actions($tenant, 'courses');
        break;
    case 'classes':
        echo tenantmaster_origin_badge(false);
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
        echo tenantmaster_origin_badge(false);
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
        echo tenantmaster_origin_badge(false);
        echo tenantmaster_import_guide($tenant);
        echo $OUTPUT->heading(get_string('uploadpackage', 'local_tenantmaster'), 3);
        echo html_writer::start_div('tenantmaster-import-upload');
        $importform->display();
        echo html_writer::end_div();
        echo $OUTPUT->heading(get_string('importbatchhistory', 'local_tenantmaster'), 3);
        echo tenantmaster_import_table($tenant);
        break;
    case 'sync':
        echo tenantmaster_origin_badge(false);
        echo tenantmaster_action_button($tenant, 'syncall', get_string('syncall', 'local_tenantmaster'), 'primary');
        echo tenantmaster_sync_tables($tenant);
        break;
    case 'validation':
        echo tenantmaster_origin_badge(false);
        echo tenantmaster_action_button($tenant, 'validate', get_string('validateall', 'local_tenantmaster'), 'secondary');
        echo tenantmaster_validation_table($tenant);
        break;
    case 'audit':
        echo tenantmaster_origin_badge(false);
        echo tenantmaster_audit_table($tenant);
        break;
    default:
        echo $tenant ? tenantmaster_dashboard($tenant) : tenantmaster_global_dashboard();
        break;
}

echo html_writer::end_div();
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
        'catalogue' => 'globalmastertemplates',
        'profile' => 'institutionmasterdata',
        'organisation' => 'organisation',
        'academic' => 'tenantmasterdata',
        'courses' => 'academiccourseprojections',
        'people' => 'usersandroles',
        'access' => 'cohortsandenrolments',
        'assessments' => 'assessments',
        'certificates' => 'certificates',
        'classes' => 'classmanagement',
        'progression' => 'progression',
        'imports' => 'imports',
        'sync' => 'synchronization',
        'validation' => 'validation',
        'audit' => 'audit',
    ][$section] ?? 'dashboard';
}

/**
 * Describe how one section reads and changes platform records.
 *
 * @param string $section Section.
 * @return array{mode: string, label: string, description: string, icon: string}
 */
function tenantmaster_section_sync_definition(string $section): array {
    $modes = [
        'automatic' => ['syncmode_automatic', 'fa-bolt'],
        'manual' => ['syncmode_manual', 'fa-pen-to-square'],
        'mixed' => ['syncmode_mixed', 'fa-arrows-rotate'],
        'review' => ['syncmode_review', 'fa-clipboard-check'],
        'live' => ['syncmode_live', 'fa-eye'],
    ];
    $sectionmodes = [
        'dashboard' => 'live',
        'tenants' => 'manual',
        'catalogue' => 'automatic',
        'profile' => 'mixed',
        'organisation' => 'manual',
        'academic' => 'automatic',
        'courses' => 'mixed',
        'people' => 'manual',
        'access' => 'mixed',
        'assessments' => 'mixed',
        'certificates' => 'mixed',
        'classes' => 'automatic',
        'progression' => 'review',
        'imports' => 'review',
        'sync' => 'mixed',
        'validation' => 'mixed',
        'audit' => 'live',
    ];
    $mode = $sectionmodes[$section] ?? 'live';
    [$label, $icon] = $modes[$mode];
    $descriptionkey = 'syncbehaviour_' . $section;
    if (!get_string_manager()->string_exists($descriptionkey, 'local_tenantmaster')) {
        $descriptionkey = 'syncbehaviour_dashboard';
    }
    return [
        'mode' => $mode,
        'label' => get_string($label, 'local_tenantmaster'),
        'description' => get_string($descriptionkey, 'local_tenantmaster'),
        'icon' => $icon,
    ];
}

/**
 * Render a compact processing-mode badge.
 *
 * @param array{mode: string, label: string, description: string, icon: string} $definition Definition.
 * @return string
 */
function tenantmaster_sync_mode_badge(array $definition): string {
    return html_writer::span(
        html_writer::span('', 'fa ' . $definition['icon'])
            . html_writer::span($definition['label']),
        'tenantmaster-sync-mode tenantmaster-sync-mode--' . $definition['mode'],
        ['title' => $definition['description']],
    );
}

/**
 * Render a consistent product header for one Tenant Master work area.
 *
 * @param string $section Section.
 * @param object|null $tenant Tenant.
 * @param string $academicview Academic sub-view.
 * @param string $mastertype Academic master type.
 * @return string
 */
function tenantmaster_section_header(
    string $section,
    ?object $tenant,
    string $academicview = 'masters',
    string $mastertype = '',
): string {
    global $DB;

    $definitions = [
        'dashboard' => ['pluginname', 'sectionhelp_dashboard', 'fa-building-columns', 'mixed'],
        'tenants' => ['managedinstitutions', 'sectionhelp_tenants', 'fa-building-columns', 'native'],
        'catalogue' => ['globalmastertemplates', 'sectionhelp_catalogue', 'fa-layer-group', 'custom'],
        'profile' => ['institutionmasterdata', 'sectionhelp_profile', 'fa-building', 'mixed'],
        'organisation' => ['organisation', 'sectionhelp_organisation', 'fa-diagram-project', 'native'],
        'academic' => ['tenantmasterdata', 'sectionhelp_academic', 'fa-book-open', 'custom'],
        'courses' => ['academiccourseprojections', 'sectionhelp_courses', 'fa-graduation-cap', 'mixed'],
        'people' => ['usersandroles', 'sectionhelp_people', 'fa-users', 'native'],
        'access' => ['cohortsandenrolments', 'sectionhelp_access', 'fa-link', 'native'],
        'assessments' => ['assessments', 'sectionhelp_assessments', 'fa-list-check', 'mixed'],
        'certificates' => ['certificates', 'sectionhelp_certificates', 'fa-certificate', 'mixed'],
        'classes' => ['classmanagement', 'sectionhelp_classes', 'fa-people-group', 'mixed'],
        'progression' => ['progression', 'sectionhelp_progression', 'fa-chart-line', 'custom'],
        'imports' => ['imports', 'sectionhelp_imports', 'fa-file-import', 'custom'],
        'sync' => ['synchronization', 'sectionhelp_sync', 'fa-arrows-rotate', 'mixed'],
        'validation' => ['validation', 'sectionhelp_validation', 'fa-shield', 'mixed'],
        'audit' => ['audit', 'sectionhelp_audit', 'fa-clipboard', 'custom'],
    ];
    [$titlekey, $descriptionkey, $icon, $origin] = $definitions[$section]
        ?? ['pluginname', 'sectionhelp_dashboard', 'fa-building-columns', 'custom'];
    $title = get_string($titlekey, 'local_tenantmaster');
    if ($section === 'academic' && $academicview === 'years') {
        $title = get_string('academicyears', 'local_tenantmaster');
    } else if ($section === 'academic' && isset(catalog::MASTER_TYPES[$mastertype])) {
        $title = get_string(catalog::MASTER_TYPES[$mastertype], 'local_tenantmaster');
    }

    $scope = get_string('sitewideadministration', 'local_tenantmaster');
    if ($tenant && !in_array($section, ['catalogue', 'tenants'], true)) {
        $company = $DB->get_record(
            'local_iomad_companies',
            ['id' => $tenant->companyid],
            'name, code',
            MUST_EXIST,
        );
        $scope = format_string($company->name) . ' [' . s($company->code) . ']';
    }
    $owner = get_string('origin_' . $origin, 'local_tenantmaster');
    $syncdefinition = tenantmaster_section_sync_definition($section);
    $meta = html_writer::span(
        html_writer::span('', 'fa fa-building')
            . html_writer::span($scope),
        'tenantmaster-section-header__scope',
    ) . html_writer::div(
        html_writer::span(get_string('dataownership', 'local_tenantmaster'),
            'tenantmaster-section-header__meta-label')
            . html_writer::span(
                $owner,
                'tenantmaster-section-header__owner tenantmaster-section-header__owner--' . $origin,
            ),
        'tenantmaster-section-header__meta-item',
    );
    $syncsummary = html_writer::div(
        html_writer::span(get_string('changeprocessing', 'local_tenantmaster'),
            'tenantmaster-section-header__sync-label')
            . tenantmaster_sync_mode_badge($syncdefinition)
            . html_writer::span(
                $syncdefinition['description'],
                'tenantmaster-section-header__sync-description',
            ),
        'tenantmaster-section-header__sync',
    );

    return html_writer::tag(
        'section',
        html_writer::span('', 'fa ' . $icon . ' tenantmaster-section-header__icon')
            . html_writer::div(
                html_writer::span(
                    get_string($tenant ? 'tenantworkspace' : 'tenantmastersetup', 'local_tenantmaster'),
                    'tenantmaster-section-header__eyebrow',
                )
                    . html_writer::tag('h2', $title, [
                        'id' => 'tenantmaster-section-title',
                        'class' => 'tenantmaster-section-header__title',
                    ])
                    . html_writer::tag(
                        'p',
                        get_string($descriptionkey, 'local_tenantmaster'),
                        ['class' => 'tenantmaster-section-header__description'],
                    )
                    . $syncsummary,
                'tenantmaster-section-header__body',
            )
            . html_writer::div($meta, 'tenantmaster-section-header__meta'),
        [
            'class' => 'tenantmaster-section-header',
            'aria-labelledby' => 'tenantmaster-section-title',
        ],
    );
}

/**
 * Render the processing-mode legend shown on workspace dashboards.
 *
 * @return string
 */
function tenantmaster_sync_legend(): string {
    $modes = [
        'automatic' => ['syncmode_automatic', 'syncmodehelp_automatic', 'fa-bolt'],
        'manual' => ['syncmode_manual', 'syncmodehelp_manual', 'fa-pen-to-square'],
        'mixed' => ['syncmode_mixed', 'syncmodehelp_mixed', 'fa-arrows-rotate'],
        'review' => ['syncmode_review', 'syncmodehelp_review', 'fa-clipboard-check'],
        'live' => ['syncmode_live', 'syncmodehelp_live', 'fa-eye'],
    ];
    $items = [];
    foreach ($modes as $mode => [$labelkey, $helpkey, $icon]) {
        $definition = [
            'mode' => $mode,
            'label' => get_string($labelkey, 'local_tenantmaster'),
            'description' => get_string($helpkey, 'local_tenantmaster'),
            'icon' => $icon,
        ];
        $items[] = html_writer::div(
            tenantmaster_sync_mode_badge($definition)
                . html_writer::span($definition['description'], 'tenantmaster-sync-legend__help'),
            'tenantmaster-sync-legend__item',
        );
    }
    return html_writer::tag(
        'section',
        html_writer::div(
            html_writer::tag('h3', get_string('synclegendtitle', 'local_tenantmaster'))
                . html_writer::tag('p', get_string('synclegenddescription', 'local_tenantmaster')),
            'tenantmaster-sync-legend__intro',
        )
            . html_writer::div(implode('', $items), 'tenantmaster-sync-legend__items'),
        [
            'class' => 'tenantmaster-sync-legend',
            'aria-label' => get_string('synclegendtitle', 'local_tenantmaster'),
        ],
    );
}

/**
 * Compact return path for one selected-tenant workspace page.
 *
 * @param string $active Active section.
 * @param object|null $tenant Tenant.
 * @return string
 */
function tenantmaster_workspace_navigation(
    string $active,
    ?object $tenant,
    string $academicview = 'masters',
    string $mastertype = '',
): string {
    if (!$tenant || $active === 'dashboard') {
        return '';
    }
    $dashboard = html_writer::link(
        new moodle_url('/local/tenantmaster/index.php', [
            'section' => 'dashboard',
            'companyid' => $tenant->companyid,
        ]),
        html_writer::span('', 'fa fa-arrow-left')
            . html_writer::span(get_string('tenantworkspace', 'local_tenantmaster')),
        ['class' => 'btn btn-secondary'],
    );
    $label = get_string(tenantmaster_section_string($active), 'local_tenantmaster');
    if ($active === 'academic' && $academicview === 'years') {
        $label = get_string('academicyears', 'local_tenantmaster');
    } else if ($active === 'academic' && isset(catalog::MASTER_TYPES[$mastertype])) {
        $label = get_string(catalog::MASTER_TYPES[$mastertype], 'local_tenantmaster');
    }
    $current = html_writer::span($label, 'tenantmaster-workspace-nav__current');
    return html_writer::div($dashboard . $current, 'tenantmaster-workspace-nav');
}

/**
 * Identify the institution that owns records on a tenant-scoped page.
 *
 * @param object $tenant Tenant.
 * @return string
 */
function tenantmaster_tenant_scope(object $tenant): string {
    global $DB;

    $companyname = $DB->get_field(
        'local_iomad_companies',
        'name',
        ['id' => $tenant->companyid],
        MUST_EXIST,
    );
    return html_writer::div(
        html_writer::span('', 'fa fa-building')
            . html_writer::span(
                get_string('tenantmasterdatascope', 'local_tenantmaster', format_string($companyname)),
            )
            . html_writer::span(get_string('tenantowned', 'local_tenantmaster'), 'tenantmaster-tenant-scope__badge'),
        'tenantmaster-tenant-scope',
    );
}

/**
 * Keep site administrators on an explicit native company context.
 *
 * @param array<int, object> $tenants Available tenants.
 * @param string $section Active section.
 * @param int $companyid Active company.
 * @return string
 */
function tenantmaster_context_bar(
    array $tenants,
    string $section,
    int $companyid,
    array $preservedparams = [],
): string {
    if (!is_siteadmin()) {
        return '';
    }
    $options = [0 => get_string('selecttenant', 'local_tenantmaster')];
    foreach ($tenants as $tenant) {
        $options[(int)$tenant->companyid] = format_string($tenant->companyname)
            . ' [' . s($tenant->companycode) . ']';
    }
    $select = html_writer::select(
        $options,
        'companyid',
        $companyid,
        false,
        [
            'class' => 'custom-select',
            'id' => 'tenantmaster-company-context',
            'aria-label' => get_string('activeinstitution', 'local_tenantmaster'),
        ],
    );
    $form = html_writer::start_tag('form', [
        'method' => 'get',
        'action' => (new moodle_url('/local/tenantmaster/index.php'))->out(false),
    ])
        . html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'section', 'value' => $section]);
    foreach ($preservedparams as $name => $value) {
        if ($value === '') {
            continue;
        }
        $form .= html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => $name,
            'value' => $value,
        ]);
    }
    $form .= html_writer::tag('label', get_string('activeinstitution', 'local_tenantmaster'), [
            'for' => 'tenantmaster-company-context',
            'class' => 'visually-hidden',
        ])
        . $select
        . html_writer::tag(
            'button',
            html_writer::span('', 'fa fa-arrow-right') . html_writer::span(get_string('open', 'local_tenantmaster')),
            [
                'type' => 'submit',
                'class' => 'btn btn-secondary',
            ],
        )
        . html_writer::end_tag('form');
    $identity = html_writer::div(
        html_writer::span('', 'fa fa-building-columns tenantmaster-contextbar__icon')
            . html_writer::div(
                html_writer::tag('strong', get_string('activeinstitution', 'local_tenantmaster'))
                    . html_writer::tag('small', get_string('activeinstitutionhelp', 'local_tenantmaster')),
                'tenantmaster-contextbar__copy',
            ),
        'tenantmaster-contextbar__identity',
    );
    return html_writer::tag(
        'section',
        $identity . $form,
        [
            'class' => 'tenantmaster-contextbar',
            'aria-label' => get_string('activeinstitution', 'local_tenantmaster'),
        ],
    );
}

/**
 * Distinguish authoritative native data from Tenant Master-owned metadata.
 *
 * @param bool $native Native IOMAD/Moodle data.
 * @return string
 */
function tenantmaster_origin_badge(bool $native): string {
    global $OUTPUT;

    $label = get_string($native ? 'origin_native' : 'origin_custom', 'local_tenantmaster');
    $icon = $OUTPUT->pix_icon($native ? 'i/settings' : 'i/field', '');
    return html_writer::span(
        $icon . html_writer::span($label),
        'tenantmaster-origin tenantmaster-origin--' . ($native ? 'native' : 'custom'),
    );
}

/**
 * Contextual academic master actions.
 *
 * @param object $tenant Tenant.
 * @param string $type Active type.
 * @return string
 */
function tenantmaster_academic_actions(object $tenant, string $type): string {
    global $OUTPUT;

    $actions = [];
    $actions[] = $OUTPUT->single_button(
        new moodle_url('/local/tenantmaster/index.php', [
            'section' => 'imports',
            'companyid' => $tenant->companyid,
            'entity' => 'academic_masters',
            'type' => $type,
        ]),
        get_string('bulkimport', 'local_tenantmaster'),
        'get',
    );
    if ($type !== '') {
        $actions[] = tenantmaster_action_button(
            $tenant,
            'synctype',
            get_string('syncthismastertype', 'local_tenantmaster'),
            'secondary',
            ['mastertype' => $type],
        );
    }
    return html_writer::div(implode('', $actions), 'tenantmaster-actions my-3');
}

/**
 * Contextual actions for one policy-style master domain.
 *
 * @param object $tenant Tenant.
 * @param string $type Master type.
 * @return string
 */
function tenantmaster_master_domain_actions(object $tenant, string $type): string {
    global $OUTPUT;

    $actions = [
        $OUTPUT->single_button(
            new moodle_url('/local/tenantmaster/index.php', [
                'section' => 'academic',
                'companyid' => $tenant->companyid,
                'type' => $type,
            ]),
            get_string('managecustommaster', 'local_tenantmaster'),
            'get',
        ),
        $OUTPUT->single_button(
            new moodle_url('/local/tenantmaster/index.php', [
                'section' => 'imports',
                'companyid' => $tenant->companyid,
                'entity' => 'academic_masters',
                'type' => $type,
            ]),
            get_string('bulkimport', 'local_tenantmaster'),
            'get',
        ),
        tenantmaster_action_button(
            $tenant,
            'synctype',
            get_string('sync', 'local_tenantmaster'),
            'secondary',
            ['mastertype' => $type],
        ),
    ];
    return html_writer::div(implode('', $actions), 'tenantmaster-actions my-3');
}

/**
 * Site-level dashboard available before any company is initialised.
 *
 * @return string
 */
function tenantmaster_global_dashboard(): string {
    global $DB, $OUTPUT;

    $definitions = [
        [
            new moodle_url('/local/tenantmaster/index.php', ['section' => 'catalogue']),
            'globalmastertemplates',
            'fa-layer-group',
            get_string('catalogueitemcount', 'local_tenantmaster', (object)[
                'active' => $DB->count_records('local_tenantmaster_catitem', ['active' => 1]),
                'total' => $DB->count_records('local_tenantmaster_catitem'),
            ]),
        ],
        [
            new moodle_url('/local/tenantmaster/index.php', ['section' => 'tenants']),
            'managedinstitutions',
            'fa-building-columns',
            get_string('managedinstitutioncount', 'local_tenantmaster',
                $DB->count_records('local_tenantmaster_tenant')),
        ],
        [
            new moodle_url('/blocks/iomad_company_admin/company_edit_form.php', ['createnew' => 1]),
            'createnativeiomadcompany',
            'fa-building',
            get_string('nativecompanycount', 'local_tenantmaster',
                $DB->count_records('local_iomad_companies')),
        ],
        [
            new moodle_url('/blocks/iomad_company_admin/index.php'),
            'iomadadmintools',
            'fa-gear',
            get_string('nativeadministration', 'local_tenantmaster'),
        ],
    ];
    $tiles = [];
    foreach ($definitions as $definition) {
        [$url, $labelkey, $icon, $meta] = $definition;
        $section = match ($labelkey) {
            'globalmastertemplates' => 'catalogue',
            'managedinstitutions' => 'tenants',
            default => 'tenants',
        };
        $syncdefinition = tenantmaster_section_sync_definition($section);
        $tiles[] = html_writer::link(
            $url,
            html_writer::span('', 'fa ' . $icon)
                . html_writer::span(
                    html_writer::tag('strong', get_string($labelkey, 'local_tenantmaster'))
                        . html_writer::tag('small', $meta)
                        . tenantmaster_sync_mode_badge($syncdefinition),
                    'tenantmaster-tool__body',
                ),
            [
                'class' => 'tenantmaster-tool',
                'aria-label' => get_string($labelkey, 'local_tenantmaster') . ': ' . $meta,
            ],
        );
    }
    return tenantmaster_section_header('dashboard', null)
        . tenantmaster_sync_legend()
        . $OUTPUT->heading(get_string('tenantmastersetup', 'local_tenantmaster'), 3)
        . html_writer::div(implode('', $tiles), 'tenantmaster-tools tenantmaster-tools--administration');
}

/**
 * Render catalogue scope and master-type tiles.
 *
 * @param string $scope Active scope.
 * @param string $type Active master type.
 * @return string
 */
function tenantmaster_catalogue_navigation(string $scope, string $type): string {
    global $OUTPUT;

    $scopetiles = [];
    foreach (catalogue_service::SCOPES as $key => $labelkey) {
        $scopetiles[] = html_writer::link(
            new moodle_url('/local/tenantmaster/index.php', [
                'section' => 'catalogue',
                'scope' => $key,
            ]),
            $OUTPUT->pix_icon($key === 'shared' ? 'i/settings' : 'i/group', '')
                . html_writer::span(
                    html_writer::tag('strong', get_string($labelkey, 'local_tenantmaster')),
                    'tenantmaster-tool__body',
                ),
            ['class' => 'tenantmaster-tool' . ($scope === $key ? ' is-active' : '')],
        );
    }

    $typetiles = [];
    foreach (catalog::MASTER_TYPES as $key => $labelkey) {
        $typetiles[] = html_writer::link(
            new moodle_url('/local/tenantmaster/index.php', [
                'section' => 'catalogue',
                'scope' => $scope,
                'type' => $key,
            ]),
            $OUTPUT->pix_icon(tenantmaster_catalogue_icon($key), '')
                . html_writer::span(
                    html_writer::tag('strong', get_string($labelkey, 'local_tenantmaster')),
                    'tenantmaster-tool__body',
                ),
            ['class' => 'tenantmaster-tool' . ($type === $key ? ' is-active' : '')],
        );
    }

    return $OUTPUT->heading(get_string('cataloguescopes', 'local_tenantmaster'), 3)
        . html_writer::div(implode('', $scopetiles), 'tenantmaster-tools tenantmaster-tools--compact')
        . $OUTPUT->heading(get_string('masterdatanavigation', 'local_tenantmaster'), 3)
        . html_writer::div(implode('', $typetiles), 'tenantmaster-tools tenantmaster-tools--compact');
}

/**
 * Select a semantic core icon for one catalogue domain.
 *
 * @param string $mastertype Master type.
 * @return string
 */
function tenantmaster_catalogue_icon(string $mastertype): string {
    return match ($mastertype) {
        'subject', 'course_template' => 'i/course',
        'assessment_policy', 'attendance_policy' => 'i/grades',
        'certificate_rule' => 'i/permissions',
        'progression_rule' => 't/reload',
        'grade', 'credit' => 'i/grade_correct',
        'programme', 'semester', 'stream', 'specialisation', 'division' => 'i/cohort',
        default => 'i/field',
    };
}

/**
 * Global catalogue table.
 *
 * @param array<int, object> $items Items.
 * @param string $scope Active scope.
 * @param string $type Active type.
 * @param bool $showremoved Whether removed rows are visible.
 * @return string
 */
function tenantmaster_catalogue_table(
    array $items,
    string $scope,
    string $type,
    bool $showremoved,
): string {
    global $OUTPUT;

    if (!$items) {
        return $OUTPUT->notification(get_string('nocatalogueitems', 'local_tenantmaster'), 'info', false);
    }
    $table = new html_table();
    $table->head = [
        get_string('type', 'local_tenantmaster'),
        get_string('code'),
        get_string('name'),
        get_string('parent', 'local_tenantmaster'),
        get_string('version'),
        get_string('status'),
        get_string('propagation', 'local_tenantmaster'),
        get_string('actions'),
    ];
    foreach ($items as $item) {
        $summary = json::decode_object((string)$item->propagationjson);
        $summaryparts = [];
        foreach (['created', 'updated', 'unchanged', 'conflicts'] as $measure) {
            if (array_key_exists($measure, $summary)) {
                $summaryparts[] = get_string('catalogue_' . $measure, 'local_tenantmaster')
                    . ': ' . (int)$summary[$measure];
            }
        }
        $editurl = new moodle_url('/local/tenantmaster/index.php', [
            'section' => 'catalogue',
            'scope' => $scope,
            'type' => $type,
            'catalogueeditid' => $item->id,
        ]);
        $toggleurl = new moodle_url('/local/tenantmaster/index.php', [
            'section' => 'catalogue',
            'scope' => $scope,
            'type' => $type,
            'action' => 'cataloguetoggle',
            'catalogueid' => $item->id,
            'active' => empty($item->active) ? 1 : 0,
            'sesskey' => sesskey(),
        ]);
        $operationurl = new moodle_url('/local/tenantmaster/index.php', [
            'section' => 'catalogue',
            'scope' => $scope,
            'type' => $type,
            'showremoved' => $showremoved ? 1 : 0,
            'catalogueoperationid' => $item->id,
            'catalogueoperation' => empty($item->deleted) ? 'remove' : 'restore',
        ]);
        if (empty($item->deleted)) {
            $actions = $OUTPUT->action_icon(
                $editurl,
                new pix_icon('t/edit', get_string('edit')),
            ) . $OUTPUT->action_icon(
                $toggleurl,
                new pix_icon(
                    empty($item->active) ? 't/show' : 't/hide',
                    get_string(empty($item->active) ? 'activatecatalogueitem' : 'deactivatecatalogueitem',
                        'local_tenantmaster'),
                ),
            ) . $OUTPUT->action_icon(
                $operationurl,
                new pix_icon('t/delete', get_string('removecatalogueitem', 'local_tenantmaster')),
            );
        } else {
            $actions = $OUTPUT->action_icon(
                $operationurl,
                new pix_icon('t/restore', get_string('restorecatalogueitem', 'local_tenantmaster')),
            );
        }
        $table->data[] = [
            get_string(catalog::MASTER_TYPES[$item->mastertype], 'local_tenantmaster'),
            s($item->code),
            format_string($item->name),
            s($item->parentexternalid ?: get_string('none')),
            (int)$item->version,
            !empty($item->deleted)
                ? get_string('cataloguestatusremoved', 'local_tenantmaster')
                : get_string(empty($item->active) ? 'inactive' : 'active'),
            get_string('propagationstate_' . $item->propagationstate, 'local_tenantmaster')
                . ($summaryparts ? html_writer::tag('small', implode(' | ', $summaryparts)) : ''),
            $actions,
        ];
    }
    return html_writer::table($table);
}

/**
 * Toggle visibility of removed catalogue records.
 *
 * @param string $scope Scope.
 * @param string $type Master type.
 * @param bool $showremoved Current state.
 * @return string
 */
function tenantmaster_catalogue_removed_filter(string $scope, string $type, bool $showremoved): string {
    $url = new moodle_url('/local/tenantmaster/index.php', [
        'section' => 'catalogue',
        'scope' => $scope,
        'type' => $type,
        'showremoved' => $showremoved ? 0 : 1,
    ]);
    return html_writer::div(
        html_writer::link(
            $url,
            get_string($showremoved ? 'hideremovedcatalogueitems' : 'showremovedcatalogueitems',
                'local_tenantmaster'),
            ['class' => 'btn btn-secondary'],
        ),
        'tenantmaster-actions my-3',
    );
}

/**
 * Render a server-side removal or restoration confirmation.
 *
 * @param object $impact Impact analysis.
 * @param string $operation Operation.
 * @param bool $showremoved Preserve removed-row filter.
 * @return string
 */
function tenantmaster_catalogue_confirmation(
    object $impact,
    string $operation,
    bool $showremoved,
): string {
    global $OUTPUT;

    $cancelurl = new moodle_url('/local/tenantmaster/index.php', [
        'section' => 'catalogue',
        'scope' => $impact->item->scope,
        'type' => $impact->item->mastertype,
        'showremoved' => $showremoved ? 1 : 0,
    ]);
    if ($operation === 'remove' && !$impact->canremove) {
        return $OUTPUT->notification(
            get_string('catalogueremoveblockedchildren', 'local_tenantmaster', (object)[
                'name' => format_string($impact->item->name),
                'children' => (int)$impact->dependentchildren,
            ]),
            'error',
            false,
        ) . $OUTPUT->single_button($cancelurl, get_string('continue'), 'get');
    }
    $isrestore = $operation === 'restore';
    $message = get_string(
        $isrestore ? 'cataloguerestoreconfirm' : 'catalogueremoveconfirm',
        'local_tenantmaster',
        (object)[
            'name' => format_string($impact->item->name),
            'linked' => (int)$impact->linkedtenants,
            'customised' => (int)$impact->customisedtenants,
        ],
    );
    $continueurl = new moodle_url('/local/tenantmaster/index.php', [
        'section' => 'catalogue',
        'scope' => $impact->item->scope,
        'type' => $impact->item->mastertype,
        'showremoved' => $showremoved ? 1 : 0,
        'action' => $isrestore ? 'cataloguerestore' : 'catalogueremove',
        'catalogueid' => $impact->item->id,
        'sesskey' => sesskey(),
    ]);
    return $OUTPUT->confirm($message, $continueurl, $cancelurl);
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
    $summary = [];
    foreach ($counts as $label => $count) {
        $summary[] = html_writer::div(
            html_writer::tag('strong', (int)$count)
                . html_writer::tag('small', s($label)),
            'tenantmaster-summary__item',
        );
    }
    $actions = html_writer::div(
        tenantmaster_action_button(
            $tenant,
            'adoptdefaults',
            get_string('adoptdefaults', 'local_tenantmaster'),
            'secondary',
        ),
        'tenantmaster-actions mb-3',
    );
    return tenantmaster_section_header('dashboard', $tenant)
        . tenantmaster_sync_legend()
        . html_writer::div(implode('', $summary), 'tenantmaster-summary')
        . $actions
        . tenantmaster_dashboard_tools($tenant);
}

/**
 * Tenant operations arranged as one linear workflow.
 *
 * @param object $tenant Tenant.
 * @return string
 */
function tenantmaster_dashboard_tools(object $tenant): string {
    global $OUTPUT;

    $groups = [
        'workspacesetup' => [
            ['profile', 'institutionmasterdata', 'fa-building', 'tool_company_help', 'native', []],
            ['organisation', 'organisation', 'fa-diagram-project', 'tool_organisation_help', 'native', []],
        ],
        'workspacemasterdata' => [
            [
                'academic',
                'academicyears',
                'fa-calendar',
                'tool_academic_help',
                'custom',
                ['academicview' => 'years'],
            ],
        ],
        'workspaceprojections' => [
            ['courses', 'academiccourseprojections', 'fa-graduation-cap', 'tool_courses_help', 'native', []],
            ['people', 'usersandroles', 'fa-users', 'tool_people_help', 'native', []],
            ['access', 'cohortsandenrolments', 'fa-link', 'tool_access_help', 'native', []],
        ],
        'workspacelearning' => [
            ['assessments', 'assessments', 'fa-list-check', 'tool_assessments_help', 'custom', []],
            ['certificates', 'certificates', 'fa-certificate', 'tool_certificates_help', 'custom', []],
            ['classes', 'classmanagement', 'fa-people-group', 'tool_classes_help', 'custom', []],
            ['progression', 'progression', 'fa-chart-line', 'tool_progression_help', 'custom', []],
        ],
        'workspaceoperations' => [
            ['imports', 'imports', 'fa-file-import', 'tool_imports_help', 'custom', []],
            ['sync', 'synchronization', 'fa-arrows-rotate', 'tool_sync_help', 'custom', []],
            ['validation', 'validation', 'fa-shield', 'tool_validation_help', 'custom', []],
            ['audit', 'audit', 'fa-clipboard', 'tool_audit_help', 'custom', []],
        ],
    ];
    foreach (tenantmaster_academic_master_types((string)$tenant->tenanttype) as $mastertype => $icon) {
        $groups['workspacemasterdata'][] = [
            'academic',
            catalog::MASTER_TYPES[$mastertype],
            $icon,
            'tool_academic_help',
            'custom',
            ['type' => $mastertype],
        ];
    }
    if ((string)$tenant->tenanttype !== 'school') {
        $groups['workspacelearning'] = array_values(array_filter(
            $groups['workspacelearning'],
            static fn(array $definition): bool => $definition[0] !== 'classes',
        ));
    }
    $step = 0;
    $output = '';
    foreach ($groups as $groupkey => $definitions) {
        $items = [];
        foreach ($definitions as [$section, $labelkey, $icon, $helpkey, $origin, $params]) {
            $step++;
            $syncdefinition = tenantmaster_section_sync_definition($section);
            $body = html_writer::span(
                html_writer::span(
                    html_writer::span((string)$step, 'tenantmaster-tool__step')
                        . html_writer::tag('strong', get_string($labelkey, 'local_tenantmaster')),
                    'tenantmaster-tool__heading',
                )
                    . html_writer::tag('small', get_string($helpkey, 'local_tenantmaster'))
                    . html_writer::div(
                        html_writer::span(
                            get_string($origin === 'native' ? 'origin_native' : 'tenantowned',
                                'local_tenantmaster'),
                            'tenantmaster-tool__origin tenantmaster-tool__origin--' . $origin,
                        )
                            . tenantmaster_sync_mode_badge($syncdefinition),
                        'tenantmaster-tool__metadata',
                    ),
                'tenantmaster-tool__body',
            );
            $items[] = html_writer::link(
                new moodle_url('/local/tenantmaster/index.php', [
                    'section' => $section,
                    'companyid' => $tenant->companyid,
                ] + $params),
                html_writer::span('', 'fa ' . $icon)
                    . $body,
                ['class' => 'tenantmaster-tool tenantmaster-tool--workflow'],
            );
        }
        $output .= $OUTPUT->heading(get_string($groupkey, 'local_tenantmaster'), 3)
            . html_writer::div(implode('', $items), 'tenantmaster-tools tenantmaster-tools--workflow');
    }
    return $output;
}

/**
 * Academic master domains relevant to one institution type.
 *
 * @param string $tenanttype Tenant type.
 * @return array<string, string>
 */
function tenantmaster_academic_master_types(string $tenanttype): array {
    return match ($tenanttype) {
        'school' => [
            'board' => 'fa-building-columns',
            'medium' => 'fa-language',
            'grade' => 'fa-graduation-cap',
            'stream' => 'fa-diagram-project',
            'division' => 'fa-people-group',
            'subject' => 'fa-book',
        ],
        'university', 'college' => [
            'programme' => 'fa-graduation-cap',
            'semester' => 'fa-calendar',
            'specialisation' => 'fa-diagram-project',
            'credit' => 'fa-award',
            'subject' => 'fa-book',
        ],
        default => [
            'programme' => 'fa-graduation-cap',
            'subject' => 'fa-book',
            'credit' => 'fa-award',
        ],
    };
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
        get_string('value', 'local_tenantmaster'),
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
 * Check one native IOMAD company capability.
 *
 * @param object $tenant Tenant.
 * @param string $capability Capability.
 * @return bool
 */
function tenantmaster_has_native_capability(object $tenant, string $capability): bool {
    if (is_siteadmin()) {
        return true;
    }
    $context = \local_iomad\custom_context\context_company::instance((int)$tenant->companyid);
    return \local_iomad\iomad::has_capability($capability, $context, (int)$tenant->companyid);
}

/**
 * Native department hierarchy.
 *
 * @param object $tenant Tenant.
 * @return string
 */
function tenantmaster_department_table(object $tenant): string {
    global $OUTPUT;

    if (!tenantmaster_has_native_capability($tenant, 'block/iomad_company_admin:edit_departments')) {
        return $OUTPUT->notification(get_string('nativenotauthorised', 'local_tenantmaster'), 'info', false);
    }
    $records = (new native_data_service())->departments($tenant);
    $table = new html_table();
    $table->caption = get_string('nativedepartments', 'local_tenantmaster');
    $table->head = [
        get_string('departmentname', 'local_tenantmaster'),
        get_string('shortname'),
        get_string('parent', 'local_tenantmaster'),
        get_string('nativeusers', 'local_tenantmaster'),
        get_string('nativecourses', 'local_tenantmaster'),
        get_string('actions'),
    ];
    foreach ($records as $record) {
        $table->data[] = [
            format_string($record->name),
            s($record->shortname),
            format_string($record->parentname ?? get_string('root', 'local_tenantmaster')),
            (int)$record->usercount,
            (int)$record->coursecount,
            html_writer::link(
                new moodle_url('/blocks/iomad_company_admin/company_departments.php', [
                    'company' => $tenant->companyid,
                ]),
                get_string('manage', 'local_tenantmaster'),
                ['class' => 'btn btn-secondary btn-sm'],
            ),
        ];
    }
    if (!$records) {
        $table->data[] = [
            ['text' => get_string('nodepartments', 'local_tenantmaster'), 'colspan' => 6],
        ];
    }
    return html_writer::table($table);
}

/**
 * GET course filter.
 *
 * @param object $tenant Tenant.
 * @param string $search Search.
 * @param string $visibility Visibility.
 * @return string
 */
function tenantmaster_course_filter(object $tenant, string $search, string $visibility): string {
    $form = html_writer::start_tag('form', [
        'method' => 'get',
        'action' => (new moodle_url('/local/tenantmaster/index.php'))->out(false),
    ])
        . html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'section', 'value' => 'courses'])
        . html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => 'companyid',
            'value' => $tenant->companyid,
        ])
        . html_writer::tag(
            'label',
            html_writer::span(get_string('searchcourses', 'local_tenantmaster'))
                . html_writer::empty_tag('input', [
                    'type' => 'search',
                    'name' => 'search',
                    'value' => $search,
                    'class' => 'form-control',
                ]),
        )
        . html_writer::tag(
            'label',
            html_writer::span(get_string('coursevisibility', 'local_tenantmaster'))
                . html_writer::select([
                    'all' => get_string('all'),
                    'visible' => get_string('visible', 'local_tenantmaster'),
                    'hidden' => get_string('hidden', 'local_tenantmaster'),
                ], 'visibility', $visibility, false, ['class' => 'custom-select']),
        )
        . html_writer::tag('button', get_string('filter'), ['type' => 'submit', 'class' => 'btn btn-secondary'])
        . html_writer::end_tag('form');
    return html_writer::div($form, 'tenantmaster-filterbar my-3');
}

/**
 * Filtered native company courses with direct supported edit routes.
 *
 * @param object $tenant Tenant.
 * @param string $search Search.
 * @param string $visibility Visibility.
 * @return string
 */
function tenantmaster_native_course_table(object $tenant, string $search, string $visibility): string {
    global $OUTPUT;

    if (!tenantmaster_has_native_capability($tenant, 'block/iomad_company_admin:viewcourses')) {
        return $OUTPUT->notification(get_string('nativenotauthorised', 'local_tenantmaster'), 'info', false);
    }
    $records = (new native_data_service())->courses($tenant, $search, $visibility);
    $table = new html_table();
    $table->caption = get_string('nativecourses', 'local_tenantmaster');
    $table->head = [
        get_string('course'),
        get_string('category'),
        get_string('idnumber'),
        get_string('departmentname', 'local_tenantmaster'),
        get_string('status'),
        get_string('nativeprojection', 'local_tenantmaster'),
        get_string('actions'),
    ];
    foreach ($records as $record) {
        $actions = html_writer::link(
            new moodle_url('/course/view.php', ['id' => $record->id]),
            get_string('view'),
            ['class' => 'btn btn-secondary btn-sm'],
        );
        $coursecontext = context_course::instance((int)$record->id);
        if (is_siteadmin() || has_capability('moodle/course:update', $coursecontext)) {
            $actions .= html_writer::link(
                new moodle_url('/course/edit.php', ['id' => $record->id]),
                get_string('edit'),
                ['class' => 'btn btn-secondary btn-sm'],
            );
        }
        $actions .= html_writer::link(
            new moodle_url('/blocks/iomad_company_admin/iomad_courses_form.php', [
                'company' => $tenant->companyid,
                'search' => $record->shortname,
            ]),
            get_string('iomadsettings', 'local_tenantmaster'),
            ['class' => 'btn btn-secondary btn-sm'],
        );
        $mapping = !empty($record->mappingid)
            ? tenantmaster_status_badge((string)$record->mappingstatus)
                . html_writer::tag('small', s($record->externalkey))
            : html_writer::span(get_string('nativeonly', 'local_tenantmaster'));
        $table->data[] = [
            format_string($record->fullname) . html_writer::tag('small', s($record->shortname), [
                'class' => 'd-block text-muted',
            ]),
            format_string($record->categoryname),
            s($record->idnumber),
            format_string($record->departmentname ?? ''),
            tenantmaster_status_badge($record->visible ? 'active' : 'hidden'),
            html_writer::div($mapping, 'tenantmaster-table-actions'),
            html_writer::div($actions, 'tenantmaster-table-actions'),
        ];
    }
    if (!$records) {
        $table->data[] = [
            ['text' => get_string('nocoursesmatch', 'local_tenantmaster'), 'colspan' => 7],
        ];
    }
    return html_writer::table($table);
}

/**
 * GET user filter.
 *
 * @param object $tenant Tenant.
 * @param string $search Search.
 * @return string
 */
function tenantmaster_people_filter(object $tenant, string $search): string {
    $form = html_writer::start_tag('form', [
        'method' => 'get',
        'action' => (new moodle_url('/local/tenantmaster/index.php'))->out(false),
    ])
        . html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'section', 'value' => 'people'])
        . html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => 'companyid',
            'value' => $tenant->companyid,
        ])
        . html_writer::tag(
            'label',
            html_writer::span(get_string('searchusers', 'local_tenantmaster'))
                . html_writer::empty_tag('input', [
                    'type' => 'search',
                    'name' => 'search',
                    'value' => $search,
                    'class' => 'form-control',
                ]),
        )
        . html_writer::tag('button', get_string('filter'), ['type' => 'submit', 'class' => 'btn btn-secondary'])
        . html_writer::end_tag('form');
    return html_writer::div($form, 'tenantmaster-filterbar my-3');
}

/**
 * Native users and IOMAD role attributes.
 *
 * @param object $tenant Tenant.
 * @param string $search Search.
 * @return string
 */
function tenantmaster_native_user_table(object $tenant, string $search): string {
    global $OUTPUT;

    if (!tenantmaster_has_native_capability($tenant, 'block/iomad_company_admin:view_editusers')) {
        return $OUTPUT->notification(get_string('nativenotauthorised', 'local_tenantmaster'), 'info', false);
    }
    $records = (new native_data_service())->users($tenant, $search);
    $table = new html_table();
    $table->caption = get_string('nativeusers', 'local_tenantmaster');
    $table->head = [
        get_string('user'),
        get_string('idnumber'),
        get_string('departmentname', 'local_tenantmaster'),
        get_string('nativerole', 'local_tenantmaster'),
        get_string('status'),
        get_string('actions'),
    ];
    foreach ($records as $record) {
        $role = match ((int)$record->managertype) {
            1 => get_string('nativecompanymanager', 'local_tenantmaster'),
            2 => get_string('nativedepartmentmanager', 'local_tenantmaster'),
            default => !empty($record->educator)
                ? get_string('educator', 'local_tenantmaster')
                : get_string('user'),
        };
        $table->data[] = [
            fullname($record) . html_writer::tag('small', s($record->email), ['class' => 'd-block text-muted']),
            s($record->idnumber),
            format_string($record->departmentname ?? ''),
            s($role),
            tenantmaster_status_badge(
                !empty($record->suspended) || !empty($record->companysuspended) ? 'suspended' : 'active',
            ),
            html_writer::link(
                new moodle_url('/blocks/iomad_company_admin/editusers.php', [
                    'company' => $tenant->companyid,
                    'search' => $record->username,
                ]),
                get_string('manage', 'local_tenantmaster'),
                ['class' => 'btn btn-secondary btn-sm'],
            ),
        ];
    }
    if (!$records) {
        $table->data[] = [
            ['text' => get_string('nousersmatch', 'local_tenantmaster'), 'colspan' => 6],
        ];
    }
    return html_writer::table($table);
}

/**
 * Native user profile definitions selected for the company.
 *
 * @param object $tenant Tenant.
 * @return string
 */
function tenantmaster_user_profile_field_table(object $tenant): string {
    global $OUTPUT;

    if (!tenantmaster_has_native_capability($tenant, 'block/iomad_company_admin:company_user_profiles')) {
        return $OUTPUT->notification(get_string('nativenotauthorised', 'local_tenantmaster'), 'info', false);
    }
    $records = (new native_data_service())->user_profile_fields($tenant);
    $table = new html_table();
    $table->head = [
        get_string('name'),
        get_string('shortname'),
        get_string('type', 'local_tenantmaster'),
        get_string('required'),
        get_string('fieldlocked', 'local_tenantmaster'),
    ];
    foreach ($records as $record) {
        $table->data[] = [
            format_string($record->name),
            s($record->shortname),
            s($record->datatype),
            $record->required ? get_string('yes') : get_string('no'),
            $record->locked ? get_string('yes') : get_string('no'),
        ];
    }
    if (!$records) {
        $table->data[] = [
            ['text' => get_string('nouserprofilefields', 'local_tenantmaster'), 'colspan' => 5],
        ];
    }
    return html_writer::table($table)
        . $OUTPUT->single_button(
            new moodle_url('/blocks/iomad_company_admin/company_user_profiles.php', [
                'company' => $tenant->companyid,
            ]),
            get_string('manageusercustomfields', 'local_tenantmaster'),
            'get',
        )
        . html_writer::tag('p', get_string('userfieldintegrationhelp', 'local_tenantmaster'), [
            'class' => 'text-muted mt-2',
        ]);
}

/**
 * Native cohort, group and enrolment inventory.
 *
 * @param object $tenant Tenant.
 * @return string
 */
function tenantmaster_native_access_tables(object $tenant): string {
    global $OUTPUT;

    if (!tenantmaster_has_native_capability($tenant, 'block/iomad_company_admin:viewcourses')) {
        return $OUTPUT->notification(get_string('nativenotauthorised', 'local_tenantmaster'), 'info', false);
    }
    $service = new native_data_service();

    $cohorttable = new html_table();
    $cohorttable->head = [
        get_string('cohort', 'local_tenantmaster'),
        get_string('idnumber'),
        get_string('members', 'local_tenantmaster'),
        get_string('status'),
        get_string('actions'),
    ];
    foreach ($service->cohorts($tenant) as $record) {
        $cohorttable->data[] = [
            format_string($record->name),
            s($record->idnumber),
            (int)$record->membercount,
            tenantmaster_status_badge((string)$record->mappingstatus),
            html_writer::link(
                new moodle_url('/cohort/assign.php', ['id' => $record->id]),
                get_string('manage', 'local_tenantmaster'),
                ['class' => 'btn btn-secondary btn-sm'],
            ),
        ];
    }
    if (!$cohorttable->data) {
        $cohorttable->data[] = [['text' => get_string('nomanagedcohorts', 'local_tenantmaster'), 'colspan' => 5]];
    }

    $grouptable = new html_table();
    $grouptable->head = [
        get_string('group'),
        get_string('course'),
        get_string('idnumber'),
        get_string('members', 'local_tenantmaster'),
        get_string('nativeprojection', 'local_tenantmaster'),
        get_string('actions'),
    ];
    foreach ($service->groups($tenant) as $record) {
        $grouptable->data[] = [
            format_string($record->name),
            format_string($record->coursename),
            s($record->idnumber),
            (int)$record->membercount,
            $record->mappingstatus
                ? tenantmaster_status_badge((string)$record->mappingstatus)
                : get_string('nativeonly', 'local_tenantmaster'),
            html_writer::link(
                new moodle_url('/group/index.php', ['id' => $record->courseid]),
                get_string('manage', 'local_tenantmaster'),
                ['class' => 'btn btn-secondary btn-sm'],
            ),
        ];
    }
    if (!$grouptable->data) {
        $grouptable->data[] = [['text' => get_string('nonativegroups', 'local_tenantmaster'), 'colspan' => 6]];
    }

    $enroltable = new html_table();
    $enroltable->head = [
        get_string('course'),
        get_string('enrolmentmethod', 'enrol'),
        get_string('nativerole', 'local_tenantmaster'),
        get_string('activeusers', 'local_tenantmaster'),
        get_string('status'),
        get_string('actions'),
    ];
    foreach ($service->enrolments($tenant) as $record) {
        $enroltable->data[] = [
            format_string($record->coursename),
            s($record->name ?: $record->enrol),
            format_string($record->rolename ?: $record->roleshortname),
            (int)$record->activecount,
            tenantmaster_status_badge((int)$record->status === ENROL_INSTANCE_ENABLED ? 'active' : 'disabled'),
            html_writer::link(
                new moodle_url('/enrol/instances.php', ['id' => $record->courseid]),
                get_string('manage', 'local_tenantmaster'),
                ['class' => 'btn btn-secondary btn-sm'],
            ),
        ];
    }
    if (!$enroltable->data) {
        $enroltable->data[] = [['text' => get_string('nonativeenrolments', 'local_tenantmaster'), 'colspan' => 6]];
    }

    return $OUTPUT->heading(get_string('cohorts', 'local_tenantmaster'), 3)
        . html_writer::table($cohorttable)
        . $OUTPUT->heading(get_string('groups'), 3)
        . html_writer::table($grouptable)
        . $OUTPUT->heading(get_string('enrolments', 'local_tenantmaster'), 3)
        . html_writer::table($enroltable);
}

/**
 * Tenant Master-owned native course custom-field definitions.
 *
 * @return string
 */
function tenantmaster_course_custom_field_table(): string {
    global $OUTPUT;

    $records = (new native_data_service())->course_profile_fields();
    $table = new html_table();
    $table->head = [
        get_string('name'),
        get_string('shortname'),
        get_string('type', 'local_tenantmaster'),
    ];
    foreach ($records as $record) {
        $table->data[] = [format_string($record->name), s($record->shortname), s($record->type)];
    }
    if (!$records) {
        $table->data[] = [
            ['text' => get_string('coursefieldscreatedonsync', 'local_tenantmaster'), 'colspan' => 3],
        ];
    }
    return $OUTPUT->heading(get_string('coursecustomfields', 'local_tenantmaster'), 3)
        . html_writer::table($table);
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
        $active === '' ? ['aria-current' => 'page'] : [],
    );
    foreach (catalog::MASTER_TYPES as $type => $stringkey) {
        $links[] = html_writer::link(
            new moodle_url('/local/tenantmaster/index.php', [
                'section' => 'academic',
                'companyid' => $companyid,
                'type' => $type,
            ]),
            get_string($stringkey, 'local_tenantmaster'),
            $active === $type ? ['aria-current' => 'page'] : [],
        );
    }
    return html_writer::tag('nav', implode('', $links), [
        'class' => 'tenantmaster-master-nav',
        'aria-label' => get_string('masterdatanavigation', 'local_tenantmaster'),
    ]);
}

/**
 * Tile navigation for tenant-specific academic masters.
 *
 * @param object $tenant Tenant.
 * @param string $academicview Active academic view.
 * @param string $activetype Active master type.
 * @return string
 */
function tenantmaster_academic_navigation(
    object $tenant,
    string $academicview,
    string $activetype,
): string {
    global $OUTPUT;

    $definitions = [
        [
            'academicyears',
            'fa-calendar',
            ['academicview' => 'years'],
            $academicview === 'years',
        ],
    ];
    foreach (tenantmaster_academic_master_types((string)$tenant->tenanttype) as $mastertype => $icon) {
        $definitions[] = [
            catalog::MASTER_TYPES[$mastertype],
            $icon,
            ['type' => $mastertype],
            $academicview === 'masters' && $activetype === $mastertype,
        ];
    }
    $tiles = [];
    foreach ($definitions as [$labelkey, $icon, $params, $active]) {
        $tiles[] = html_writer::link(
            new moodle_url('/local/tenantmaster/index.php', [
                'section' => 'academic',
                'companyid' => $tenant->companyid,
            ] + $params),
            html_writer::span('', 'fa ' . $icon)
                . html_writer::span(
                    html_writer::tag('strong', get_string($labelkey, 'local_tenantmaster')),
                    'tenantmaster-tool__body',
                ),
            [
                'class' => 'tenantmaster-tool' . ($active ? ' is-active' : ''),
                'aria-current' => $active ? 'page' : null,
            ],
        );
    }
    return html_writer::div(
        implode('', $tiles),
        'tenantmaster-tools tenantmaster-tools--compact tenantmaster-academic-navigation',
    );
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
                    'academicview' => 'years',
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
 * @param bool $showtype Whether to display the master-type column.
 * @return string
 */
function tenantmaster_master_table(object $tenant, array $masters, bool $showtype = true): string {
    $table = new html_table();
    $table->head = array_values(array_filter([
        $showtype ? get_string('mastertype', 'local_tenantmaster') : null,
        get_string('code', 'local_tenantmaster'),
        get_string('name'),
        get_string('active', 'local_tenantmaster'),
        get_string('nativeprojection', 'local_tenantmaster'),
        get_string('actions'),
    ], static fn(?string $heading): bool => $heading !== null));
    foreach ($masters as $master) {
        $mappings = $GLOBALS['DB']->get_records('local_tenantmaster_mapping', [
            'tenantid' => $tenant->id,
            'masterid' => $master->id,
        ]);
        $projectionitems = [];
        foreach ($mappings as $mapping) {
            $projectionitems[] = tenantmaster_status_badge((string)$mapping->status)
                . tenantmaster_native_target_link($tenant, $mapping);
        }
        if (!$projectionitems) {
            $projectedtypes = [
                'board',
                'medium',
                'grade',
                'programme',
                'semester',
                'stream',
                'specialisation',
                'division',
                'subject',
                'course_template',
            ];
            $projectionitems[] = in_array((string)$master->mastertype, $projectedtypes, true)
                ? tenantmaster_status_badge('pending')
                : html_writer::span(get_string('localmetadataonly', 'local_tenantmaster'));
        }
        $actions = html_writer::link(
            new moodle_url('/local/tenantmaster/index.php', [
                'section' => 'academic',
                'companyid' => $tenant->companyid,
                'editid' => $master->id,
                'type' => $master->mastertype,
            ]),
            get_string('edit'),
            ['class' => 'btn btn-secondary btn-sm'],
        );
        $actions .= tenantmaster_action_button(
            $tenant,
            'syncmaster',
            get_string('sync', 'local_tenantmaster'),
            'secondary',
            ['masterid' => $master->id, 'type' => $master->mastertype],
        );
        $row = [
            s($master->code),
            format_string($master->name),
            $master->active ? get_string('yes') : get_string('no'),
            html_writer::div(implode('', $projectionitems), 'tenantmaster-table-actions'),
            html_writer::div($actions, 'tenantmaster-table-actions'),
        ];
        if ($showtype) {
            array_unshift(
                $row,
                get_string(catalog::MASTER_TYPES[$master->mastertype], 'local_tenantmaster'),
            );
        }
        $table->data[] = $row;
    }
    return html_writer::table($table);
}

/**
 * Render a compact state badge.
 *
 * @param string $status Status.
 * @return string
 */
function tenantmaster_status_badge(string $status): string {
    $classstatus = preg_replace('/[^a-z0-9_-]/', '', strtolower($status));
    return html_writer::span(
        s(ucfirst(str_replace('_', ' ', $status))),
        'tenantmaster-status tenantmaster-status--' . $classstatus,
    );
}

/**
 * Link a mapping to its current native record.
 *
 * @param object $tenant Tenant.
 * @param object $mapping Mapping.
 * @return string
 */
function tenantmaster_native_target_link(object $tenant, object $mapping): string {
    global $DB;

    $url = match ((string)$mapping->component) {
        'local_iomad/company' => new moodle_url(
            '/blocks/iomad_company_admin/company_edit_form.php',
            ['company' => $tenant->companyid],
        ),
        'local_iomad/department' => new moodle_url(
            '/blocks/iomad_company_admin/company_departments.php',
            ['company' => $tenant->companyid],
        ),
        'core_course/category' => new moodle_url('/course/index.php', ['categoryid' => $mapping->targetid]),
        'core/course' => new moodle_url('/course/view.php', ['id' => $mapping->targetid]),
        'core/cohort' => new moodle_url('/cohort/assign.php', ['id' => $mapping->targetid]),
        'core/group' => new moodle_url('/group/index.php', [
            'id' => (int)$DB->get_field('groups', 'courseid', ['id' => $mapping->targetid]),
        ]),
        default => null,
    };
    $label = s((string)$mapping->component) . ' #' . (int)$mapping->targetid;
    return $url
        ? html_writer::link($url, $label, ['class' => 'btn btn-link btn-sm'])
        : html_writer::span($label);
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
            tenantmaster_native_target_link($tenant, $mapping),
            tenantmaster_status_badge((string)$mapping->status),
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
    if (!$records) {
        return html_writer::div(
            get_string('noimportbatches', 'local_tenantmaster'),
            'alert alert-info tenantmaster-empty-state',
            ['role' => 'status'],
        );
    }
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
 * Import preparation, template downloads and accepted-field reference.
 *
 * @param object $tenant Tenant.
 * @return string
 */
function tenantmaster_import_guide(object $tenant): string {
    global $OUTPUT;

    $downloadurl = new moodle_url('/local/tenantmaster/download_template.php', [
        'companyid' => $tenant->companyid,
        'format' => 'zip',
    ]);
    $downloadbutton = html_writer::link(
        $downloadurl,
        $OUTPUT->pix_icon('t/download', '')
            . html_writer::span(get_string('downloadstarterpackage', 'local_tenantmaster')),
        [
            'class' => 'btn btn-primary tenantmaster-import-guide__download',
            'download' => '',
        ],
    );
    $steps = [
        ['1', 'importstepdownload', 'importstepdownloadhelp', 'fa-download'],
        ['2', 'importstepprepare', 'importsteppreparehelp', 'fa-file-csv'],
        ['3', 'importstepinspect', 'importstepinspecthelp', 'fa-clipboard-check'],
    ];
    $stepcontent = '';
    foreach ($steps as [$number, $titlekey, $helpkey, $icon]) {
        $stepcontent .= html_writer::div(
            html_writer::span($number, 'tenantmaster-import-step__number')
                . html_writer::span('', 'fa ' . $icon . ' tenantmaster-import-step__icon')
                . html_writer::div(
                    html_writer::tag('h4', get_string($titlekey, 'local_tenantmaster'))
                        . html_writer::tag('p', get_string($helpkey, 'local_tenantmaster')),
                    'tenantmaster-import-step__copy',
                ),
            'tenantmaster-import-step',
        );
    }

    $heading = static function (string $pix, string $label, string $modifier) use ($OUTPUT): string {
        return html_writer::span(
            $OUTPUT->pix_icon($pix, '')
                . html_writer::span($label),
            'tenantmaster-import-schema-heading tenantmaster-import-schema-heading--' . $modifier,
        );
    };
    $table = new html_table();
    $table->attributes['class'] = 'generaltable tenantmaster-import-schema-table';
    $table->head = [
        $heading('i/file', get_string('file'), 'file'),
        $heading('i/req', get_string('requiredcolumns', 'local_tenantmaster'), 'required'),
        $heading('i/info', get_string('optionalcolumns', 'local_tenantmaster'), 'optional'),
        $heading('i/settings', get_string('nativeoutcome', 'local_tenantmaster'), 'native'),
        $heading('i/navigationitem', get_string('actions'), 'actions'),
    ];
    $entityicons = [
        'academic_years' => 'i/calendar',
        'academic_masters' => 'i/course',
        'departments' => 'i/group',
        'cohorts' => 't/cohort',
        'cohort_members' => 'i/user',
        'groups' => 'i/group',
        'group_members' => 'i/user',
        'user_roles' => 'i/permissions',
        'guardian_links' => 't/link',
    ];
    foreach (import_schema::entities() as $entity => $definition) {
        $csvurl = new moodle_url('/local/tenantmaster/download_template.php', [
            'companyid' => $tenant->companyid,
            'format' => 'csv',
            'entity' => $entity,
        ]);
        $csvbutton = html_writer::link(
            $csvurl,
            $OUTPUT->pix_icon('t/download', '')
                . html_writer::span(get_string('downloadcsvtemplate', 'local_tenantmaster')),
            [
                'class' => 'btn btn-sm btn-outline-secondary',
                'download' => '',
            ],
        );
        $required = array_map(
            static fn(string $column): string => html_writer::tag('code', s($column)),
            $definition['required'],
        );
        $optional = array_map(
            static fn(string $column): string => html_writer::tag('code', s($column)),
            $definition['optional'],
        );
        $entitylabel = html_writer::div(
            html_writer::span(
                $OUTPUT->pix_icon($entityicons[$entity] ?? 'i/file', ''),
                'tenantmaster-import-entity__icon',
            ) . html_writer::div(
                html_writer::tag(
                    'strong',
                    get_string('importentity_' . $entity, 'local_tenantmaster'),
                ) . html_writer::tag('code', $entity . '.csv', ['class' => 'd-block']),
                'tenantmaster-import-entity__copy',
            ),
            'tenantmaster-import-entity',
        );
        $nativeoutcome = html_writer::span(
            $OUTPUT->pix_icon('t/markasread', '')
                . html_writer::span(get_string($definition['resultkey'], 'local_tenantmaster')),
            'tenantmaster-import-outcome',
        );
        $cells = [
            new html_table_cell($entitylabel),
            new html_table_cell(implode(' ', $required)),
            new html_table_cell($optional
                ? implode(' ', $optional)
                : html_writer::span(get_string('nooptionalcolumns', 'local_tenantmaster'), 'text-muted')),
            new html_table_cell($nativeoutcome),
            new html_table_cell($csvbutton),
        ];
        $cellclasses = [
            'tenantmaster-import-schema__file',
            'tenantmaster-import-schema__required',
            'tenantmaster-import-schema__optional',
            'tenantmaster-import-schema__outcome',
            'tenantmaster-import-schema__actions',
        ];
        $labels = [
            get_string('file'),
            get_string('requiredcolumns', 'local_tenantmaster'),
            get_string('optionalcolumns', 'local_tenantmaster'),
            get_string('nativeoutcome', 'local_tenantmaster'),
            get_string('actions'),
        ];
        foreach ($cells as $index => $cell) {
            $cell->attributes['data-label'] = $labels[$index];
            $cell->attributes['class'] = $cellclasses[$index];
        }
        $table->data[] = new html_table_row($cells);
    }

    $header = html_writer::div(
        html_writer::div(
            html_writer::tag(
                'h3',
                get_string('importworkflowtitle', 'local_tenantmaster'),
                ['id' => 'tenantmaster-import-workflow-title'],
            )
                . html_writer::tag('p', get_string('importworkflowintro', 'local_tenantmaster')),
            'tenantmaster-import-guide__copy',
        ) . $downloadbutton,
        'tenantmaster-import-guide__header',
    );
    $reference = html_writer::tag(
        'h3',
        get_string('importfieldreference', 'local_tenantmaster'),
        ['class' => 'tenantmaster-import-guide__reference-title'],
    ) . html_writer::tag(
        'p',
        get_string('importfieldreferencehelp', 'local_tenantmaster'),
        ['class' => 'tenantmaster-import-guide__reference-help'],
    ) . html_writer::table($table);
    $notes = html_writer::div(
        html_writer::span('', 'fa fa-circle-info')
            . html_writer::span(get_string('importmanifestnote', 'local_tenantmaster')),
        'alert alert-info tenantmaster-import-note',
        ['role' => 'note'],
    ) . html_writer::div(
        html_writer::span('', 'fa fa-shield')
            . html_writer::span(get_string('importsecuritynote', 'local_tenantmaster')),
        'alert alert-warning tenantmaster-import-note',
        ['role' => 'note'],
    );

    return html_writer::tag(
        'section',
        $header
            . html_writer::div($stepcontent, 'tenantmaster-import-steps')
            . $notes
            . $reference,
        [
            'class' => 'tenantmaster-import-guide',
            'aria-labelledby' => 'tenantmaster-import-workflow-title',
        ],
    );
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
