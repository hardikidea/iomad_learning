<?php
// This file is part of Moodle - https://moodle.org/

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

use local_orgprofile\form\list_filter_form;
use local_orgprofile\local\ui\listing;
use local_orgprofile\local\ui\page_helper;

$entity = required_param('entity', PARAM_ALPHA);
$entities = [
    'orgtype' => [
        'table' => 'local_orgprofile_orgtype',
        'title' => 'orgtypes',
        'cap' => 'local/orgprofile:manage',
        'sorts' => ['name' => 'name', 'shortname' => 'shortname', 'sortorder' => 'sortorder', 'enabled' => 'enabled'],
    ],
    'usertype' => [
        'table' => 'local_orgprofile_usertype',
        'title' => 'usertypes',
        'cap' => 'local/orgprofile:manage',
        'sorts' => ['name' => 'name', 'shortname' => 'shortname', 'sortorder' => 'sortorder', 'enabled' => 'enabled'],
    ],
    'form' => [
        'table' => 'local_orgprofile_form',
        'title' => 'forms',
        'cap' => 'local/orgprofile:manageforms',
        'sorts' => ['name' => 'name', 'shortname' => 'shortname', 'enabled' => 'enabled'],
    ],
    'category' => [
        'table' => 'local_orgprofile_category',
        'title' => 'categories',
        'cap' => 'local/orgprofile:manageforms',
        'sorts' => ['name' => 'name', 'shortname' => 'shortname', 'sortorder' => 'sortorder', 'collapsed' => 'collapsed'],
    ],
    'field' => [
        'table' => 'local_orgprofile_field',
        'title' => 'fields',
        'cap' => 'local/orgprofile:managefields',
        'sorts' => ['name' => 'name', 'shortname' => 'shortname', 'datatype' => 'datatype', 'enabled' => 'enabled'],
    ],
];
if (!isset($entities[$entity])) {
    throw new invalid_parameter_exception('Unsupported entity.');
}
$definition = $entities[$entity];
require_login();
$context = context_system::instance();
require_capability($definition['cap'], $context);
$url = new moodle_url('/local/orgprofile/manage.php', ['entity' => $entity]);
$title = get_string($definition['title'], 'local_orgprofile');
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title($title);
$PAGE->set_heading($title);
page_helper::breadcrumbs([[$title, $url]]);

$list = listing::from_request($definition['sorts'], 'name');
$listurl = new moodle_url($url, $list->url_params(true));
$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);
if ($action === 'delete' && $id) {
    $record = $DB->get_record($definition['table'], ['id' => $id], '*', MUST_EXIST);
    if (data_submitted() && optional_param('confirm', 0, PARAM_BOOL)) {
        require_sesskey();
        try {
            if (in_array($entity, ['orgtype', 'usertype'], true)) {
                (new \local_orgprofile\local\service\organization_service())->delete($entity, $id);
            } else {
                (new \local_orgprofile\local\service\form_service())->delete($entity, $id);
            }
            redirect($listurl, get_string('deleted', 'local_orgprofile'), null,
                \core\output\notification::NOTIFY_SUCCESS);
        } catch (Throwable $exception) {
            redirect($listurl, $exception->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
        }
    }
    echo $OUTPUT->header();
    echo page_helper::intro(
        get_string($entity . 'purpose', 'local_orgprofile'),
        get_string($entity . 'why', 'local_orgprofile')
    );
    $yesurl = new moodle_url($listurl, ['action' => 'delete', 'id' => $id, 'confirm' => 1]);
    $yes = new single_button($yesurl, get_string('delete'), 'post');
    $no = new single_button($listurl, get_string('cancel'), 'get');
    echo $OUTPUT->confirm(get_string('deleteconfirm', 'local_orgprofile', format_string($record->name)), $yes, $no);
    echo $OUTPUT->footer();
    exit;
}

$select = '';
$params = [];
if ($list->query() !== '') {
    $params = ['name' => '%' . $DB->sql_like_escape($list->query()) . '%'];
    $select = '(' . $DB->sql_like('name', ':name', false) . ' OR ' .
        $DB->sql_like('shortname', ':shortname', false) . ')';
    $params['shortname'] = $params['name'];
}
$total = $DB->count_records_select($definition['table'], $select, $params);
$records = $DB->get_records_select(
    $definition['table'],
    $select,
    $params,
    $list->order_by() . ', id ASC',
    '*',
    $list->offset(),
    $list->perpage()
);

$table = new html_table();
$table->attributes['class'] = 'generaltable w-100';
$table->head = match ($entity) {
    'orgtype' => [
        $list->heading('name', get_string('name', 'local_orgprofile'), $url),
        $list->heading('shortname', get_string('shortname', 'local_orgprofile'), $url),
        $list->heading('enabled', get_string('status', 'local_orgprofile'), $url),
        $list->heading('sortorder', get_string('sortorder', 'local_orgprofile'), $url),
        get_string('relatedrecords', 'local_orgprofile'),
        get_string('actions', 'local_orgprofile'),
    ],
    'usertype' => [
        $list->heading('name', get_string('name', 'local_orgprofile'), $url),
        get_string('orgtype', 'local_orgprofile'),
        $list->heading('shortname', get_string('shortname', 'local_orgprofile'), $url),
        $list->heading('enabled', get_string('status', 'local_orgprofile'), $url),
        $list->heading('sortorder', get_string('sortorder', 'local_orgprofile'), $url),
        get_string('relatedrecords', 'local_orgprofile'),
        get_string('actions', 'local_orgprofile'),
    ],
    'form' => [
        $list->heading('name', get_string('name', 'local_orgprofile'), $url),
        get_string('appliesto', 'local_orgprofile'),
        $list->heading('shortname', get_string('shortname', 'local_orgprofile'), $url),
        $list->heading('enabled', get_string('status', 'local_orgprofile'), $url),
        get_string('structure', 'local_orgprofile'),
        get_string('actions', 'local_orgprofile'),
    ],
    'category' => [
        $list->heading('name', get_string('name', 'local_orgprofile'), $url),
        get_string('profileform', 'local_orgprofile'),
        $list->heading('shortname', get_string('shortname', 'local_orgprofile'), $url),
        $list->heading('sortorder', get_string('sortorder', 'local_orgprofile'), $url),
        $list->heading('collapsed', get_string('collapsed', 'local_orgprofile'), $url),
        get_string('fieldcount', 'local_orgprofile'),
        get_string('actions', 'local_orgprofile'),
    ],
    'field' => [
        $list->heading('name', get_string('name', 'local_orgprofile'), $url),
        $list->heading('shortname', get_string('shortname', 'local_orgprofile'), $url),
        $list->heading('datatype', get_string('datatype', 'local_orgprofile'), $url),
        get_string('fieldrules', 'local_orgprofile'),
        $list->heading('enabled', get_string('status', 'local_orgprofile'), $url),
        get_string('usedinforms', 'local_orgprofile'),
        get_string('actions', 'local_orgprofile'),
    ],
};

foreach ($records as $record) {
    $editparams = ['entity' => $entity, 'id' => $record->id] + $list->url_params(true);
    $actions = $OUTPUT->action_icon(
        new moodle_url('/local/orgprofile/edit.php', $editparams),
        new pix_icon('t/edit', get_string('edit'))
    );
    $deleteurl = new moodle_url($listurl, ['action' => 'delete', 'id' => $record->id]);
    $actions .= $OUTPUT->action_icon($deleteurl, new pix_icon('t/delete', get_string('delete')));

    if ($entity === 'orgtype') {
        $related = get_string('relatedsummary', 'local_orgprofile', (object) [
            'first' => $DB->count_records('local_orgprofile_usertype', ['orgtypeid' => $record->id]),
            'firstlabel' => get_string('usertypes', 'local_orgprofile'),
            'second' => $DB->count_records('local_orgprofile_company', ['orgtypeid' => $record->id]),
            'secondlabel' => get_string('companies', 'local_orgprofile'),
        ]);
        $table->data[] = [format_string($record->name), s($record->shortname),
            page_helper::status_badge((bool) $record->enabled), $record->sortorder, $related, $actions];
    } else if ($entity === 'usertype') {
        $orgtype = $DB->get_record('local_orgprofile_orgtype', ['id' => $record->orgtypeid], 'id,name');
        $related = get_string('relatedsummary', 'local_orgprofile', (object) [
            'first' => $DB->count_records('local_orgprofile_form', ['usertypeid' => $record->id]),
            'firstlabel' => get_string('forms', 'local_orgprofile'),
            'second' => $DB->count_records('local_orgprofile_user', ['usertypeid' => $record->id]),
            'secondlabel' => get_string('assignments', 'local_orgprofile'),
        ]);
        $table->data[] = [format_string($record->name), $orgtype ? format_string($orgtype->name) : get_string('unknown'),
            s($record->shortname), page_helper::status_badge((bool) $record->enabled), $record->sortorder,
            $related, $actions];
    } else if ($entity === 'form') {
        $orgtype = $DB->get_record('local_orgprofile_orgtype', ['id' => $record->orgtypeid], 'id,name');
        $usertype = $record->usertypeid
            ? $DB->get_record('local_orgprofile_usertype', ['id' => $record->usertypeid], 'id,name') : null;
        $applies = ($orgtype ? format_string($orgtype->name) : get_string('unknown')) . ' / ' .
            ($usertype ? format_string($usertype->name) : get_string('allusertypes', 'local_orgprofile'));
        $structure = get_string('formstructuresummary', 'local_orgprofile', (object) [
            'categories' => $DB->count_records('local_orgprofile_category', ['formid' => $record->id]),
            'fields' => $DB->count_records('local_orgprofile_formfield', ['formid' => $record->id]),
        ]);
        $actions .= $OUTPUT->action_icon(
            new moodle_url('/local/orgprofile/formfields.php', ['formid' => $record->id]),
            new pix_icon('i/settings', get_string('manageformfields', 'local_orgprofile'))
        );
        $table->data[] = [format_string($record->name), $applies, s($record->shortname),
            page_helper::status_badge((bool) $record->enabled), $structure, $actions];
    } else if ($entity === 'category') {
        $formrecord = $DB->get_record('local_orgprofile_form', ['id' => $record->formid], 'id,name');
        $table->data[] = [format_string($record->name),
            $formrecord ? format_string($formrecord->name) : get_string('unknown'), s($record->shortname),
            $record->sortorder, page_helper::yes_no_badge((bool) $record->collapsed),
            $DB->count_records('local_orgprofile_formfield', ['categoryid' => $record->id]), $actions];
    } else {
        $rules = [];
        if ($record->required) {
            $rules[] = get_string('required', 'local_orgprofile');
        }
        if ($record->readonly) {
            $rules[] = get_string('readonly', 'local_orgprofile');
        }
        if ($record->sensitive) {
            $rules[] = get_string('sensitive', 'local_orgprofile');
        }
        if ($record->uniquescope !== 'none') {
            $rules[] = get_string('unique' . $record->uniquescope, 'local_orgprofile');
        }
        $type = s($record->datatype);
        if (!empty($record->corefield)) {
            $type .= html_writer::empty_tag('br') . html_writer::span(s($record->corefield), 'text-muted small');
        }
        $table->data[] = [format_string($record->name), s($record->shortname), $type,
            $rules ? implode(', ', $rules) : get_string('none'),
            page_helper::status_badge((bool) $record->enabled),
            $DB->count_records('local_orgprofile_formfield', ['fieldid' => $record->id]), $actions];
    }
}

$filterform = new list_filter_form(
    new moodle_url('/local/orgprofile/manage.php'),
    ['hidden' => ['entity' => $entity]],
    'get'
);
$filterform->set_data($list->filter_data());
$addurl = new moodle_url('/local/orgprofile/edit.php', ['entity' => $entity]);

echo $OUTPUT->header();
echo page_helper::intro(
    get_string($entity . 'purpose', 'local_orgprofile'),
    get_string($entity . 'why', 'local_orgprofile')
);
echo html_writer::div(
    $OUTPUT->single_button($addurl, get_string('addnew', 'local_orgprofile'), 'get'),
    'mb-3'
);
echo page_helper::filter($filterform, $url);
if ($records) {
    echo html_writer::table($table);
    echo $OUTPUT->paging_bar($total, $list->page(), $list->perpage(),
        new moodle_url($url, $list->url_params()));
} else {
    echo page_helper::empty_state($list->query() !== '');
}
echo $OUTPUT->footer();
