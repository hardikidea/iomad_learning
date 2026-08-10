<?php
// This file is part of Moodle - https://moodle.org/

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

use local_orgprofile\local\ui\page_helper;

$companyid = optional_param('companyid', 0, PARAM_INT);
$usertypeid = optional_param('usertypeid', 0, PARAM_INT);
require_login();
$systemcontext = context_system::instance();
if (!$companyid) {
    $companyid = (int) \local_iomad\iomad::get_my_companyid($systemcontext);
}
$service = new \local_orgprofile\local\service\provisioning_service();
$service->require_create_company_user($companyid);
$company = $DB->get_record('local_iomad_companies', ['id' => $companyid], 'id,name,shortname', MUST_EXIST);
$mapping = $DB->get_record('local_orgprofile_company', ['companyid' => $companyid]);
if (!$mapping) {
    throw new moodle_exception('companynotmapped', 'local_orgprofile',
        new moodle_url('/local/orgprofile/company.php', ['companyid' => $companyid]));
}
$context = \local_iomad\custom_context\context_company::instance($companyid);
$urlparams = ['companyid' => $companyid];
if ($usertypeid) {
    $urlparams['usertypeid'] = $usertypeid;
}
$url = new moodle_url('/local/orgprofile/company_user_create.php', $urlparams);
$returnurl = new moodle_url('/blocks/iomad_company_admin/index.php');
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('createprofileduser', 'local_orgprofile'));
$PAGE->set_heading(get_string('createprofileduserfor', 'local_orgprofile', format_string($company->name)));
page_helper::breadcrumbs([
    [get_string('assignments', 'local_orgprofile'), new moodle_url('/local/orgprofile/assignment.php')],
    [format_string($company->name), new moodle_url('/local/orgprofile/assignment.php', ['companyid' => $companyid])],
    [get_string('createprofileduser', 'local_orgprofile'), $url],
]);

if (!$usertypeid) {
    $form = new \local_orgprofile\form\user_type_select_form($url, [
        'company' => $company,
        'mapping' => $mapping,
    ]);
    if ($form->is_cancelled()) {
        redirect($returnurl);
    } else if ($data = $form->get_data()) {
        require_sesskey();
        redirect(new moodle_url('/local/orgprofile/company_user_create.php', [
            'companyid' => $companyid,
            'usertypeid' => (int) $data->usertypeid,
        ]));
    }
    echo $OUTPUT->header();
    echo page_helper::intro(get_string('companyuserselectpurpose', 'local_orgprofile'),
        get_string('companyusercreatewhy', 'local_orgprofile'));
    echo $OUTPUT->notification(get_string('workflowstep', 'local_orgprofile', (object) [
        'current' => 1, 'total' => 2, 'name' => get_string('usertype', 'local_orgprofile'),
    ]), 'info');
    echo $OUTPUT->notification(get_string('selectusertypeintro', 'local_orgprofile'), 'info');
    $form->display();
    echo $OUTPUT->footer();
    exit;
}

$definition = $service->get_creation_definition($companyid, $usertypeid);
$PAGE->requires->js_call_amd('local_orgprofile/accordion', 'init');
$form = new \local_orgprofile\form\company_user_create_form($url, ['definition' => $definition]);
if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/orgprofile/company_user_create.php', ['companyid' => $companyid]));
} else if ($data = $form->get_data()) {
    require_sesskey();
    if ((int) $data->companyid !== $companyid || (int) $data->usertypeid !== $usertypeid) {
        throw new invalid_parameter_exception(get_string('invalidrelationship', 'local_orgprofile'));
    }
    $userid = $service->create_company_user($companyid, $usertypeid, (array) $data);
    redirect(
        new moodle_url('/local/orgprofile/profile.php', ['userid' => $userid, 'companyid' => $companyid]),
        get_string('profiledusercreated', 'local_orgprofile'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo page_helper::intro(get_string('companyusercreatepurpose', 'local_orgprofile'),
    get_string('companyusercreatewhy', 'local_orgprofile'));
echo $OUTPUT->notification(get_string('workflowstep', 'local_orgprofile', (object) [
    'current' => 2, 'total' => 2, 'name' => format_string($definition->form->name),
]), 'info');
echo $OUTPUT->notification(get_string('companyusercreateintro', 'local_orgprofile'), 'info');
$form->display();
echo $OUTPUT->footer();
