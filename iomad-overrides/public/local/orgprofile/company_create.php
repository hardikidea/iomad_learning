<?php
// This file is part of Moodle - https://moodle.org/

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

use local_orgprofile\local\ui\page_helper;

require_login();
$context = context_system::instance();
require_capability('block/iomad_company_admin:company_add', $context);
$url = new moodle_url('/local/orgprofile/company_create.php');
$returnurl = new moodle_url('/blocks/iomad_company_admin/index.php');
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('createcompanyprofiled', 'local_orgprofile'));
$PAGE->set_heading(get_string('createcompanyprofiled', 'local_orgprofile'));
page_helper::breadcrumbs([
    [get_string('companymapping', 'local_orgprofile'), new moodle_url('/local/orgprofile/company.php')],
    [get_string('createcompanyprofiled', 'local_orgprofile'), $url],
]);

if (!$DB->record_exists('local_orgprofile_orgtype', ['enabled' => 1])) {
    throw new moodle_exception('noenabledorgtypes', 'local_orgprofile',
        new moodle_url('/local/orgprofile/manage.php', ['entity' => 'orgtype']));
}

$form = new \local_orgprofile\form\company_create_form($url);
if ($form->is_cancelled()) {
    redirect($returnurl);
} else if ($data = $form->get_data()) {
    require_sesskey();
    $company = (new \local_orgprofile\local\service\provisioning_service())->create_company($data);
    redirect(
        new moodle_url('/local/orgprofile/company_user_create.php', ['companyid' => $company->id]),
        get_string('companycreatedwithprofile', 'local_orgprofile'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo page_helper::intro(get_string('companycreatepurpose', 'local_orgprofile'),
    get_string('companycreatewhy', 'local_orgprofile'));
echo $OUTPUT->notification(get_string('companycreateintro', 'local_orgprofile'), 'info');
$form->display();
echo $OUTPUT->footer();
