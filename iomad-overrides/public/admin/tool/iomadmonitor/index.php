<?php
// This file is part of Moodle - http://moodle.org/

require(__DIR__ . '/../../../../config.php');

require_login();
$context = context_system::instance();
require_capability('tool/iomadmonitor:view', $context);

$PAGE->set_url('/admin/tool/iomadmonitor/index.php');
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('pluginname', 'tool_iomadmonitor'));
$PAGE->set_heading(get_string('pluginname', 'tool_iomadmonitor'));

$report = (new \tool_iomadmonitor\local\health_service())->run(optional_param('deep', 0, PARAM_BOOL));

echo $OUTPUT->header();
echo html_writer::start_tag('table', ['class' => 'generaltable']);
echo html_writer::tag('caption', get_string('statuscaption', 'tool_iomadmonitor'));
echo html_writer::start_tag('thead');
echo html_writer::tag(
    'tr',
    html_writer::tag('th', get_string('check', 'tool_iomadmonitor'), ['scope' => 'col'])
    . html_writer::tag('th', get_string('status'), ['scope' => 'col'])
    . html_writer::tag('th', get_string('summary', 'tool_iomadmonitor'), ['scope' => 'col'])
    . html_writer::tag('th', get_string('metric', 'tool_iomadmonitor'), ['scope' => 'col'])
    . html_writer::tag('th', get_string('duration', 'tool_iomadmonitor'), ['scope' => 'col'])
);
echo html_writer::end_tag('thead');
echo html_writer::start_tag('tbody');
foreach ($report['checks'] as $check) {
    echo html_writer::tag(
        'tr',
        html_writer::tag('th', s($check['label']), ['scope' => 'row'])
        . html_writer::tag('td', s(strtoupper($check['status'])))
        . html_writer::tag('td', s($check['summary']))
        . html_writer::tag('td', $check['metric'] === null ? '' : (string)$check['metric'])
        . html_writer::tag('td', (string)$check['durationms'] . ' ms')
    );
}
echo html_writer::end_tag('tbody');
echo html_writer::end_tag('table');
echo $OUTPUT->single_button(
    new moodle_url('/admin/tool/iomadmonitor/index.php', ['deep' => 1]),
    get_string('rundeepcheck', 'tool_iomadmonitor'),
    'get',
);
echo html_writer::link(
    new moodle_url('/admin/tool/iomadmonitor/status.php'),
    get_string('viewservicecatalogue', 'tool_iomadmonitor'),
);
echo $OUTPUT->footer();
