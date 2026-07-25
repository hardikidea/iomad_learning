<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\form;

use local_tenantmaster\local\catalog;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Combined tenant and current native company profile.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class tenant_profile extends \moodleform {
    /**
     * Define form.
     */
    protected function definition(): void {
        $mform = $this->_form;
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'companyid');
        $mform->setType('companyid', PARAM_INT);
        $mform->addElement('header', 'identity', get_string('tenantidentity', 'local_tenantmaster'));
        $mform->addElement('text', 'trustcode', get_string('trustcode', 'local_tenantmaster'), ['size' => 30]);
        $mform->setType('trustcode', PARAM_TEXT);
        $mform->freeze('trustcode');
        $mform->addElement(
            'select',
            'tenanttype',
            get_string('tenanttype', 'local_tenantmaster'),
            catalog::localise(catalog::TENANT_TYPES)
        );

        $mform->addElement('header', 'nativeprofile', get_string('nativecompanyprofile', 'local_tenantmaster'));
        $mform->addElement('text', 'name', get_string('institutionname', 'local_tenantmaster'), ['size' => 50]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required');
        $mform->addElement('textarea', 'address', get_string('address'), ['rows' => 3, 'cols' => 60]);
        $mform->setType('address', PARAM_TEXT);
        $mform->addElement('text', 'city', get_string('city'), ['size' => 30]);
        $mform->setType('city', PARAM_TEXT);
        $mform->addRule('city', null, 'required');
        $mform->addElement('text', 'region', get_string('state'), ['size' => 30]);
        $mform->setType('region', PARAM_TEXT);
        $mform->addElement('text', 'postcode', get_string('postcode', 'local_tenantmaster'), ['size' => 20]);
        $mform->setType('postcode', PARAM_TEXT);
        $mform->addElement('select', 'country', get_string('country'), get_string_manager()->get_list_of_countries());
        $mform->addRule('country', null, 'required');
        $mform->addElement('text', 'hostname', get_string('hostname', 'local_tenantmaster'), ['size' => 50]);
        $mform->setType('hostname', PARAM_HOST);

        $mform->addElement('header', 'branding', get_string('branding', 'local_tenantmaster'));
        foreach (['maincolor', 'headingcolor', 'linkcolor'] as $field) {
            $mform->addElement('text', $field, get_string($field, 'local_tenantmaster'), [
                'size' => 12,
                'placeholder' => '#123456',
            ]);
            $mform->setType($field, PARAM_TEXT);
        }
        $mform->addElement('textarea', 'customcss', get_string('customcss', 'local_tenantmaster'), [
            'rows' => 8,
            'cols' => 80,
        ]);
        $mform->setType('customcss', PARAM_RAW);
        $mform->addElement('submit', 'submittenantprofile', get_string('savechanges'));
    }

    /**
     * Validate design tokens and hostname input.
     *
     * @param array<string, mixed> $data Data.
     * @param array<string, mixed> $files Files.
     * @return array<string, string>
     */
    public function validation($data, $files): array {
        global $DB;

        $errors = parent::validation($data, $files);
        foreach (['maincolor', 'headingcolor', 'linkcolor'] as $field) {
            if ($data[$field] !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', (string)$data[$field])) {
                $errors[$field] = get_string('invalidbrandcolor', 'local_tenantmaster');
            }
        }
        if (
            $data['hostname'] !== '' && $DB->record_exists_select(
                'local_iomad_companies',
                'hostname = :hostname AND id <> :companyid',
                ['hostname' => $data['hostname'], 'companyid' => $data['companyid']],
            )
        ) {
            $errors['hostname'] = get_string('hostnameinuse', 'local_tenantmaster');
        }
        if (strlen((string)$data['customcss']) > 65535) {
            $errors['customcss'] = get_string('customcsstoolong', 'local_tenantmaster');
        }
        return $errors;
    }
}
