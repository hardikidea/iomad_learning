<?php
// This file is part of IOMAD - http://www.iomad.org/

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_aicoursecreator\course_publisher;
use local_aicoursecreator\course_schema_validator;
use local_aicoursecreator\draft_repository;
use local_aicoursecreator\sample_definition;
use local_aicoursecreator\scorm_exporter;
use local_aicoursecreator\task\generate_draft;

[$options, $unrecognised] = cli_get_params([
    'mode' => 'doctor',
    'company' => '',
    'companyid' => 0,
    'draftid' => 0,
    'title' => '',
    'brief' => '',
    'file' => '',
    'output' => '',
    'sections' => 5,
    'apply' => false,
    'async' => false,
    'publish' => false,
    'help' => false,
], [
    'h' => 'help',
]);

if ($unrecognised) {
    cli_error('Unknown options: ' . implode(', ', $unrecognised));
}
if ($options['help']) {
    echo <<<HELP
Tenant-safe AI course authoring

--mode=doctor|validate|create|generate|approve|publish|scorm|seed-demo|report
--company=SHORTNAME   Required for tenant mutations.
--draftid=ID         Required for existing draft actions.
--title=TEXT          Course title for create.
--brief=TEXT          Course brief for create. Avoid personal data.
--file=PATH           JSON definition for validate.
--output=PATH         SCORM archive output path.
--sections=N          Requested section count for create.
--apply               Required for every mutation.
--async               Queue generation instead of running it now.
--publish             Publish the deterministic seed-demo course.

HELP;
    exit(0);
}

$mode = clean_param((string)$options['mode'], PARAM_ALPHAEXT);
$companyid = (int)$options['companyid'];
if ($options['company'] !== '') {
    $companyid = (int)$DB->get_field(
        'local_iomad_companies',
        'id',
        ['shortname' => $options['company']],
        MUST_EXIST,
    );
}
$draftid = (int)$options['draftid'];
$userid = get_admin()->id;

$json = static function (array $data): void {
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
};
$requireapply = static function () use ($options): void {
    if (!$options['apply']) {
        cli_error('This mutation requires --apply.');
    }
};
$requirecompany = static function () use ($companyid): void {
    if ($companyid <= 0) {
        cli_error('--company must identify an IOMAD company.');
    }
};

switch ($mode) {
    case 'doctor':
        $manager = \core\di::get(\core_ai\manager::class);
        $action = \core_ai\aiactions\generate_text::class;
        $providers = $manager->get_providers_for_actions([$action], true);
        $json([
            'status' => empty($providers[$action]) ? 'not_configured' : 'ready',
            'enabled_generate_text_providers' => count($providers[$action] ?? []),
            'schema_version' => 1,
            'default_monthly_credits' => (int)(get_config('local_aicoursecreator', 'defaultcredits') ?: 300),
        ]);
        break;

    case 'validate':
        if ($options['file'] === '' || !is_readable($options['file'])) {
            cli_error('--file must be a readable JSON definition.');
        }
        $definition = (new course_schema_validator())->from_json(file_get_contents($options['file']));
        $json([
            'status' => 'valid',
            'schema_version' => $definition['schema_version'],
            'sections' => count($definition['sections']),
            'checksum' => hash(
                'sha256',
                json_encode($definition, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ),
        ]);
        break;

    case 'create':
        $requireapply();
        $requirecompany();
        $repository = new draft_repository();
        $draft = $repository->create([
            'title' => $options['title'],
            'brief' => $options['brief'],
            'sectioncount' => (int)$options['sections'],
            'audience' => 'company learners',
            'tone' => 'professional',
        ], $companyid, $userid);
        $json(['id' => $draft->id, 'uuid' => $draft->uuid, 'status' => $draft->status]);
        break;

    case 'generate':
        $requireapply();
        $requirecompany();
        $repository = new draft_repository();
        $draft = $repository->queue($draftid, $companyid, $userid);
        $task = new generate_draft();
        $task->set_userid($userid);
        $task->set_custom_data([
            'draftid' => $draft->id,
            'companyid' => $companyid,
            'userid' => $userid,
        ]);
        if ($options['async']) {
            \core\task\manager::queue_adhoc_task($task, true);
            $status = 'queued';
        } else {
            $task->execute();
            $status = $repository->get($draftid, $companyid)->status;
        }
        $json(['id' => $draftid, 'status' => $status]);
        break;

    case 'approve':
        $requireapply();
        $requirecompany();
        $repository = new draft_repository();
        $draft = $repository->approve($draftid, $companyid, $userid);
        $json(['id' => $draft->id, 'status' => $draft->status, 'checksum' => $draft->checksum]);
        break;

    case 'publish':
        $requireapply();
        $requirecompany();
        $course = (new course_publisher())->publish($draftid, $companyid, $userid);
        $json(['draftid' => $draftid, 'status' => 'published', 'courseid' => $course->id]);
        break;

    case 'scorm':
        $requirecompany();
        if ($options['output'] === '') {
            cli_error('--output is required.');
        }
        $repository = new draft_repository();
        $draft = $repository->get($draftid, $companyid);
        (new scorm_exporter())->export_to_path($repository->definition($draft), $options['output']);
        $json([
            'draftid' => $draftid,
            'status' => 'exported',
            'output' => $options['output'],
            'sha256' => hash_file('sha256', $options['output']),
        ]);
        break;

    case 'seed-demo':
        $requireapply();
        $requirecompany();
        $repository = new draft_repository();
        foreach ($repository->list_for_company($companyid) as $existing) {
            if ($existing->title === 'Digital Safety Foundations' && $existing->provider === 'local-fixture') {
                $courseid = (int)$existing->courseid;
                if ($options['publish'] && $existing->status === 'review') {
                    $existing = $repository->approve($existing->id, $companyid, $userid);
                }
                if ($options['publish'] && $existing->status === 'approved') {
                    $courseid = (int)(new course_publisher())->publish(
                        $existing->id,
                        $companyid,
                        $userid,
                    )->id;
                    $existing = $repository->get($existing->id, $companyid);
                }
                $json([
                    'id' => $existing->id,
                    'status' => $existing->status,
                    'checksum' => $existing->checksum,
                    'courseid' => $courseid,
                    'action' => 'unchanged',
                ]);
                break 2;
            }
        }
        $suffix = 'company-' . $companyid;
        $draft = $repository->create([
            'title' => 'Digital Safety Foundations',
            'brief' => 'Sanitised local acceptance course.',
            'sectioncount' => 2,
            'audience' => 'learners',
            'tone' => 'professional',
        ], $companyid, $userid);
        $repository->queue($draft->id, $companyid, $userid);
        $repository->mark_generating($draft->id, $companyid, $userid, 0);
        $draft = $repository->save_generated(
            $draft->id,
            $companyid,
            $userid,
            sample_definition::create($suffix),
            'local-fixture',
            'deterministic'
        );
        $courseid = 0;
        if ($options['publish']) {
            $draft = $repository->approve($draft->id, $companyid, $userid);
            $courseid = (int)(new course_publisher())->publish(
                $draft->id,
                $companyid,
                $userid,
            )->id;
            $draft = $repository->get($draft->id, $companyid);
        }
        $json([
            'id' => $draft->id,
            'status' => $draft->status,
            'checksum' => $draft->checksum,
            'courseid' => $courseid,
            'action' => 'created',
        ]);
        break;

    case 'report':
        $requirecompany();
        $repository = new draft_repository();
        $draft = $repository->get($draftid, $companyid);
        $json([
            'id' => $draft->id,
            'uuid' => $draft->uuid,
            'companyid' => $draft->companyid,
            'status' => $draft->status,
            'credits' => $draft->credits,
            'checksum' => $draft->checksum,
            'courseid' => $draft->courseid,
        ]);
        break;

    default:
        cli_error("Unsupported mode: {$mode}");
}
