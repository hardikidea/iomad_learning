<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\form;

use local_tenantmaster\local\catalog;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Tenant Master-owned regulatory and academic institution metadata.
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
        $mform->addElement('header', 'identity', get_string('academicidentity', 'local_tenantmaster'));
        $mform->addElement('static', 'trustcode', get_string('nativecompanycode', 'local_tenantmaster'));
        $mform->addElement(
            'select',
            'tenanttype',
            get_string('institutiontype', 'local_tenantmaster'),
            catalog::localise(catalog::TENANT_TYPES)
        );

        $mform->addElement(
            'header',
            'schoolidentity',
            get_string('schoolregulatorymetadata', 'local_tenantmaster'),
        );
        foreach (
            [
                'trustlegalname' => 60,
                'trustregistrationnumber' => 30,
                'udisecode' => 20,
                'boardaffiliationnumber' => 30,
                'recognitionnumber' => 30,
                'schoolstage' => 30,
                'managementtype' => 30,
                'academicsession' => 20,
                'district' => 30,
                'block' => 30,
                'preferredlanguages' => 50,
            ] as $field => $size
        ) {
            $mform->addElement('text', $field, get_string($field, 'local_tenantmaster'), ['size' => $size]);
            $mform->setType($field, PARAM_TEXT);
            $mform->hideIf($field, 'tenanttype', 'neq', 'school');
        }
        $mform->addElement(
            'text',
            'establishmentyear',
            get_string('establishmentyear', 'local_tenantmaster'),
            ['size' => 8],
        );
        $mform->setType('establishmentyear', PARAM_INT);
        $mform->hideIf('establishmentyear', 'tenanttype', 'neq', 'school');

        $mform->addElement(
            'header',
            'highereducationidentity',
            get_string('highereducationmetadata', 'local_tenantmaster'),
        );
        foreach (
            [
                'institutioncode' => 30,
                'aishecode' => 20,
                'universitytype' => 30,
                'accreditationbody' => 30,
                'accreditationgrade' => 20,
                'regulatoryauthority' => 40,
                'approvalnumber' => 30,
                'academiccalendar' => 30,
                'creditframework' => 40,
            ] as $field => $size
        ) {
            $mform->addElement('text', $field, get_string($field, 'local_tenantmaster'), ['size' => $size]);
            $mform->setType($field, PARAM_TEXT);
            $mform->hideIf($field, 'tenanttype', 'eq', 'school');
        }
        $mform->addElement(
            'submit',
            'submittenantprofile',
            get_string('savemastermetadata', 'local_tenantmaster'),
        );
    }

    /**
     * Validate regulatory metadata.
     *
     * @param array<string, mixed> $data Data.
     * @param array<string, mixed> $files Files.
     * @return array<string, string>
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (
            !empty($data['establishmentyear'])
                && ((int)$data['establishmentyear'] < 1800 || (int)$data['establishmentyear'] > (int)date('Y'))
        ) {
            $errors['establishmentyear'] = get_string('invalidestablishmentyear', 'local_tenantmaster');
        }
        return $errors;
    }
}
