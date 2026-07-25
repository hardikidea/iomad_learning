<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\form;

use local_tenantmaster\local\catalog;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * New tenant onboarding form.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class onboarding extends \moodleform {
    /**
     * Define form.
     */
    protected function definition(): void {
        $mform = $this->_form;
        $mform->addElement('header', 'identity', get_string('tenantidentity', 'local_tenantmaster'));
        $mform->addElement('text', 'name', get_string('institutionname', 'local_tenantmaster'), ['size' => 50]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required');
        $mform->addElement('text', 'shortname', get_string('shortname'), ['size' => 30]);
        $mform->setType('shortname', PARAM_ALPHANUMEXT);
        $mform->addRule('shortname', null, 'required');
        $mform->addElement('text', 'trustcode', get_string('trustcode', 'local_tenantmaster'), ['size' => 30]);
        $mform->setType('trustcode', PARAM_TEXT);
        $mform->addRule('trustcode', null, 'required');
        $mform->addElement(
            'select',
            'tenanttype',
            get_string('tenanttype', 'local_tenantmaster'),
            catalog::localise(catalog::TENANT_TYPES)
        );
        $mform->addElement(
            'select',
            'parentcompanyid',
            get_string('parentcompany', 'local_tenantmaster'),
            $this->_customdata['parentcompanies'] ?? [0 => get_string('none')]
        );
        $mform->addElement('text', 'city', get_string('city'), ['size' => 30]);
        $mform->setType('city', PARAM_TEXT);
        $mform->addRule('city', null, 'required');
        $mform->addElement('select', 'country', get_string('country'), get_string_manager()->get_list_of_countries());
        $mform->addRule('country', null, 'required');
        $mform->addElement('textarea', 'address', get_string('address'), ['rows' => 3, 'cols' => 60]);
        $mform->setType('address', PARAM_TEXT);
        $mform->addElement('text', 'region', get_string('state'), ['size' => 30]);
        $mform->setType('region', PARAM_TEXT);
        $mform->addElement('text', 'postcode', get_string('postcode', 'local_tenantmaster'), ['size' => 20]);
        $mform->setType('postcode', PARAM_TEXT);
        $mform->addElement('text', 'hostname', get_string('hostname', 'local_tenantmaster'), ['size' => 50]);
        $mform->setType('hostname', PARAM_HOST);
        $mform->addElement('submit', 'submitonboarding', get_string('createtenant', 'local_tenantmaster'));
    }

    /**
     * Validate stable identifiers.
     *
     * @param array<string, mixed> $data Data.
     * @param array<string, mixed> $files Files.
     * @return array<string, string>
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (!catalog::valid_external_key((string)$data['trustcode'])) {
            $errors['trustcode'] = get_string('invalidexternalkey', 'local_tenantmaster');
        }
        return $errors;
    }
}
