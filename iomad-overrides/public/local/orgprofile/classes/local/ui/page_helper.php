<?php
// This file is part of Moodle - https://moodle.org/

namespace local_orgprofile\local\ui;

use html_writer;
use moodle_url;

/** Moodle-native page chrome shared by Organization Profiles controllers. */
final class page_helper {

    /**
     * Add a consistent plugin breadcrumb trail.
     *
     * @param array<int, array{0: string, 1?: moodle_url|null}> $crumbs Additional crumbs
     */
    public static function breadcrumbs(array $crumbs = []): void {
        global $PAGE;
        $PAGE->navbar->add(
            get_string('pluginname', 'local_orgprofile'),
            new moodle_url('/local/orgprofile/index.php')
        );
        foreach ($crumbs as $crumb) {
            $PAGE->navbar->add($crumb[0], $crumb[1] ?? null);
        }
    }

    /** Render a concise purpose and dependency note using theme components. */
    public static function intro(string $purpose, string $why): string {
        $content = html_writer::tag('h2', get_string('aboutthispage', 'local_orgprofile'), [
            'class' => 'h5 alert-heading',
        ]);
        $content .= html_writer::tag('p', $purpose, ['class' => 'mb-2']);
        $content .= html_writer::tag(
            'p',
            html_writer::tag('strong', get_string('whyrequired', 'local_orgprofile') . ': ') . $why,
            ['class' => 'mb-0']
        );
        return html_writer::div($content, 'alert alert-info');
    }

    /** Render list controls and reset link. */
    public static function filter(
        \local_orgprofile\form\list_filter_form $form,
        moodle_url $reseturl
    ): string {
        $content = $form->render();
        $content .= html_writer::div(
            html_writer::link($reseturl, get_string('clearfilters', 'local_orgprofile')),
            'text-end mt-n3 mb-3'
        );
        return html_writer::div($content, 'card card-body mb-3');
    }

    /** Render a consistent empty state. */
    public static function empty_state(bool $filtered): string {
        $string = $filtered ? 'noresults' : 'norecords';
        return html_writer::div(get_string($string, 'local_orgprofile'), 'alert alert-light border');
    }

    /** Render a theme-aligned boolean badge. */
    public static function yes_no_badge(bool $value): string {
        $class = $value ? 'badge bg-success' : 'badge bg-secondary';
        return html_writer::span(get_string($value ? 'yes' : 'no'), $class);
    }

    /** Render an enabled/disabled status badge. */
    public static function status_badge(bool $enabled): string {
        $class = $enabled ? 'badge bg-success' : 'badge bg-secondary';
        return html_writer::span(
            get_string($enabled ? 'statusenabled' : 'statusdisabled', 'local_orgprofile'),
            $class
        );
    }
}
