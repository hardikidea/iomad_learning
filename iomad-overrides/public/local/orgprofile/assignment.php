<?php
// This file is part of Moodle - https://moodle.org/

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

use local_orgprofile\form\list_filter_form;
use local_orgprofile\local\ui\listing;
use local_orgprofile\local\ui\page_helper;

$companyid = optional_param('companyid', 0, PARAM_INT);
require_login();
$systemcontext = context_system::instance();
$pagecontext = $companyid > 0
    ? \local_iomad\custom_context\context_company::instance($companyid, IGNORE_MISSING) : $systemcontext;
$url = new moodle_url('/local/orgprofile/assignment.php', $companyid ? ['companyid' => $companyid] : []);
$title = get_string('assignments', 'local_orgprofile');
$PAGE->set_context($pagecontext ?: $systemcontext);
$PAGE->set_url($url);
$PAGE->set_title($title);
$PAGE->set_heading($title);

if (!$companyid) {
    require_capability('local/orgprofile:manage', $systemcontext);
    page_helper::breadcrumbs([[$title, $url]]);
    $list = listing::from_request([
        'company' => 'c.name', 'orgtype' => 'o.name', 'assignments' => 'assignmentcount',
    ], 'company');
    $params = [];
    $where = '';
    if ($list->query() !== '') {
        $params['companyquery'] = '%' . $DB->sql_like_escape($list->query()) . '%';
        $params['orgquery'] = $params['companyquery'];
        $where = 'WHERE (' . $DB->sql_like('c.name', ':companyquery', false) . ' OR ' .
            $DB->sql_like('o.name', ':orgquery', false) . ')';
    }
    $from = "FROM {local_orgprofile_company} m
             JOIN {local_iomad_companies} c ON c.id = m.companyid
             JOIN {local_orgprofile_orgtype} o ON o.id = m.orgtypeid
        LEFT JOIN (
            SELECT companyid, COUNT(1) AS assignmentcount
              FROM {local_orgprofile_user}
          GROUP BY companyid
        ) a ON a.companyid = m.companyid";
    $total = $DB->count_records_sql("SELECT COUNT(1) $from $where", $params);
    $mappings = $DB->get_records_sql(
        "SELECT m.id, m.companyid, c.name AS companyname, c.shortname AS companyshortname,
                o.name AS orgtypename, COALESCE(a.assignmentcount, 0) AS assignmentcount
           $from
         $where
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
        $list->heading('assignments', get_string('assignedusers', 'local_orgprofile'), $url),
        get_string('actions', 'local_orgprofile'),
    ];
    foreach ($mappings as $mapping) {
        $table->data[] = [
            format_string($mapping->companyname),
            s($mapping->companyshortname),
            format_string($mapping->orgtypename),
            $mapping->assignmentcount,
            html_writer::link(
                new moodle_url('/local/orgprofile/assignment.php', ['companyid' => $mapping->companyid]),
                get_string('manageassignments', 'local_orgprofile')
            ),
        ];
    }
    $filterform = new list_filter_form(new moodle_url('/local/orgprofile/assignment.php'), ['hidden' => []], 'get');
    $filterform->set_data($list->filter_data());
    echo $OUTPUT->header();
    echo page_helper::intro(get_string('assignmentselectpurpose', 'local_orgprofile'),
        get_string('assignmentwhy', 'local_orgprofile'));
    echo $OUTPUT->notification(get_string('selectcompanyfirst', 'local_orgprofile'), 'info');
    echo page_helper::filter($filterform, $url);
    if ($mappings) {
        echo html_writer::table($table);
        echo $OUTPUT->paging_bar($total, $list->page(), $list->perpage(),
            new moodle_url($url, $list->url_params()));
    } else {
        echo page_helper::empty_state($list->query() !== '');
    }
    echo $OUTPUT->footer();
    exit;
}

$company = $DB->get_record('local_iomad_companies', ['id' => $companyid], 'id,name,shortname', MUST_EXIST);
$mapping = $DB->get_record('local_orgprofile_company', ['companyid' => $companyid], '*', MUST_EXIST);
$authorization = new \local_orgprofile\local\service\authorization_service();
if (!$authorization->can_assign_user_type((int) $USER->id, $companyid) && !is_siteadmin()) {
    throw new required_capability_exception(
        \local_iomad\custom_context\context_company::instance($companyid),
        'local/orgprofile:assignusertype',
        'nopermissions',
        'local_orgprofile'
    );
}
page_helper::breadcrumbs([
    [get_string('companymapping', 'local_orgprofile'), new moodle_url('/local/orgprofile/company.php')],
    [$title, new moodle_url('/local/orgprofile/assignment.php')],
    [format_string($company->name), $url],
]);
$list = listing::from_request([
    'user' => 'u.lastname', 'usertype' => 't.name', 'form' => 'f.name', 'status' => 'a.status',
], 'user');
$listurl = new moodle_url($url, $list->url_params(true));
$form = new \local_orgprofile\form\assignment_form($listurl, ['companyid' => $companyid, 'mapping' => $mapping]);
if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/orgprofile/assignment.php'));
} else if ($data = $form->get_data()) {
    require_sesskey();
    (new \local_orgprofile\local\service\profile_service())->assign_user_type(
        (int) $data->userid,
        $companyid,
        (int) $data->usertypeid,
        empty($data->formid) ? null : (int) $data->formid,
        $data->status
    );
    redirect($listurl, get_string('saved', 'local_orgprofile'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

$params = ['companyid' => $companyid];
$where = 'WHERE a.companyid = :companyid';
if ($list->query() !== '') {
    $params['firstnamequery'] = '%' . $DB->sql_like_escape($list->query()) . '%';
    $params['lastnamequery'] = $params['firstnamequery'];
    $params['typequery'] = $params['firstnamequery'];
    $params['formquery'] = $params['firstnamequery'];
    $where .= ' AND (' . $DB->sql_like('u.firstname', ':firstnamequery', false) . ' OR ' .
        $DB->sql_like('u.lastname', ':lastnamequery', false) . ' OR ' .
        $DB->sql_like('t.name', ':typequery', false) . ' OR ' .
        $DB->sql_like('f.name', ':formquery', false) . ')';
}
$from = "FROM {local_orgprofile_user} a
         JOIN {user} u ON u.id = a.userid
         JOIN {local_orgprofile_usertype} t ON t.id = a.usertypeid
    LEFT JOIN {local_orgprofile_form} f ON f.id = a.formid";
$total = $DB->count_records_sql("SELECT COUNT(1) $from $where", $params);
$assignments = $DB->get_records_sql(
    "SELECT a.id, a.userid, a.status, u.firstname, u.lastname, t.name AS typename, f.name AS formname
       $from
     $where
   ORDER BY " . $list->order_by() . ', u.firstname ASC, a.id ASC',
    $params,
    $list->offset(),
    $list->perpage()
);
$table = new html_table();
$table->attributes['class'] = 'generaltable w-100';
$table->head = [
    $list->heading('user', get_string('user', 'local_orgprofile'), $url),
    $list->heading('usertype', get_string('usertype', 'local_orgprofile'), $url),
    $list->heading('form', get_string('profileform', 'local_orgprofile'), $url),
    $list->heading('status', get_string('status', 'local_orgprofile'), $url),
    get_string('actions', 'local_orgprofile'),
];
foreach ($assignments as $assignment) {
    $profileurl = new moodle_url('/local/orgprofile/profile.php', [
        'userid' => $assignment->userid,
        'companyid' => $companyid,
    ]);
    $table->data[] = [
        fullname($assignment),
        format_string($assignment->typename),
        $assignment->formname ? format_string($assignment->formname) :
            get_string('automaticformresolution', 'local_orgprofile'),
        page_helper::status_badge($assignment->status === 'active'),
        html_writer::link($profileurl, get_string('viewprofile', 'local_orgprofile')),
    ];
}
$filterform = new list_filter_form(
    new moodle_url('/local/orgprofile/assignment.php'),
    ['hidden' => ['companyid' => $companyid]],
    'get'
);
$filterform->set_data($list->filter_data());

echo $OUTPUT->header();
echo page_helper::intro(get_string('assignmentpurpose', 'local_orgprofile'),
    get_string('assignmentwhy', 'local_orgprofile'));
echo $OUTPUT->heading(format_string($company->name), 3);
echo html_writer::div(s($company->shortname), 'text-muted mb-3');
echo page_helper::filter($filterform, $url);
if ($assignments) {
    echo html_writer::table($table);
    echo $OUTPUT->paging_bar($total, $list->page(), $list->perpage(),
        new moodle_url($url, $list->url_params()));
} else {
    echo page_helper::empty_state($list->query() !== '');
}
echo $OUTPUT->heading(get_string('assignorupdateusertype', 'local_orgprofile'), 3, 'mt-4');
echo $OUTPUT->notification(get_string('assignmentformnote', 'local_orgprofile'), 'info');
$form->display();
echo $OUTPUT->footer();
