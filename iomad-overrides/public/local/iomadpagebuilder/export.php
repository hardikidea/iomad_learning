<?php
// This file is part of IOMAD - http://www.iomad.org/

use local_iomadpagebuilder\catalog;
use local_iomadpagebuilder\page_repository;
use local_iomadpagebuilder\tenant_context;

require_once(__DIR__ . '/../../config.php');

require_login();
require_sesskey();
$companyid = tenant_context::companyid(false);
tenant_context::require_capability('local/iomadpagebuilder:view', $companyid);
$record = (new page_repository())->get(required_param('id', PARAM_INT), $companyid, true);
$payload = [
    'manifest_version' => 1,
    'catalog_version' => catalog::VERSION,
    'uuid' => $record->uuid,
    'name' => $record->name,
    'slug' => $record->slug,
    'description' => $record->description,
    'target' => $record->target,
    'targetid' => (int)$record->targetid,
    'revision' => (int)$record->revision,
    'checksum' => $record->checksum,
    'definition' => json_decode($record->definition, true, 128, JSON_THROW_ON_ERROR),
];
$json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
$filename = clean_filename($record->slug . '-r' . $record->revision . '.json');
send_file($json, $filename, 0, 0, true, true, 'application/json');
