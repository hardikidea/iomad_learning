<?php
// This file is part of Moodle - https://moodle.org/

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

use local_orgprofile\form\list_filter_form;
use local_orgprofile\local\ui\listing;
use local_orgprofile\local\ui\page_helper;

$formid = optional_param('formid', 0, PARAM_INT);
$id = optional_param('id', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
require_login();
$context = context_system::instance();
require_capability('local/orgprofile:manageforms', $context);
$url = new moodle_url('/local/orgprofile/formfields.php', $formid ? ['formid' => $formid] : []);
$title = get_string('formfields', 'local_orgprofile');
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title($title);
$PAGE->set_heading($title);

if (!$formid) {
    page_helper::breadcrumbs([[$title, $url]]);
    $list = listing::from_request([
        'name' => 'f.name', 'shortname' => 'f.shortname', 'orgtype' => 'o.name', 'enabled' => 'f.enabled',
    ], 'name');
    $params = [];
    $where = '';
    if ($list->query() !== '') {
        $params['query'] = '%' . $DB->sql_like_escape($list->query()) . '%';
        $where = 'WHERE (' . $DB->sql_like('f.name', ':query', false) . ' OR ' .
            $DB->sql_like('f.shortname', ':shortquery', false) . ' OR ' .
            $DB->sql_like('o.name', ':orgquery', false) . ')';
        $params['shortquery'] = $params['query'];
        $params['orgquery'] = $params['query'];
    }
    $from = "FROM {local_orgprofile_form} f
             JOIN {local_orgprofile_orgtype} o ON o.id = f.orgtypeid
        LEFT JOIN {local_orgprofile_usertype} u ON u.id = f.usertypeid";
    $total = $DB->count_records_sql("SELECT COUNT(1) $from $where", $params);
    $forms = $DB->get_records_sql(
        "SELECT f.id, f.name, f.shortname, f.enabled, o.name AS orgtypename, u.name AS usertypename
           $from
         $where
       ORDER BY " . $list->order_by() . ', f.id ASC',
        $params,
        $list->offset(),
        $list->perpage()
    );
    $filterform = new list_filter_form(new moodle_url('/local/orgprofile/formfields.php'), ['hidden' => []], 'get');
    $filterform->set_data($list->filter_data());
    $table = new html_table();
    $table->attributes['class'] = 'generaltable w-100';
    $table->head = [
        $list->heading('name', get_string('profileform', 'local_orgprofile'), $url),
        $list->heading('orgtype', get_string('appliesto', 'local_orgprofile'), $url),
        $list->heading('shortname', get_string('shortname', 'local_orgprofile'), $url),
        get_string('structure', 'local_orgprofile'),
        $list->heading('enabled', get_string('status', 'local_orgprofile'), $url),
        get_string('actions', 'local_orgprofile'),
    ];
    foreach ($forms as $formrecord) {
        $table->data[] = [
            format_string($formrecord->name),
            format_string($formrecord->orgtypename) . ' / ' .
                ($formrecord->usertypename ? format_string($formrecord->usertypename) :
                    get_string('allusertypes', 'local_orgprofile')),
            s($formrecord->shortname),
            get_string('formstructuresummary', 'local_orgprofile', (object) [
                'categories' => $DB->count_records('local_orgprofile_category', ['formid' => $formrecord->id]),
                'fields' => $DB->count_records('local_orgprofile_formfield', ['formid' => $formrecord->id]),
            ]),
            page_helper::status_badge((bool) $formrecord->enabled),
            html_writer::link(
                new moodle_url('/local/orgprofile/formfields.php', ['formid' => $formrecord->id]),
                get_string('manageformfields', 'local_orgprofile')
            ),
        ];
    }
    echo $OUTPUT->header();
    echo page_helper::intro(
        get_string('formfieldselectpurpose', 'local_orgprofile'),
        get_string('formfieldwhy', 'local_orgprofile')
    );
    echo $OUTPUT->notification(get_string('selectformfirst', 'local_orgprofile'), 'info');
    echo page_helper::filter($filterform, $url);
    if ($forms) {
        echo html_writer::table($table);
        echo $OUTPUT->paging_bar($total, $list->page(), $list->perpage(),
            new moodle_url($url, $list->url_params()));
    } else {
        echo page_helper::empty_state($list->query() !== '');
    }
    echo $OUTPUT->footer();
    exit;
}

$formrecord = $DB->get_record('local_orgprofile_form', ['id' => $formid], '*', MUST_EXIST);
page_helper::breadcrumbs([
    [get_string('forms', 'local_orgprofile'), new moodle_url('/local/orgprofile/manage.php', ['entity' => 'form'])],
    [$title, new moodle_url('/local/orgprofile/formfields.php')],
    [format_string($formrecord->name), $url],
]);
$list = listing::from_request([
    'category' => 'c.sortorder', 'field' => 'fld.name', 'datatype' => 'fld.datatype', 'sortorder' => 'ff.sortorder',
], 'category');
$listurl = new moodle_url($url, $list->url_params(true));

if ($action === 'delete' && $id) {
    $placement = $DB->get_record('local_orgprofile_formfield', ['id' => $id, 'formid' => $formid], '*', MUST_EXIST);
    if (data_submitted() && optional_param('confirm', 0, PARAM_BOOL)) {
        require_sesskey();
        (new \local_orgprofile\local\service\form_service())->delete('formfield', $id);
        redirect($listurl, get_string('deleted', 'local_orgprofile'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }
    $field = $DB->get_record('local_orgprofile_field', ['id' => $placement->fieldid], 'id,name', MUST_EXIST);
    echo $OUTPUT->header();
    echo page_helper::intro(get_string('formfieldpurpose', 'local_orgprofile'),
        get_string('formfieldwhy', 'local_orgprofile'));
    echo $OUTPUT->confirm(
        get_string('deleteconfirm', 'local_orgprofile', format_string($field->name)),
        new single_button(new moodle_url($listurl, ['action' => 'delete', 'id' => $id, 'confirm' => 1]),
            get_string('delete'), 'post'),
        new single_button($listurl, get_string('cancel'), 'get')
    );
    echo $OUTPUT->footer();
    exit;
}

$placement = $id ? $DB->get_record('local_orgprofile_formfield', ['id' => $id, 'formid' => $formid], '*', MUST_EXIST) : null;
$editform = new \local_orgprofile\form\form_field_form($listurl, ['formid' => $formid, 'record' => $placement]);
if ($editform->is_cancelled()) {
    redirect($listurl);
} else if ($data = $editform->get_data()) {
    require_sesskey();
    (new \local_orgprofile\local\service\form_service())->save_form_field($data);
    redirect($listurl, get_string('saved', 'local_orgprofile'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

$params = ['formid' => $formid];
$where = 'WHERE ff.formid = :formid';
if ($list->query() !== '') {
    $params['query'] = '%' . $DB->sql_like_escape($list->query()) . '%';
    $params['categoryquery'] = $params['query'];
    $params['shortquery'] = $params['query'];
    $where .= ' AND (' . $DB->sql_like('fld.name', ':query', false) . ' OR ' .
        $DB->sql_like('c.name', ':categoryquery', false) . ' OR ' .
        $DB->sql_like('fld.shortname', ':shortquery', false) . ')';
}
$from = "FROM {local_orgprofile_formfield} ff
         JOIN {local_orgprofile_category} c ON c.id = ff.categoryid
         JOIN {local_orgprofile_field} fld ON fld.id = ff.fieldid";
$total = $DB->count_records_sql("SELECT COUNT(1) $from $where", $params);
$placements = $DB->get_records_sql(
    "SELECT ff.id, ff.sortorder, ff.requiredoverride, ff.readonlyoverride, ff.visibleoverride,
            c.name AS categoryname, c.sortorder AS categorysort, fld.name AS fieldname,
            fld.shortname, fld.datatype, fld.enabled, fld.required, fld.readonly, fld.visible, fld.sensitive
       $from
     $where
   ORDER BY " . $list->order_by() . ', ff.id ASC',
    $params,
    $list->offset(),
    $list->perpage()
);
$table = new html_table();
$table->attributes['class'] = 'generaltable w-100';
$table->head = [
    $list->heading('category', get_string('category', 'local_orgprofile'), $url),
    $list->heading('field', get_string('field', 'local_orgprofile'), $url),
    $list->heading('datatype', get_string('datatype', 'local_orgprofile'), $url),
    get_string('effectiverules', 'local_orgprofile'),
    $list->heading('sortorder', get_string('sortorder', 'local_orgprofile'), $url),
    get_string('status', 'local_orgprofile'),
    get_string('actions', 'local_orgprofile'),
];
foreach ($placements as $placementrecord) {
    $effective = [];
    $effective[] = get_string('requiredshort', 'local_orgprofile') . ': ' .
        get_string(($placementrecord->requiredoverride ?? $placementrecord->required) ? 'yes' : 'no');
    $effective[] = get_string('readonlyshort', 'local_orgprofile') . ': ' .
        get_string(($placementrecord->readonlyoverride ?? $placementrecord->readonly) ? 'yes' : 'no');
    $effective[] = get_string('visibleshort', 'local_orgprofile') . ': ' .
        get_string(($placementrecord->visibleoverride ?? $placementrecord->visible) ? 'yes' : 'no');
    if ($placementrecord->sensitive) {
        $effective[] = get_string('sensitive', 'local_orgprofile');
    }
    $actions = $OUTPUT->action_icon(new moodle_url($listurl, ['id' => $placementrecord->id]),
        new pix_icon('t/edit', get_string('edit')));
    $actions .= $OUTPUT->action_icon(
        new moodle_url($listurl, ['action' => 'delete', 'id' => $placementrecord->id]),
        new pix_icon('t/delete', get_string('delete'))
    );
    $table->data[] = [format_string($placementrecord->categoryname), format_string($placementrecord->fieldname),
        s($placementrecord->datatype), implode('; ', $effective), $placementrecord->sortorder,
        page_helper::status_badge((bool) $placementrecord->enabled), $actions];
}
$filterform = new list_filter_form(
    new moodle_url('/local/orgprofile/formfields.php'),
    ['hidden' => ['formid' => $formid]],
    'get'
);
$filterform->set_data($list->filter_data());

echo $OUTPUT->header();
echo page_helper::intro(get_string('formfieldpurpose', 'local_orgprofile'),
    get_string('formfieldwhy', 'local_orgprofile'));
echo $OUTPUT->heading(format_string($formrecord->name), 3);
echo html_writer::div(
    html_writer::link(new moodle_url('/local/orgprofile/manage.php', ['entity' => 'category']),
        get_string('managecategories', 'local_orgprofile')) . ' &middot; ' .
    html_writer::link(new moodle_url('/local/orgprofile/manage.php', ['entity' => 'field']),
        get_string('managefieldlibrary', 'local_orgprofile')),
    'mb-3'
);
echo page_helper::filter($filterform, $url);
if ($placements) {
    echo html_writer::table($table);
    echo $OUTPUT->paging_bar($total, $list->page(), $list->perpage(),
        new moodle_url($url, $list->url_params()));
} else {
    echo page_helper::empty_state($list->query() !== '');
}
echo $OUTPUT->heading(get_string($placement ? 'editplacement' : 'addplacement', 'local_orgprofile'), 3,
    'mt-4');
$editform->display();
echo $OUTPUT->footer();
