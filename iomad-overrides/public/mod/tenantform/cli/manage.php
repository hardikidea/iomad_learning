<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Provision and validate tenant forms from the Moodle CLI.
 *
 * @package    mod_tenantform
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/course/modlib.php');

$help = <<<'HELP'
Tenant form operations.

Options:
  --mode=doctor|create|submit|report|export
  --company=SHORTNAME          IOMAD company shortname.
  --course=SHORTNAME           Course shortname.
  --cm-idnumber=IDNUMBER       Stable course-module idnumber.
  --name=NAME                  Form activity name.
  --template=KEY               Maintained template key.
  --username=USERNAME          Authenticated submitter.
  --data-json=JSON             Stable field IDs mapped to values.
  --format=csv|excel|ods|pdf   Export data format.
  --output=PATH                Absolute export destination.
  --notify=0|1                 Notify same-company reviewers.
  --help                       Show this help.

The command never prints submitted field values.
HELP;

[$options, $unrecognised] = cli_get_params([
    'mode' => 'doctor',
    'company' => '',
    'course' => '',
    'cm-idnumber' => '',
    'name' => '',
    'template' => 'custom',
    'username' => '',
    'data-json' => '{}',
    'format' => 'csv',
    'output' => '',
    'notify' => 0,
    'help' => false,
], [
    'h' => 'help',
]);

if ($unrecognised) {
    cli_error('Unknown options: ' . implode(', ', $unrecognised));
}
if ($options['help']) {
    cli_writeln($help);
    exit(0);
}

/**
 * Find an installed form by stable course-module idnumber.
 *
 * @param string $idnumber ID number.
 * @return array Course module, form and course.
 */
function tenantform_cli_find(string $idnumber): array {
    global $DB;

    $sql = "SELECT cm.id
              FROM {course_modules} cm
              JOIN {modules} m ON m.id = cm.module
             WHERE m.name = :module
                   AND cm.idnumber = :idnumber
                   AND cm.deletioninprogress = 0";
    $cmid = $DB->get_field_sql($sql, ['module' => 'tenantform', 'idnumber' => $idnumber], MUST_EXIST);
    $cm = get_coursemodule_from_id('tenantform', $cmid, 0, false, MUST_EXIST);
    return [
        $cm,
        $DB->get_record('tenantform', ['id' => $cm->instance], '*', MUST_EXIST),
        get_course($cm->course),
    ];
}

/**
 * Resolve an IOMAD company.
 *
 * @param string $shortname Shortname.
 * @return object
 */
function tenantform_cli_company(string $shortname): object {
    global $DB;

    if ($shortname === '') {
        cli_error('--company is required.');
    }
    return $DB->get_record(
        'local_iomad_companies',
        ['shortname' => $shortname],
        '*',
        MUST_EXIST,
    );
}

$mode = (string)$options['mode'];
if ($mode === 'doctor') {
    $manager = $DB->get_manager();
    $definitions = [];
    $validator = new \mod_tenantform\local\definition_validator();
    foreach (array_keys(\mod_tenantform\local\template_catalog::names()) as $key) {
        $validator->validate(\mod_tenantform\local\template_catalog::definition($key));
        $definitions[] = $key;
    }
    cli_writeln(json_encode([
        'ok' => $manager->table_exists('tenantform')
            && $manager->table_exists('tenantform_entry')
            && $manager->table_exists('tenantform_audit'),
        'schema_version' => 1,
        'templates' => count($definitions),
        'field_types' => 11,
        'formats' => \mod_tenantform\local\entry_exporter::FORMATS,
    ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
    exit(0);
}

if ($mode === 'create') {
    $companyrecord = tenantform_cli_company((string)$options['company']);
    $course = $DB->get_record('course', ['shortname' => $options['course']], '*', MUST_EXIST);
    if (!\mod_tenantform\local\tenant_access::course_in_company($course->id, $companyrecord->id)) {
        cli_error('The course is outside the requested company.');
    }
    $cmidnumber = trim((string)$options['cm-idnumber']);
    if ($cmidnumber === '' || !preg_match('/^[A-Za-z0-9_.-]{3,100}$/', $cmidnumber)) {
        cli_error('--cm-idnumber must be a stable ID using letters, numbers, dot, dash or underscore.');
    }
    $existing = $DB->get_field_sql(
        "SELECT cm.id
           FROM {course_modules} cm
           JOIN {modules} m ON m.id = cm.module
          WHERE m.name = :module AND cm.idnumber = :idnumber",
        ['module' => 'tenantform', 'idnumber' => $cmidnumber],
    );
    if ($existing) {
        cli_writeln(json_encode([
            'ok' => true,
            'action' => 'unchanged',
            'cmid' => (int)$existing,
            'idnumber' => $cmidnumber,
        ], JSON_THROW_ON_ERROR));
        exit(0);
    }
    $name = trim((string)$options['name'])
        ?: \mod_tenantform\local\template_catalog::names()[(string)$options['template']];
    $unidentified = $DB->get_records_sql(
        "SELECT cm.id
           FROM {course_modules} cm
           JOIN {modules} m ON m.id = cm.module
           JOIN {tenantform} tf ON tf.id = cm.instance
          WHERE m.name = :module
                AND cm.course = :courseid
                AND tf.companyid = :companyid
                AND tf.name = :name
                AND " . $DB->sql_isempty('course_modules', 'cm.idnumber', true, true),
        [
            'module' => 'tenantform',
            'courseid' => $course->id,
            'companyid' => $companyrecord->id,
            'name' => $name,
        ],
    );
    if (count($unidentified) === 1) {
        $existing = (int)array_key_first($unidentified);
        set_coursemodule_idnumber($existing, $cmidnumber);
        cli_writeln(json_encode([
            'ok' => true,
            'action' => 'identified',
            'cmid' => $existing,
            'idnumber' => $cmidnumber,
        ], JSON_THROW_ON_ERROR));
        exit(0);
    }
    $template = (string)$options['template'];
    $definition = \mod_tenantform\local\template_catalog::definition($template);
    \core\session\manager::set_user(get_admin());
    $SESSION->currenteditingcompany = $companyrecord->id;
    [, , , , $data] = prepare_new_moduleinfo_data($course, 'tenantform', 0);
    $data->name = $name;
    $data->intro = '';
    $data->introformat = FORMAT_HTML;
    $data->cmidnumber = $cmidnumber;
    $data->formtype = $template;
    $data->definitionjson = json_encode(
        $definition,
        JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
    );
    $data->accent = '#176b5b';
    $data->density = 'comfortable';
    $data->allowguest = 0;
    $data->notify = (int)(bool)$options['notify'];
    $data->targetcourseid = 0;
    $data->autoenrol = 0;
    $created = add_moduleinfo($data, $course);
    cli_writeln(json_encode([
        'ok' => true,
        'action' => 'created',
        'cmid' => (int)$created->coursemodule,
        'instanceid' => (int)$created->instance,
        'idnumber' => $cmidnumber,
    ], JSON_THROW_ON_ERROR));
    exit(0);
}

if ($mode === 'submit') {
    [$cm, $form, $course] = tenantform_cli_find((string)$options['cm-idnumber']);
    $companyrecord = tenantform_cli_company((string)$options['company']);
    if ((int)$form->companyid !== (int)$companyrecord->id) {
        cli_error('The form is outside the requested company.');
    }
    $user = $DB->get_record('user', [
        'username' => $options['username'],
        'deleted' => 0,
        'suspended' => 0,
    ], '*', MUST_EXIST);
    if (!\mod_tenantform\local\tenant_access::user_in_company($user->id, $companyrecord->id)) {
        cli_error('The submitter is outside the requested company.');
    }
    \core\session\manager::set_user($user);
    $SESSION->currenteditingcompany = $companyrecord->id;
    $context = context_module::instance($cm->id);
    if (!has_capability('mod/tenantform:submit', $context)) {
        cli_error('The submitter does not have form submission permission.');
    }
    try {
        $values = json_decode((string)$options['data-json'], true, 64, JSON_THROW_ON_ERROR);
    } catch (\JsonException $exception) {
        cli_error('Invalid --data-json: ' . $exception->getMessage());
    }
    if (!is_array($values)) {
        cli_error('--data-json must be a JSON object.');
    }
    $post = [
        'submissiontoken' => substr(hash(
            'sha256',
            $cm->id . ':' . $user->id . ':' . json_encode($values, JSON_THROW_ON_ERROR),
        ), 0, 48),
    ];
    foreach ($values as $fieldid => $value) {
        $post['field_' . clean_param((string)$fieldid, PARAM_ALPHANUMEXT)] = $value;
    }
    $result = (new \mod_tenantform\local\submission_service())->submit(
        $form,
        $course,
        $cm,
        $context,
        $user,
        $post,
        [],
    );
    cli_writeln(json_encode([
        'ok' => true,
        'action' => $result->created ? 'created' : 'unchanged',
        'entryid' => (int)$result->entry->id,
        'status' => $result->entry->status,
        'fieldcount' => count(json_decode($result->entry->datajson, true, 64, JSON_THROW_ON_ERROR)),
    ], JSON_THROW_ON_ERROR));
    exit(0);
}

if (in_array($mode, ['report', 'export'], true)) {
    [$cm, $form] = tenantform_cli_find((string)$options['cm-idnumber']);
    $companyrecord = tenantform_cli_company((string)$options['company']);
    if ((int)$form->companyid !== (int)$companyrecord->id) {
        cli_error('The form is outside the requested company.');
    }
    $entries = (new \mod_tenantform\local\entry_repository())->all((int)$form->id);
    if ($mode === 'report') {
        $statuses = array_fill_keys(\mod_tenantform\local\entry_repository::STATUSES, 0);
        $filecount = 0;
        foreach ($entries as $entry) {
            $statuses[$entry->status]++;
            $filecount += (int)$entry->filecount;
        }
        cli_writeln(json_encode([
            'ok' => true,
            'cmid' => (int)$cm->id,
            'entries' => count($entries),
            'statuses' => $statuses,
            'files' => $filecount,
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        exit(0);
    }
    $output = (string)$options['output'];
    if ($output === '' || !str_starts_with($output, '/') || !is_dir(dirname($output))) {
        cli_error('--output must be an absolute path in an existing directory.');
    }
    $definition = (new \mod_tenantform\local\definition_validator())->from_json($form->definitionjson);
    $temporary = (new \mod_tenantform\local\entry_exporter())->write(
        $form,
        $definition,
        $entries,
        (string)$options['format'],
    );
    if (!copy($temporary, $output)) {
        cli_error('Could not write the requested export.');
    }
    unlink($temporary);
    cli_writeln(json_encode([
        'ok' => true,
        'rows' => count($entries),
        'format' => $options['format'],
        'output' => $output,
        'checksum' => hash_file('sha256', $output),
    ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
    exit(0);
}

cli_error('Unsupported --mode. Use doctor, create, submit, report or export.');
