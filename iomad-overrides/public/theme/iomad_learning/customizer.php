<?php
// This file is part of Moodle - http://moodle.org/

require(__DIR__ . '/../../../config.php');

use theme_iomad_learning\local\token_catalog;

require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_url('/theme/iomad_learning/customizer.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('customizer', 'theme_iomad_learning'));
$PAGE->set_heading(get_string('customizer', 'theme_iomad_learning'));
$PAGE->requires->css('/theme/iomad_learning/style/customizer.css');

$definitions = token_catalog::definitions();
if (data_submitted() && confirm_sesskey()) {
    foreach ($definitions as $key => $definition) {
        $name = 'token_' . $key;
        $value = $definition['type'] === 'boolean' && !isset($_POST[$name])
            ? '0'
            : optional_param($name, $definition['default'], PARAM_RAW_TRIMMED);
        set_config($key, token_catalog::normalize($key, (string)$value), 'theme_iomad_learning');
    }
    theme_reset_all_caches();
    redirect(
        new moodle_url('/theme/iomad_learning/customizer.php'),
        get_string('changessaved'),
        1,
        \core\output\notification::NOTIFY_SUCCESS,
    );
}

$map = [];
foreach ($definitions as $key => $definition) {
    $map[$key] = token_catalog::css_name($key);
}
$PAGE->requires->js_call_amd('theme_iomad_learning/customizer', 'init', [$map]);

echo $OUTPUT->header();
echo html_writer::start_tag('form', [
    'method' => 'post',
    'class' => 'iomad-learning-customizer',
    'data-region' => 'theme-customizer',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::start_div('iomad-learning-customizer-toolbar');
echo html_writer::label(get_string('filtertokens', 'theme_iomad_learning'), 'theme-token-search', false, [
    'class' => 'visually-hidden',
]);
echo html_writer::empty_tag('input', [
    'id' => 'theme-token-search',
    'type' => 'search',
    'class' => 'form-control',
    'placeholder' => get_string('filtertokens', 'theme_iomad_learning'),
    'data-action' => 'search',
]);
echo html_writer::label(get_string('filtergroups', 'theme_iomad_learning'), 'menutoken_group', false, [
    'class' => 'visually-hidden',
]);
echo html_writer::select(
    ['' => get_string('allgroups', 'theme_iomad_learning')] + token_catalog::groups(),
    'token_group',
    '',
    false,
    ['class' => 'form-select', 'data-action' => 'group'],
);
echo html_writer::tag(
    'button',
    html_writer::tag('i', '', ['class' => 'fa fa-rotate-left', 'aria-hidden' => 'true']),
    [
        'type' => 'button',
        'class' => 'btn btn-secondary',
        'data-action' => 'reset',
        'title' => get_string('resetpreview', 'theme_iomad_learning'),
        'aria-label' => get_string('resetpreview', 'theme_iomad_learning'),
    ],
);
echo html_writer::tag('button', get_string('savechanges'), [
    'type' => 'submit',
    'class' => 'btn btn-primary',
]);
echo html_writer::end_div();

echo html_writer::start_div('iomad-learning-customizer-workspace');
echo html_writer::start_tag('section', [
    'class' => 'iomad-learning-token-panel',
    'aria-label' => get_string('designcontrols', 'theme_iomad_learning'),
]);
foreach ($definitions as $key => $definition) {
    $value = token_catalog::value($key);
    $attributes = [
        'class' => 'iomad-learning-token-control',
        'data-group' => $definition['group'],
        'data-label' => core_text::strtolower($definition['label']),
        'data-token' => $key,
        'data-default' => $definition['default'],
    ];
    echo html_writer::start_div('', $attributes);
    echo html_writer::label($definition['label'], 'token-' . $key);
    if ($definition['type'] === 'colour') {
        echo html_writer::empty_tag('input', [
            'id' => 'token-' . $key,
            'name' => 'token_' . $key,
            'type' => 'color',
            'value' => $value,
            'class' => 'form-control form-control-color',
            'data-css-value' => $value,
        ]);
    } else if ($definition['type'] === 'boolean') {
        echo html_writer::checkbox(
            'token_' . $key,
            '1',
            $value === '1',
            '',
            ['id' => 'token-' . $key, 'class' => 'form-check-input'],
        );
    } else {
        $options = [];
        foreach ($definition['options'] as $option => $cssvalue) {
            $options[] = [
                'value' => $option,
                'label' => $option,
                'css' => $cssvalue,
            ];
        }
        echo html_writer::start_tag('select', [
            'id' => 'token-' . $key,
            'name' => 'token_' . $key,
            'class' => 'form-select',
        ]);
        foreach ($options as $option) {
            echo html_writer::tag('option', s($option['label']), [
                'value' => $option['value'],
                'data-css' => $option['css'],
                'selected' => $option['value'] === $value ? 'selected' : null,
            ]);
        }
        echo html_writer::end_tag('select');
    }
    echo html_writer::end_div();
}
echo html_writer::end_tag('section');

echo html_writer::start_tag('section', [
    'class' => 'iomad-learning-preview',
    'data-region' => 'preview',
    'aria-label' => get_string('preview', 'theme_iomad_learning'),
]);
echo html_writer::start_tag('header', ['class' => 'iomad-learning-preview-nav']);
echo html_writer::tag('strong', 'Northbridge Learning');
echo html_writer::tag(
    'nav',
    html_writer::link('#', get_string('home'))
    . html_writer::link('#', get_string('mycourses'))
    . html_writer::link('#', get_string('grades')),
);
echo html_writer::end_tag('header');
echo html_writer::start_div('iomad-learning-preview-body');
echo html_writer::tag('h2', get_string('mycourses'));
echo html_writer::start_div('iomad-learning-preview-grid');
foreach (['Applied Mathematics', 'Research Methods', 'Digital Citizenship'] as $index => $course) {
    echo html_writer::start_tag('article', ['class' => 'iomad-learning-preview-course']);
    echo html_writer::div('', 'iomad-learning-preview-image preview-image-' . $index);
    echo html_writer::tag('h3', $course);
    echo html_writer::div(
        html_writer::div('', 'iomad-learning-preview-progress-bar'),
        'iomad-learning-preview-progress',
    );
    echo html_writer::tag('button', get_string('continue'), ['type' => 'button', 'class' => 'btn btn-primary']);
    echo html_writer::end_tag('article');
}
echo html_writer::end_div();
echo html_writer::start_div('iomad-learning-preview-form');
echo html_writer::tag('h3', get_string('login'));
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'class' => 'form-control',
    'value' => 'learner@example.test',
    'aria-label' => get_string('username'),
]);
echo html_writer::tag('button', get_string('login'), ['type' => 'button', 'class' => 'btn btn-secondary']);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_tag('section');
echo html_writer::end_div();
echo html_writer::end_tag('form');
echo $OUTPUT->footer();
