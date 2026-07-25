<?php
// This file is part of IOMAD - http://www.iomad.org/

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_iomadpagebuilder\catalog;
use local_iomadpagebuilder\definition_validator;
use local_iomadpagebuilder\page_repository;

[$options, $unrecognised] = cli_get_params([
    'mode' => 'catalog',
    'company' => '',
    'companyid' => 0,
    'template' => 'school_home',
    'name' => '',
    'slug' => '',
    'target' => 'custompage',
    'targetid' => 0,
    'input' => '',
    'apply' => false,
    'publish' => false,
    'help' => false,
], ['h' => 'help']);

if ($unrecognised) {
    cli_error('Unknown options: ' . implode(', ', $unrecognised), 2);
}
if ($options['help']) {
    cli_writeln('Usage: php public/local/iomadpagebuilder/cli/pagebuilder.php '
        . '--mode=catalog|validate|create --company=SHORTNAME [--template=school_home] '
        . '[--name=NAME --slug=SLUG --target=custompage --targetid=0 --apply --publish]');
    exit(0);
}
\core\session\manager::set_user(get_admin());
if (!is_siteadmin()) {
    cli_error('This command requires a site administrator.', 1);
}

try {
    $result = [];
    if ($options['mode'] === 'catalog') {
        $result = [
            'ok' => true,
            'catalog_version' => catalog::VERSION,
            'preset_count' => count(catalog::presets()),
            'template_count' => count(catalog::templates()),
            'presets' => catalog::presets(),
            'templates' => array_map(static fn(array $template): array => [
                'key' => $template['key'],
                'name' => $template['name'],
                'description' => $template['description'],
            ], catalog::templates()),
        ];
    } else if ($options['mode'] === 'validate') {
        if (!$options['input'] || !is_readable($options['input'])) {
            throw new InvalidArgumentException('A readable --input JSON file is required.');
        }
        $definition = (new definition_validator())->from_json(file_get_contents($options['input']));
        $result = [
            'ok' => true,
            'schema_version' => $definition['schema_version'],
            'section_count' => count($definition['sections']),
            'checksum' => hash('sha256', json_encode($definition, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
        ];
    } else if ($options['mode'] === 'create') {
        $companyid = (int)$options['companyid'];
        if ($options['company'] !== '') {
            $companyid = (int)$DB->get_field(
                'local_iomad_companies',
                'id',
                ['shortname' => $options['company']],
                MUST_EXIST,
            );
        }
        if ($companyid <= 0) {
            throw new InvalidArgumentException('--company must identify an IOMAD company.');
        }
        $input = [
            'name' => $options['name'],
            'slug' => $options['slug'],
            'target' => $options['target'],
            'targetid' => (int)$options['targetid'],
            'definition' => catalog::template($options['template']),
        ];
        if (!$options['apply']) {
            $result = [
                'ok' => true,
                'mode' => 'plan',
                'company' => (string)$options['company'],
                'input' => $input,
            ];
        } else {
            $repository = new page_repository();
            $page = $repository->save($input, $companyid, get_admin()->id);
            if ($options['publish']) {
                $page = $repository->publish($page->id, $companyid, get_admin()->id);
            }
            $result = [
                'ok' => true,
                'mode' => 'apply',
                'id' => (int)$page->id,
                'uuid' => $page->uuid,
                'revision' => (int)$page->revision,
                'status' => $page->status,
                'checksum' => $page->checksum,
            ];
        }
    } else {
        throw new InvalidArgumentException('Mode must be catalog, validate, or create.');
    }
    cli_writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    exit(0);
} catch (Throwable $exception) {
    cli_error($exception->getMessage(), 1);
}
