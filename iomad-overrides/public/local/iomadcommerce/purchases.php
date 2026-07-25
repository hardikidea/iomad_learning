<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');

require_login();
require_capability('local/iomadcommerce:viewpurchases', context_system::instance());
$scope = \local_iomadcommerce\local\tenant_scope::resolve();
$purchases = (new \local_iomadcommerce\local\order_service())->purchases($scope, (int)$USER->id);

$PAGE->set_url('/local/iomadcommerce/purchases.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('mycourses', 'local_iomadcommerce'));
$PAGE->set_heading(get_string('mycourses', 'local_iomadcommerce'));
$PAGE->requires->css('/local/iomadcommerce/styles.css');

echo $OUTPUT->header();
echo html_writer::start_div('tenantcommerce-grid');
foreach ($purchases as $purchase) {
    echo html_writer::start_tag('article', ['class' => 'tenantcommerce-product']);
    echo html_writer::tag('h2', format_string($purchase->name));
    echo html_writer::div(
        userdate($purchase->timeassigned, get_string('strftimedatefullshort')),
        'tenantcommerce-date',
    );
    echo $OUTPUT->single_button(
        new moodle_url('/course/view.php', ['id' => $purchase->courseid]),
        get_string('course'),
        'get',
    );
    echo html_writer::end_tag('article');
}
echo html_writer::end_div();
if (!$purchases) {
    echo $OUTPUT->notification(get_string('nopurchases', 'local_iomadcommerce'), 'info');
}
echo $OUTPUT->footer();
