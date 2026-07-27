<?php
// This file is part of Moodle - http://moodle.org/

namespace local_global_events\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Tenant-scoped event definition form.
 *
 * @package local_global_events
 */
final class event extends \moodleform {
    /**
     * Form definition.
     */
    protected function definition(): void {
        $mform = $this->_form;
        $editing = !empty($this->_customdata['editing']);

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'companyid', (int)$this->_customdata['companyid']);
        $mform->setType('companyid', PARAM_INT);
        $mform->addElement('text', 'idnumber', get_string('eventidnumber', 'local_global_events'), ['size' => 40]);
        $mform->setType('idnumber', PARAM_TEXT);
        $mform->addRule('idnumber', null, 'required');
        if ($editing) {
            $mform->freeze('idnumber');
        }
        $mform->addElement('text', 'name', get_string('eventname', 'local_global_events'), ['size' => 60]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required');
        $mform->addElement('textarea', 'description', get_string('description'), ['rows' => 5, 'cols' => 70]);
        $mform->setType('description', PARAM_TEXT);
        $mform->addElement(
            'autocomplete',
            'courseid',
            get_string('eventcourse', 'local_global_events'),
            $this->_customdata['courses'],
        );
        $visibility = ['companies' => get_string('visibilitycompanies', 'local_global_events')];
        if (!empty($this->_customdata['allowglobal'])) {
            $visibility['all'] = get_string('visibilityall', 'local_global_events');
        }
        $mform->addElement('select', 'visibility', get_string('visibility', 'local_global_events'), $visibility);
        $mform->addElement(
            'autocomplete',
            'companyids',
            get_string('visiblecompanies', 'local_global_events'),
            $this->_customdata['companies'],
            ['multiple' => true],
        );
        $mform->addElement('date_time_selector', 'timestart', get_string('eventstart', 'local_global_events'), [
            'optional' => true,
        ]);
        $mform->addElement('date_time_selector', 'timeend', get_string('eventend', 'local_global_events'), [
            'optional' => true,
        ]);
        $mform->addElement('select', 'status', get_string('status'), [
            'draft' => get_string('statusdraft', 'local_global_events'),
            'published' => get_string('statuspublished', 'local_global_events'),
            'cancelled' => get_string('statuscancelled', 'local_global_events'),
        ]);
        $this->add_action_buttons(true, get_string('saveevent', 'local_global_events'));
    }

    /**
     * Validate stable identity, visibility and event window.
     *
     * @param array<string, mixed> $data Data.
     * @param array<string, mixed> $files Files.
     * @return array<string, string>
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (!preg_match('/^[A-Za-z0-9_.:-]{3,100}$/', (string)$data['idnumber'])) {
            $errors['idnumber'] = get_string('invalididnumber', 'local_global_events');
        }
        if (
            (string)$data['visibility'] === 'companies'
                && empty(array_filter(array_map('intval', (array)($data['companyids'] ?? []))))
        ) {
            $errors['companyids'] = get_string('companiesrequired', 'local_global_events');
        }
        if (!empty($data['timeend']) && (int)$data['timeend'] <= (int)$data['timestart']) {
            $errors['timeend'] = get_string('endafterstart', 'local_global_events');
        }
        return $errors;
    }
}
