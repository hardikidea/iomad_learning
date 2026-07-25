<?php
// This file is part of IOMAD - http://www.iomad.org/

use local_iomadpagebuilder\output\page as page_output;
use local_iomadpagebuilder\page_repository;
use local_iomadpagebuilder\tenant_context;

require_once(__DIR__ . '/../../config.php');

require_login();
$companyid = tenant_context::companyid(false);
tenant_context::require_capability('local/iomadpagebuilder:view', $companyid);
$id = required_param('id', PARAM_INT);
$repository = new page_repository();
$record = $repository->get($id, $companyid, true);
$context = tenant_context::context($companyid);
$url = new moodle_url('/local/iomadpagebuilder/preview.php', ['id' => $id]);

$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(format_string($record->name));
$PAGE->set_heading(format_string($record->name));

echo $OUTPUT->header();
echo $OUTPUT->render_from_template(
    'local_iomadpagebuilder/page',
    page_output::from_record($record)->export_for_template($OUTPUT)
);
echo $OUTPUT->footer();
