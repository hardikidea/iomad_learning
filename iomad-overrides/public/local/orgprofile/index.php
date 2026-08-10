<?php
// This file is part of Moodle - https://moodle.org/

require_once(__DIR__ . '/../../config.php');

use local_orgprofile\local\ui\page_helper;

require_login();
$context = context_system::instance();
require_capability('local/orgprofile:manage', $context);
$url = new moodle_url('/local/orgprofile/index.php');
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('pluginname', 'local_orgprofile'));
$PAGE->set_heading(get_string('pluginname', 'local_orgprofile'));
page_helper::breadcrumbs();

$validmappings = (int) $DB->count_records_sql(
    'SELECT COUNT(1)
       FROM {local_orgprofile_company} m
       JOIN {local_iomad_companies} c ON c.id = m.companyid'
);
$stalemappings = (int) $DB->count_records_sql(
    'SELECT COUNT(1)
       FROM {local_orgprofile_company} m
  LEFT JOIN {local_iomad_companies} c ON c.id = m.companyid
      WHERE c.id IS NULL'
);
$stats = [
    'orgtype' => $DB->count_records('local_orgprofile_orgtype'),
    'usertype' => $DB->count_records('local_orgprofile_usertype'),
    'field' => $DB->count_records('local_orgprofile_field'),
    'form' => $DB->count_records('local_orgprofile_form'),
    'category' => $DB->count_records('local_orgprofile_category'),
    'formfield' => $DB->count_records('local_orgprofile_formfield'),
    'company' => $validmappings,
    'assignment' => $DB->count_records('local_orgprofile_user'),
    'value' => $DB->count_records('local_orgprofile_value'),
];
$definitions = [
    'orgtype' => ['title' => 'orgtypes', 'description' => 'orgtypecard', 'dependency' => 'orgtypedependency',
        'url' => new moodle_url('/local/orgprofile/manage.php', ['entity' => 'orgtype'])],
    'usertype' => ['title' => 'usertypes', 'description' => 'usertypecard', 'dependency' => 'usertypedependency',
        'url' => new moodle_url('/local/orgprofile/manage.php', ['entity' => 'usertype'])],
    'field' => ['title' => 'fields', 'description' => 'fieldcard', 'dependency' => 'fielddependency',
        'url' => new moodle_url('/local/orgprofile/manage.php', ['entity' => 'field'])],
    'form' => ['title' => 'forms', 'description' => 'formcard', 'dependency' => 'formdependency',
        'url' => new moodle_url('/local/orgprofile/manage.php', ['entity' => 'form'])],
    'category' => ['title' => 'categories', 'description' => 'categorycard', 'dependency' => 'categorydependency',
        'url' => new moodle_url('/local/orgprofile/manage.php', ['entity' => 'category'])],
    'formfield' => ['title' => 'formfields', 'description' => 'formfieldcard', 'dependency' => 'formfielddependency',
        'url' => new moodle_url('/local/orgprofile/formfields.php')],
    'company' => ['title' => 'companymapping', 'description' => 'companymappingcard',
        'dependency' => 'companymappingdependency', 'url' => new moodle_url('/local/orgprofile/company.php')],
    'assignment' => ['title' => 'assignments', 'description' => 'assignmentcard',
        'dependency' => 'assignmentdependency', 'url' => new moodle_url('/local/orgprofile/assignment.php')],
];
$items = [];
foreach ($definitions as $key => $definition) {
    $items[] = [
        'title' => get_string($definition['title'], 'local_orgprofile'),
        'description' => get_string($definition['description'], 'local_orgprofile'),
        'dependency' => get_string($definition['dependency'], 'local_orgprofile'),
        'count' => $stats[$key],
        'configured' => $stats[$key] > 0,
        'statusclass' => $stats[$key] > 0 ? 'bg-success' : 'bg-secondary',
        'statuslabel' => get_string($stats[$key] > 0 ? 'configured' : 'notconfigured', 'local_orgprofile'),
        'url' => $definition['url']->out(false),
    ];
}
$workflow = [];
foreach (['workfloworgtype', 'workflowusertype', 'workflowfield', 'workflowform',
        'workflowcompany', 'workflowassignment', 'workflowprofile'] as $index => $string) {
    $workflow[] = ['number' => $index + 1, 'text' => get_string($string, 'local_orgprofile')];
}
$quickactions = [
    [
        'label' => get_string('createcompanyprofiled', 'local_orgprofile'),
        'url' => (new moodle_url('/local/orgprofile/company_create.php'))->out(false),
        'primary' => true,
    ],
    [
        'label' => get_string('manageassignments', 'local_orgprofile'),
        'url' => (new moodle_url('/local/orgprofile/assignment.php'))->out(false),
        'primary' => false,
    ],
];
$data = [
    'items' => $items,
    'workflow' => $workflow,
    'quickactions' => $quickactions,
    'categorycount' => $stats['category'],
    'placementcount' => $stats['formfield'],
    'mappingcount' => $stats['company'],
    'assignmentcount' => $stats['assignment'],
    'valuecount' => $stats['value'],
    'hasstalemappings' => $stalemappings > 0,
    'stalemappings' => $stalemappings,
];

echo $OUTPUT->header();
echo $PAGE->get_renderer('local_orgprofile')->admin_dashboard($data);
echo $OUTPUT->footer();
