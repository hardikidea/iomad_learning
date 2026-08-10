<?php
// This file is part of Moodle - https://moodle.org/

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

use local_orgprofile\form\list_filter_form;
use local_orgprofile\local\ui\listing;
use local_orgprofile\local\ui\page_helper;

$id = optional_param('id', 0, PARAM_INT);
$companyid = optional_param('companyid', 0, PARAM_INT);
require_login();
$systemcontext = context_system::instance();
$record = $id ? $DB->get_record('local_orgprofile_company', ['id' => $id], '*', MUST_EXIST) : null;
if ($record) {
    $companyid = (int) $record->companyid;
}
$authorization = new \local_orgprofile\local\service\authorization_service();
$globalmanage = has_capability('local/orgprofile:manage', $systemcontext);
if (!$globalmanage && (!$companyid || !$authorization->can_manage_company_mapping($companyid))) {
    $companycontext = $companyid
        ? \local_iomad\custom_context\context_company::instance($companyid, IGNORE_MISSING) : false;
    throw new required_capability_exception(
        $companycontext ?: $systemcontext,
        'local/orgprofile:managecompanymapping',
        'nopermissions',
        'local_orgprofile'
    );
}

$baseparams = $globalmanage ? [] : ['companyid' => $companyid];
$url = new moodle_url('/local/orgprofile/company.php', $baseparams);
$pagecontext = !$globalmanage && $companyid
    ? \local_iomad\custom_context\context_company::instance($companyid) : $systemcontext;
$title = get_string('companymapping', 'local_orgprofile');
$PAGE->set_url($url);
$PAGE->set_context($pagecontext);
$PAGE->set_title($title);
$PAGE->set_heading($title);
page_helper::breadcrumbs([[$title, $url]]);

$list = listing::from_request([
    'company' => 'c.name', 'orgtype' => 'o.name', 'form' => 'f.name', 'assignments' => 'assignmentcount',
], 'company');
$returnurl = new moodle_url($url, $list->url_params(true));
$formurl = new moodle_url('/local/orgprofile/company.php', ($id ? ['id' => $id] : $baseparams) + $list->url_params(true));
$form = new \local_orgprofile\form\company_mapping_form($formurl, [
    'record' => $record,
    'companyid' => $globalmanage ? 0 : $companyid,
]);
if ($form->is_cancelled()) {
    redirect($returnurl);
} else if ($data = $form->get_data()) {
    require_sesskey();
    if (!$authorization->can_manage_company_mapping((int) $data->companyid)) {
        throw new required_capability_exception(
            \local_iomad\custom_context\context_company::instance($data->companyid),
            'local/orgprofile:managecompanymapping',
            'nopermissions',
            'local_orgprofile'
        );
    }
    (new \local_orgprofile\local\service\organization_service())->map_company(
        (int) $data->companyid,
        (int) $data->orgtypeid,
        empty($data->defaultformid) ? null : (int) $data->defaultformid,
        $data->configjson ?? null
    );
    redirect($returnurl, get_string('saved', 'local_orgprofile'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

$where = [];
$params = [];
if (!$globalmanage) {
    $where[] = 'm.companyid = :companyid';
    $params['companyid'] = $companyid;
}
if ($list->query() !== '') {
    $params['companyquery'] = '%' . $DB->sql_like_escape($list->query()) . '%';
    $params['orgquery'] = $params['companyquery'];
    $params['formquery'] = $params['companyquery'];
    $where[] = '(' . $DB->sql_like('c.name', ':companyquery', false) . ' OR ' .
        $DB->sql_like('o.name', ':orgquery', false) . ' OR ' .
        $DB->sql_like('f.name', ':formquery', false) . ')';
}
$wheresql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$from = "FROM {local_orgprofile_company} m
         JOIN {local_iomad_companies} c ON c.id = m.companyid
         JOIN {local_orgprofile_orgtype} o ON o.id = m.orgtypeid
    LEFT JOIN {local_orgprofile_form} f ON f.id = m.defaultformid
    LEFT JOIN (
        SELECT companyid, COUNT(1) AS assignmentcount
          FROM {local_orgprofile_user}
      GROUP BY companyid
    ) a ON a.companyid = m.companyid";
$total = $DB->count_records_sql("SELECT COUNT(1) $from $wheresql", $params);
$mappings = $DB->get_records_sql(
    "SELECT m.id, m.companyid, c.name AS companyname, c.shortname AS companyshortname,
            o.name AS orgtypename, f.name AS formname, COALESCE(a.assignmentcount, 0) AS assignmentcount
       $from
     $wheresql
   ORDER BY " . $list->order_by() . ', m.id ASC',
    $params,
    $list->offset(),
    $list->perpage()
);

$table = new html_table();
$table->attributes['class'] = 'generaltable w-100';
$table->head = [
    $list->heading('company', get_string('company', 'local_orgprofile'), $url),
    get_string('companyshortname', 'local_orgprofile'),
    $list->heading('orgtype', get_string('orgtype', 'local_orgprofile'), $url),
    $list->heading('form', get_string('defaultform', 'local_orgprofile'), $url),
    $list->heading('assignments', get_string('assignedusers', 'local_orgprofile'), $url),
    get_string('actions', 'local_orgprofile'),
];
foreach ($mappings as $mapping) {
    $actions = $OUTPUT->action_icon(
        new moodle_url('/local/orgprofile/company.php', ['id' => $mapping->id] + $list->url_params(true)),
        new pix_icon('t/edit', get_string('edit'))
    );
    $actions .= ' ' . html_writer::link(
        new moodle_url('/local/orgprofile/assignment.php', ['companyid' => $mapping->companyid]),
        get_string('assignments', 'local_orgprofile')
    );
    $table->data[] = [
        format_string($mapping->companyname),
        s($mapping->companyshortname),
        format_string($mapping->orgtypename),
        $mapping->formname ? format_string($mapping->formname) : get_string('automaticformresolution', 'local_orgprofile'),
        $mapping->assignmentcount,
        $actions,
    ];
}
$filterform = new list_filter_form(new moodle_url('/local/orgprofile/company.php'), ['hidden' => $baseparams], 'get');
$filterform->set_data($list->filter_data());

echo $OUTPUT->header();
echo page_helper::intro(get_string('companymappingpurpose', 'local_orgprofile'),
    get_string('companymappingwhy', 'local_orgprofile'));
echo page_helper::filter($filterform, $url);
if ($mappings) {
    echo html_writer::table($table);
    echo $OUTPUT->paging_bar($total, $list->page(), $list->perpage(),
        new moodle_url($url, $list->url_params()));
} else {
    echo page_helper::empty_state($list->query() !== '');
}
echo $OUTPUT->heading(get_string($record ? 'editcompanymapping' : 'addcompanymapping', 'local_orgprofile'), 3,
    'mt-4');
echo $OUTPUT->notification(get_string('companymappinglocknote', 'local_orgprofile'), 'info');
$form->display();
echo $OUTPUT->footer();
