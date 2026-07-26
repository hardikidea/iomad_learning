<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\form;

use local_tenantmaster\local\catalog;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Initialise Tenant Master for an existing native IOMAD company.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class company_adoption extends \moodleform {
    /**
     * Define form.
     */
    protected function definition(): void {
        $mform = $this->_form;
        $mform->addElement(
            'select',
            'adoptcompanyid',
            get_string('nativeiomadcompany', 'local_tenantmaster'),
            $this->_customdata['companies'] ?? [],
        );
        $mform->addRule('adoptcompanyid', null, 'required');
        $mform->addElement(
            'select',
            'tenanttype',
            get_string('institutiontype', 'local_tenantmaster'),
            catalog::localise(catalog::TENANT_TYPES),
        );
        $mform->addElement(
            'submit',
            'submitcompanyadoption',
            get_string('initialiseacademicmanagement', 'local_tenantmaster'),
        );
    }

    /**
     * Require an uninitialised company with a stable native company code.
     *
     * @param array<string, mixed> $data Data.
     * @param array<string, mixed> $files Files.
     * @return array<string, string>
     */
    public function validation($data, $files): array {
        global $DB;

        $errors = parent::validation($data, $files);
        $companyid = (int)($data['adoptcompanyid'] ?? 0);
        $company = $DB->get_record('local_iomad_companies', ['id' => $companyid, 'suspended' => 0]);
        if (!$company || $DB->record_exists('local_tenantmaster_tenant', ['companyid' => $companyid])) {
            $errors['adoptcompanyid'] = get_string('companynotavailableforadoption', 'local_tenantmaster');
        } else if (!catalog::valid_external_key(trim((string)$company->code))) {
            $errors['adoptcompanyid'] = get_string('nativecompanycoderequired', 'local_tenantmaster');
        }
        return $errors;
    }
}
