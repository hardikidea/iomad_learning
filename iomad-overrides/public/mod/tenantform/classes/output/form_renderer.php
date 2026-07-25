<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_tenantform\output;

/**
 * Accessible server-rendered form definition.
 *
 * @package    mod_tenantform
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class form_renderer {
    /**
     * Render a submission form.
     *
     * @param object $form Form instance.
     * @param array $definition Validated definition.
     * @param string $token Idempotency token.
     * @param array $values Field values.
     * @param array $errors Field errors.
     * @param \moodle_url|null $action Submission endpoint.
     * @return string
     */
    public function render(
        object $form,
        array $definition,
        string $token,
        array $values = [],
        array $errors = [],
        ?\moodle_url $action = null
    ): string {
        $branding = json_decode($form->brandingjson, true) ?: [];
        $accent = preg_match('/^#[0-9a-fA-F]{6}$/', $branding['accent'] ?? '')
            ? $branding['accent']
            : '#176b5b';
        $density = ($branding['density'] ?? '') === 'compact' ? 'compact' : 'comfortable';
        $attributes = [
            'method' => 'post',
            'enctype' => 'multipart/form-data',
            'class' => 'tenantform tenantform--' . $density,
            'data-tenantform' => '1',
            'style' => '--tenantform-accent:' . $accent,
        ];
        if ($action !== null) {
            $attributes['action'] = $action->out(false);
        }
        $html = \html_writer::start_tag('form', $attributes);
        $html .= \html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => 'sesskey',
            'value' => sesskey(),
        ]);
        $html .= \html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => 'action',
            'value' => 'submit',
        ]);
        $html .= \html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => 'submissiontoken',
            'value' => $token,
        ]);
        if (isset($errors['_form'])) {
            $html .= \html_writer::div(s($errors['_form']), 'alert alert-danger', [
                'role' => 'alert',
            ]);
        }
        $html .= \html_writer::div('', 'tenantform__progress', [
            'data-form-progress' => '1',
            'aria-live' => 'polite',
        ]);
        foreach ($definition['pages'] as $pageindex => $page) {
            $pagehaserror = (bool)array_intersect(
                array_column($page['fields'], 'id'),
                array_keys($errors),
            );
            $html .= \html_writer::start_tag('section', [
                'class' => 'tenantform__page',
                'data-form-page' => $pageindex,
                'data-page-has-error' => $pagehaserror ? '1' : '0',
                'aria-labelledby' => 'tenantform-page-title-' . $pageindex,
            ]);
            $html .= \html_writer::tag(
                'h3',
                s($page['title']),
                ['id' => 'tenantform-page-title-' . $pageindex],
            );
            foreach ($page['fields'] as $field) {
                $html .= $this->field($field, $values[$field['id']] ?? '', $errors[$field['id']] ?? '');
            }
            $html .= \html_writer::end_tag('section');
        }
        $html .= \html_writer::start_div('tenantform__actions');
        $html .= \html_writer::tag('button', get_string('previouspage', 'mod_tenantform'), [
            'type' => 'button',
            'class' => 'btn btn-secondary',
            'data-form-previous' => '1',
        ]);
        $html .= \html_writer::tag('button', get_string('nextpage', 'mod_tenantform'), [
            'type' => 'button',
            'class' => 'btn btn-primary',
            'data-form-next' => '1',
        ]);
        $html .= \html_writer::tag('button', get_string('submitform', 'mod_tenantform'), [
            'type' => 'submit',
            'class' => 'btn btn-primary',
            'data-form-submit' => '1',
        ]);
        $html .= \html_writer::end_div();
        $html .= \html_writer::end_tag('form');
        return $html;
    }

    /**
     * Render one definition field.
     *
     * @param array $field Field.
     * @param mixed $value Value.
     * @param string $error Error.
     * @return string
     */
    private function field(array $field, mixed $value, string $error): string {
        if ($field['type'] === 'heading') {
            return \html_writer::tag('h4', s($field['label']), ['class' => 'tenantform__subheading']);
        }
        $id = 'tenantform-field-' . $field['id'];
        $attributes = [
            'class' => 'tenantform__field' . ($error !== '' ? ' tenantform__field--error' : ''),
            'data-field-id' => $field['id'],
            'data-required' => $field['required'] ? '1' : '0',
        ];
        if (!empty($field['condition'])) {
            $attributes += [
                'data-condition-field' => $field['condition']['field'],
                'data-condition-operator' => $field['condition']['operator'],
                'data-condition-value' => $field['condition']['value'],
            ];
        }
        $html = \html_writer::start_div('', $attributes);
        if (!in_array($field['type'], ['checkbox', 'consent', 'radio'], true)) {
            $label = s($field['label']);
            if ($field['required']) {
                $label .= \html_writer::span(
                    get_string('requiredindicator', 'mod_tenantform'),
                    'tenantform__required',
                    ['aria-hidden' => 'true'],
                );
            }
            $html .= \html_writer::tag('label', $label, ['for' => $id]);
        }
        if (!empty($field['help'])) {
            $html .= \html_writer::div(s($field['help']), 'tenantform__help', [
                'id' => $id . '-help',
            ]);
        }
        $html .= $this->control($field, $id, (string)$value, $error);
        if ($error !== '') {
            $html .= \html_writer::div(s($error), 'tenantform__error', [
                'id' => $id . '-error',
                'role' => 'alert',
            ]);
        }
        $html .= \html_writer::end_div();
        return $html;
    }

    /**
     * Render an input control.
     *
     * @param array $field Field.
     * @param string $id DOM ID.
     * @param string $value Value.
     * @param string $error Error.
     * @return string
     */
    private function control(array $field, string $id, string $value, string $error): string {
        $name = 'field_' . $field['id'];
        $attributes = [
            'id' => $id,
            'name' => $name,
            'class' => 'form-control',
            'data-field-control' => $field['id'],
        ];
        if ($field['required']) {
            $attributes['required'] = 'required';
        }
        if ($error !== '') {
            $attributes['aria-invalid'] = 'true';
            $attributes['aria-describedby'] = $id . '-error';
        } else if (!empty($field['help'])) {
            $attributes['aria-describedby'] = $id . '-help';
        }
        if (isset($field['min'])) {
            $attributes['min'] = $field['min'];
        }
        if (isset($field['max'])) {
            $attributes['max'] = $field['max'];
        }
        $inputtype = match ($field['type']) {
            'email' => 'email',
            'number' => 'number',
            'date' => 'date',
            default => 'text',
        };
        return match ($field['type']) {
            'textarea' => \html_writer::tag('textarea', s($value), $attributes + ['rows' => 5]),
            'select' => $this->select($field, $attributes, $value),
            'radio' => $this->radios($field, $name, $value, $error),
            'checkbox', 'consent' => $this->checkbox($field, $id, $name, $value, $error),
            'file' => \html_writer::empty_tag('input', $attributes + [
                'type' => 'file',
                'class' => 'form-control',
            ]),
            default => \html_writer::empty_tag(
                'input',
                $attributes + [
                    'type' => $inputtype,
                'value' => $value,
                ],
            ),
        };
    }

    /**
     * Render a select.
     *
     * @param array $field Field.
     * @param array $attributes Attributes.
     * @param string $value Value.
     * @return string
     */
    private function select(array $field, array $attributes, string $value): string {
        $html = \html_writer::tag('option', get_string('selectoption', 'mod_tenantform'), ['value' => '']);
        foreach ($field['options'] as $option) {
            $optionattributes = ['value' => $option];
            if ($option === $value) {
                $optionattributes['selected'] = 'selected';
            }
            $html .= \html_writer::tag('option', s($option), $optionattributes);
        }
        return \html_writer::tag('select', $html, $attributes);
    }

    /**
     * Render a radio fieldset.
     *
     * @param array $field Field.
     * @param string $name Name.
     * @param string $value Value.
     * @param string $error Error.
     * @return string
     */
    private function radios(array $field, string $name, string $value, string $error): string {
        $legend = s($field['label']);
        if ($field['required']) {
            $legend .= \html_writer::span(
                get_string('requiredindicator', 'mod_tenantform'),
                'tenantform__required',
                ['aria-hidden' => 'true'],
            );
        }
        $html = \html_writer::tag('legend', $legend, ['class' => 'tenantform__legend']);
        foreach ($field['options'] as $index => $option) {
            $id = 'tenantform-field-' . $field['id'] . '-' . $index;
            $inputattributes = [
                'type' => 'radio',
                'id' => $id,
                'name' => $name,
                'value' => $option,
                'data-field-control' => $field['id'],
            ];
            if ($field['required'] && $index === 0) {
                $inputattributes['required'] = 'required';
            }
            if ($option === $value) {
                $inputattributes['checked'] = 'checked';
            }
            if ($error !== '') {
                $inputattributes['aria-invalid'] = 'true';
            }
            $html .= \html_writer::start_div('form-check');
            $html .= \html_writer::empty_tag('input', $inputattributes + ['class' => 'form-check-input']);
            $html .= \html_writer::tag('label', s($option), [
                'for' => $id,
                'class' => 'form-check-label',
            ]);
            $html .= \html_writer::end_div();
        }
        return \html_writer::tag('fieldset', $html, ['class' => 'tenantform__fieldset']);
    }

    /**
     * Render a checkbox or consent.
     *
     * @param array $field Field.
     * @param string $id ID.
     * @param string $name Name.
     * @param string $value Value.
     * @param string $error Error.
     * @return string
     */
    private function checkbox(
        array $field,
        string $id,
        string $name,
        string $value,
        string $error
    ): string {
        $attributes = [
            'type' => 'checkbox',
            'id' => $id,
            'name' => $name,
            'value' => '1',
            'class' => 'form-check-input',
            'data-field-control' => $field['id'],
        ];
        if ($value === '1') {
            $attributes['checked'] = 'checked';
        }
        if ($field['required']) {
            $attributes['required'] = 'required';
        }
        if ($error !== '') {
            $attributes['aria-invalid'] = 'true';
        }
        $label = s($field['label']);
        if ($field['required']) {
            $label .= \html_writer::span(
                get_string('requiredindicator', 'mod_tenantform'),
                'tenantform__required',
                ['aria-hidden' => 'true'],
            );
        }
        return \html_writer::start_div('form-check')
            . \html_writer::empty_tag('input', $attributes)
            . \html_writer::tag('label', $label, ['for' => $id, 'class' => 'form-check-label'])
            . \html_writer::end_div();
    }
}
