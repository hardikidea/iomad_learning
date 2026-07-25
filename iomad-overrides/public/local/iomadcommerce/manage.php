<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

require_login();
$companyshortname = optional_param('company', '', PARAM_ALPHANUMEXT);
$companyid = optional_param('companyid', 0, PARAM_INT);
if (is_siteadmin() && $companyid === 0 && $companyshortname !== '') {
    $companyid = (int)$DB->get_field(
        'local_iomad_companies',
        'id',
        ['shortname' => $companyshortname],
        MUST_EXIST,
    );
}
if (is_siteadmin() && $companyid === 0) {
    $PAGE->set_url('/local/iomadcommerce/manage.php');
    $PAGE->set_context(\context_system::instance());
    $PAGE->set_pagelayout('admin');
    $PAGE->set_title(get_string('selectcompany', 'local_iomadcommerce'));
    $PAGE->set_heading(get_string('managecommerce', 'local_iomadcommerce'));
    $companies = $DB->get_records(
        'local_iomad_companies',
        ['suspended' => 0],
        'name ASC',
        'id,name,shortname',
    );
    echo $OUTPUT->header();
    echo html_writer::tag('h2', get_string('selectcompany', 'local_iomadcommerce'));
    $items = [];
    foreach ($companies as $companyrecord) {
        $items[] = html_writer::link(
            new moodle_url('/local/iomadcommerce/manage.php', ['company' => $companyrecord->shortname]),
            format_string($companyrecord->name),
        );
    }
    echo html_writer::alist($items);
    echo $OUTPUT->footer();
    exit;
}
$scope = \local_iomadcommerce\local\tenant_scope::resolve($companyid);
$context = \local_iomad\custom_context\context_company::instance($scope->companyid());
require_capability('local/iomadcommerce:manage', $context);
$company = new \local_iomad\company($scope->companyid());
$companyshortname = (string)$company->get('shortname');
$courses = $company->get_menu_courses(shared: true, default: false, includehidden: true);
$form = new \local_iomadcommerce\form\product_form(null, [
    'companyid' => $scope->companyid(),
    'courses' => $courses,
]);

$PAGE->set_url('/local/iomadcommerce/manage.php', ['company' => $companyshortname]);
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('managecommerce', 'local_iomadcommerce'));
$PAGE->set_heading(get_string('managecommerce', 'local_iomadcommerce'));

if ($data = $form->get_data()) {
    $course = get_course($data->courseid);
    [, $action] = (new \local_iomadcommerce\local\product_repository())->upsert(
        $scope,
        $course,
        [
            'externalid' => $data->externalid,
            'name' => $data->name,
            'status' => $data->status,
            'priceminor' => $data->priceminor,
            'currency' => $data->currency,
            'accessdays' => $data->accessdays,
            'checkouturl' => $data->checkouturl,
            'recommendations' => array_filter(array_map('trim', explode(',', $data->recommendations))),
        ],
    );
    redirect(
        $PAGE->url,
        get_string('productaction', 'local_iomadcommerce', $action),
        null,
        \core\output\notification::NOTIFY_SUCCESS,
    );
}

$products = (new \local_iomadcommerce\local\product_repository())->list($scope->companyid(), false);
echo $OUTPUT->header();
$form->display();
$table = new html_table();
$table->head = [
    get_string('externalid', 'local_iomadcommerce'),
    get_string('product', 'local_iomadcommerce'),
    get_string('status'),
    get_string('seats', 'local_iomadcommerce'),
];
foreach ($products as $product) {
    $table->data[] = [
        s($product->externalid),
        format_string($product->name),
        s($product->status),
        $DB->get_field_sql(
            "SELECT COALESCE(SUM(i.quantity), 0)
               FROM {local_iomadcommerce_item} i
               JOIN {local_iomadcommerce_order} o ON o.id = i.orderid
              WHERE i.productid = :productid AND o.status = :status",
            ['productid' => $product->id, 'status' => 'paid'],
        ),
    ];
}
echo $table->data ? html_writer::table($table) : '';
echo $OUTPUT->footer();
