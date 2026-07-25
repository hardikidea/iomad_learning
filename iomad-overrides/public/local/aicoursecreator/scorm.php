<?php
// This file is part of IOMAD - http://www.iomad.org/

use local_aicoursecreator\draft_repository;
use local_aicoursecreator\scorm_exporter;
use local_aicoursecreator\tenant_context;

require_once(__DIR__ . '/../../config.php');

require_login();
require_sesskey();
$companyid = tenant_context::companyid();
tenant_context::require_capability('local/aicoursecreator:manage', $companyid);
$id = required_param('id', PARAM_INT);
$repository = new draft_repository();
$draft = $repository->get($id, $companyid);
$pathname = make_temp_directory('local_aicoursecreator') . "/draft-{$draft->uuid}.zip";
(new scorm_exporter())->export_to_path($repository->definition($draft), $pathname);
send_temp_file($pathname, clean_filename($draft->title) . '-scorm-1.2.zip');
