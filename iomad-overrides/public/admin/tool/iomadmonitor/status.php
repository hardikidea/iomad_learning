<?php
// This file is part of Moodle - http://moodle.org/

require(__DIR__ . '/../../../../config.php');

require_login();
$context = context_system::instance();
require_capability('tool/iomadmonitor:view', $context);

$output = optional_param('output', 'html', PARAM_ALPHA);
$report = (new \tool_iomadmonitor\local\health_service())->run(false);
$catalogue = \tool_iomadmonitor\local\service_catalogue::build()->catalogue();
$requestid = \tool_iomadmonitor\local\correlation_id::get();

if ($output === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Request-ID: ' . $requestid);
    echo json_encode([
        'request_id' => $requestid,
        'health' => $report,
        'services' => $catalogue,
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    exit;
}

$PAGE->set_url('/admin/tool/iomadmonitor/status.php');
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('servicestatus', 'tool_iomadmonitor'));
$PAGE->set_heading(get_string('servicestatus', 'tool_iomadmonitor'));

echo $OUTPUT->header();
echo html_writer::tag('p', get_string('servicestatusintro', 'tool_iomadmonitor'));
echo html_writer::tag('p', get_string('requestid', 'tool_iomadmonitor') . ': ' . s($requestid));
echo html_writer::start_tag('table', ['class' => 'generaltable']);
echo html_writer::tag('caption', get_string('servicecatalogue', 'tool_iomadmonitor'));
echo html_writer::start_tag('thead');
echo html_writer::tag(
    'tr',
    html_writer::tag('th', get_string('service', 'tool_iomadmonitor'), ['scope' => 'col'])
    . html_writer::tag('th', get_string('owner', 'tool_iomadmonitor'), ['scope' => 'col'])
    . html_writer::tag('th', get_string('criticality', 'tool_iomadmonitor'), ['scope' => 'col'])
    . html_writer::tag('th', get_string('companyscope', 'tool_iomadmonitor'), ['scope' => 'col'])
    . html_writer::tag('th', get_string('dependencies', 'tool_iomadmonitor'), ['scope' => 'col']),
);
echo html_writer::end_tag('thead');
echo html_writer::start_tag('tbody');
foreach ($catalogue as $service) {
    echo html_writer::tag(
        'tr',
        html_writer::tag('th', s($service['name']), ['scope' => 'row'])
        . html_writer::tag('td', s($service['owner']))
        . html_writer::tag('td', s($service['criticality']))
        . html_writer::tag('td', s($service['companyscope']))
        . html_writer::tag('td', s(implode(', ', $service['dependencies']))),
    );
}
echo html_writer::end_tag('tbody');
echo html_writer::end_tag('table');
echo $OUTPUT->footer();
