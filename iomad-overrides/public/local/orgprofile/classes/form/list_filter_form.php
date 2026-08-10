<?php
// This file is part of Moodle - https://moodle.org/

namespace local_orgprofile\form;

use local_orgprofile\local\ui\listing;

/** Search and page-size controls shared by plugin administration lists. */
final class list_filter_form extends \moodleform {

    /** Define the GET filter form. */
    protected function definition(): void {
        $mform = $this->_form;
        foreach (($this->_customdata['hidden'] ?? []) as $name => $value) {
            $mform->addElement('hidden', $name, $value);
            $mform->setType($name, PARAM_ALPHANUMEXT);
        }

        $elements = [];
        $elements[] = $mform->createElement('text', 'q', '', [
            'maxlength' => 255,
            'placeholder' => get_string('searchplaceholder', 'local_orgprofile'),
            'aria-label' => get_string('search', 'local_orgprofile'),
        ]);
        $mform->setType('q', PARAM_TEXT);

        $pagesizes = [];
        foreach (listing::PAGE_SIZES as $size) {
            $pagesizes[$size] = get_string('recordsperpage', 'local_orgprofile', $size);
        }
        $elements[] = $mform->createElement('select', 'perpage', '', $pagesizes, [
            'aria-label' => get_string('pagesize', 'local_orgprofile'),
        ]);
        $mform->setType('perpage', PARAM_INT);
        $elements[] = $mform->createElement('submit', 'filterbutton', get_string('applyfilters', 'local_orgprofile'));
        $mform->addGroup($elements, 'listfilters', get_string('filtercontrols', 'local_orgprofile'), ' ', false);
    }
}
