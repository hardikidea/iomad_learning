<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');

require_login();
require_capability('local/iomadcommerce:viewcatalogue', context_system::instance());
$scope = \local_iomadcommerce\local\tenant_scope::resolve();
$productrepository = new \local_iomadcommerce\local\product_repository();
$products = $productrepository->list($scope->companyid());
$purchases = (new \local_iomadcommerce\local\order_service())->purchases($scope, (int)$USER->id);
$recommendations = $productrepository->recommendations($scope->companyid(), $purchases);
$purchasedids = array_fill_keys(array_map(
    static fn(object $purchase): string => $purchase->externalid,
    $purchases,
), true);

$PAGE->set_url('/local/iomadcommerce/index.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('catalogue', 'local_iomadcommerce'));
$PAGE->set_heading(get_string('catalogue', 'local_iomadcommerce'));
$PAGE->requires->css('/local/iomadcommerce/styles.css');

echo $OUTPUT->header();
if ($recommendations) {
    echo html_writer::tag('h2', get_string('recommendations', 'local_iomadcommerce'));
    echo html_writer::start_div('tenantcommerce-recommendations');
    foreach ($recommendations as $recommendation) {
        echo html_writer::link(
            '#product-' . (int)$recommendation->id,
            format_string($recommendation->name),
            ['class' => 'tenantcommerce-recommendation'],
        );
    }
    echo html_writer::end_div();
}
echo html_writer::start_div('tenantcommerce-grid');
foreach ($products as $product) {
    echo html_writer::start_tag('article', [
        'class' => 'tenantcommerce-product',
        'id' => 'product-' . (int)$product->id,
    ]);
    echo html_writer::tag('h2', format_string($product->name));
    $course = get_course($product->courseid);
    echo html_writer::div(format_text($course->summary, $course->summaryformat), 'tenantcommerce-summary');
    $price = $product->status === 'free'
        ? get_string('free', 'local_iomadcommerce')
        : format_float($product->priceminor / 100, 2) . ' ' . s($product->currency);
    echo html_writer::div($price, 'tenantcommerce-price');
    if (isset($purchasedids[$product->externalid])) {
        echo $OUTPUT->single_button(
            new moodle_url('/course/view.php', ['id' => $product->courseid]),
            get_string('course'),
            'get',
        );
    } else if ($product->status === 'free') {
        echo $OUTPUT->single_button(
            new moodle_url('/local/iomadcommerce/checkout.php', [
                'product' => $product->externalid,
                'sesskey' => sesskey(),
            ]),
            get_string('buy', 'local_iomadcommerce'),
            'post',
        );
    } else if ($product->checkouturl !== '') {
        echo html_writer::link(
            $product->checkouturl,
            get_string('buy', 'local_iomadcommerce'),
            [
                'class' => 'btn btn-primary',
                'rel' => 'noopener noreferrer',
            ],
        );
    } else {
        echo $OUTPUT->notification(get_string('notconfigured', 'local_iomadcommerce'), 'info');
    }
    echo html_writer::end_tag('article');
}
echo html_writer::end_div();
if (!$products) {
    echo $OUTPUT->notification(get_string('noproducts', 'local_iomadcommerce'), 'info');
}
echo $OUTPUT->footer();
