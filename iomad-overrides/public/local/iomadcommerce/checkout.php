<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');

require_login();
require_sesskey();
require_capability('local/iomadcommerce:viewcatalogue', context_system::instance());
$productexternalid = required_param('product', PARAM_ALPHANUMEXT);
$scope = \local_iomadcommerce\local\tenant_scope::resolve();
$product = (new \local_iomadcommerce\local\product_repository())->get(
    $scope->companyid(),
    $productexternalid,
);
if ($product->status !== 'free') {
    throw new invalid_parameter_exception('Only free products complete inside IOMAD.');
}
$externalid = 'free:' . $scope->companyid() . ':' . $USER->id . ':' . $product->externalid;
(new \local_iomadcommerce\local\order_service())->create(
    $scope,
    $product,
    (int)$USER->id,
    $externalid,
);
redirect(
    new moodle_url('/local/iomadcommerce/purchases.php'),
    get_string('ordercreated', 'local_iomadcommerce'),
    null,
    \core\output\notification::NOTIFY_SUCCESS,
);
