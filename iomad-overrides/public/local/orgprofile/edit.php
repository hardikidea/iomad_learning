<?php
// This file is part of Moodle - https://moodle.org/

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

use local_orgprofile\local\ui\page_helper;

$entity = required_param('entity', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);
$capabilities = [
    'orgtype' => 'local/orgprofile:manage', 'usertype' => 'local/orgprofile:manage',
    'form' => 'local/orgprofile:manageforms', 'category' => 'local/orgprofile:manageforms',
    'field' => 'local/orgprofile:managefields',
];
$tables = [
    'orgtype' => 'local_orgprofile_orgtype', 'usertype' => 'local_orgprofile_usertype',
    'form' => 'local_orgprofile_form', 'category' => 'local_orgprofile_category',
    'field' => 'local_orgprofile_field',
];
$titles = [
    'orgtype' => 'orgtypes', 'usertype' => 'usertypes', 'form' => 'forms',
    'category' => 'categories', 'field' => 'fields',
];
$itemtitles = [
    'orgtype' => 'orgtype', 'usertype' => 'usertype', 'form' => 'profileform',
    'category' => 'category', 'field' => 'field',
];
if (!isset($capabilities[$entity])) {
    throw new invalid_parameter_exception('Unsupported entity.');
}
require_login();
$context = context_system::instance();
require_capability($capabilities[$entity], $context);
$listparams = [
    'q' => optional_param('q', '', PARAM_TEXT),
    'page' => optional_param('page', 0, PARAM_INT),
    'perpage' => optional_param('perpage', 20, PARAM_INT),
    'sort' => optional_param('sort', 'name', PARAM_ALPHANUMEXT),
    'dir' => optional_param('dir', 'asc', PARAM_ALPHA),
];
$url = new moodle_url('/local/orgprofile/edit.php', ['entity' => $entity, 'id' => $id] + $listparams);
$returnurl = new moodle_url('/local/orgprofile/manage.php', ['entity' => $entity] + $listparams);
$record = $id ? $DB->get_record($tables[$entity], ['id' => $id], '*', MUST_EXIST) : null;
$sectiontitle = get_string($titles[$entity], 'local_orgprofile');
$pagetitle = get_string(
    $record ? 'edititem' : 'additem',
    'local_orgprofile',
    get_string($itemtitles[$entity], 'local_orgprofile')
);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title($pagetitle);
$PAGE->set_heading($pagetitle);
page_helper::breadcrumbs([
    [$sectiontitle, new moodle_url('/local/orgprofile/manage.php', ['entity' => $entity])],
    [$pagetitle, $url],
]);
$form = new \local_orgprofile\form\entity_form($url, ['entity' => $entity, 'record' => $record]);
if ($form->is_cancelled()) {
    redirect($returnurl);
} else if ($data = $form->get_data()) {
    require_sesskey();
    if (in_array($entity, ['orgtype', 'usertype'], true)) {
        $service = new \local_orgprofile\local\service\organization_service();
        $entity === 'orgtype' ? $service->save_organization_type($data) : $service->save_user_type($data);
    } else {
        $service = new \local_orgprofile\local\service\form_service();
        match ($entity) {
            'form' => $service->save_form($data),
            'category' => $service->save_category($data),
            'field' => $service->save_field($data),
        };
    }
    redirect($returnurl, get_string('saved', 'local_orgprofile'), null, \core\output\notification::NOTIFY_SUCCESS);
}
echo $OUTPUT->header();
echo page_helper::intro(
    get_string($entity . 'editpurpose', 'local_orgprofile'),
    get_string($entity . 'why', 'local_orgprofile')
);
$form->display();
echo $OUTPUT->footer();
