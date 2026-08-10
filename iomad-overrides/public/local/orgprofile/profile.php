<?php
// This file is part of Moodle - https://moodle.org/

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

use local_orgprofile\local\ui\page_helper;

$userid = required_param('userid', PARAM_INT);
$companyid = required_param('companyid', PARAM_INT);
require_login();
$authorization = new \local_orgprofile\local\service\authorization_service();
$authorization->require_view_profile($userid, $companyid);
$companycontext = \local_iomad\custom_context\context_company::instance($companyid);
$url = new moodle_url('/local/orgprofile/profile.php', ['userid' => $userid, 'companyid' => $companyid]);
$PAGE->set_url($url);
$PAGE->set_context($companycontext);
$PAGE->set_pagelayout('standard');
$service = new \local_orgprofile\local\service\profile_service();
$profile = $service->get_profile($userid, $companyid);
$titledata = (object) [
    'form' => format_string($profile->form->name),
    'user' => fullname($profile->user),
    'company' => format_string($profile->company->name),
];
$title = get_string('profilefor', 'local_orgprofile', $titledata);
$PAGE->set_title($title);
$PAGE->set_heading($title);
$orgtype = $DB->get_record('local_orgprofile_orgtype', ['id' => $profile->mapping->orgtypeid], 'id,name', MUST_EXIST);
$usertype = $DB->get_record('local_orgprofile_usertype', ['id' => $profile->assignment->usertypeid], 'id,name', MUST_EXIST);
page_helper::breadcrumbs([
    [get_string('assignments', 'local_orgprofile'), new moodle_url('/local/orgprofile/assignment.php')],
    [format_string($profile->company->name),
        new moodle_url('/local/orgprofile/assignment.php', ['companyid' => $companyid])],
    [$title, $url],
]);
$canedit = $authorization->can_edit_profile($userid, $companyid);
$form = new \local_orgprofile\form\profile_form($url, [
    'profile' => $profile,
    'canedit' => $canedit,
    'caneditsensitive' => $authorization->can_edit_sensitive($companyid),
]);
if ($data = $form->get_data()) {
    require_sesskey();
    $service->save_profile($userid, $companyid, (array) $data);
    redirect($url, get_string('profileupdated', 'local_orgprofile'), null, \core\output\notification::NOTIFY_SUCCESS);
}
echo $OUTPUT->header();
echo page_helper::intro(get_string('profilepurpose', 'local_orgprofile'),
    get_string('profilewhy', 'local_orgprofile'));
$summary = html_writer::tag('strong', get_string('orgtype', 'local_orgprofile') . ': ') .
    format_string($orgtype->name) . html_writer::span(' &middot; ', 'mx-2') .
    html_writer::tag('strong', get_string('usertype', 'local_orgprofile') . ': ') .
    format_string($usertype->name) . html_writer::span(' &middot; ', 'mx-2') .
    html_writer::tag('strong', get_string('profileform', 'local_orgprofile') . ': ') .
    format_string($profile->form->name);
echo html_writer::div($summary, 'card card-body mb-3');
$hasfields = false;
foreach ($profile->categories as $category) {
    $hasfields = $hasfields || !empty($category->fields);
}
if (!$hasfields) {
    echo $OUTPUT->notification(get_string('noprofilefields', 'local_orgprofile'), 'info');
} else {
    $form->display();
}
echo $OUTPUT->footer();
