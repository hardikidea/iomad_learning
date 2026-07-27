<?php
// This file is part of Moodle - http://moodle.org/

use local_tenantmaster\local\access;
use local_tenantmaster\local\import_schema;
use local_tenantmaster\local\import_template_service;
use local_tenantmaster\local\tenant_repository;

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/filelib.php');

require_login();

$companyid = required_param('companyid', PARAM_INT);
$format = optional_param('format', 'zip', PARAM_ALPHA);
$entity = optional_param('entity', '', PARAM_ALPHANUMEXT);

$access = access::resolve($companyid, 'local/tenantmaster:import');
$access->require('local/tenantmaster:import');
if ($access->companyid() !== $companyid) {
    throw new required_capability_exception(
        $access->context(),
        'local/tenantmaster:import',
        'nopermissions',
        '',
    );
}
$tenant = (new tenant_repository())->get_by_company($companyid);
if (!$tenant) {
    throw new moodle_exception('selecttenant', 'local_tenantmaster');
}

$service = new import_template_service();
if ($format === 'zip' && $entity === '') {
    send_file(
        $service->build_zip($tenant),
        $service->zip_filename($tenant),
        0,
        0,
        true,
        true,
        'application/zip',
    );
}
if ($format === 'csv' && import_schema::supports($entity)) {
    send_file(
        $service->build_csv($entity),
        clean_filename($entity . '-template.csv'),
        0,
        0,
        true,
        true,
        'text/csv',
    );
}

throw new invalid_parameter_exception('Unsupported import template format.');
