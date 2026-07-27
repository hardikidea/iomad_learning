<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\form;

use local_tenantmaster\local\catalog;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Tenant academic master form.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class master extends \moodleform {
    /**
     * Define form.
     */
    protected function definition(): void {
        $mform = $this->_form;
        $editing = !empty($this->_customdata['editing']);
        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'tenantid');
        $mform->setType('tenantid', PARAM_INT);
        $mform->addElement(
            'select',
            'acadyearid',
            get_string('academicyearscope', 'local_tenantmaster'),
            [0 => get_string('sharedallacademicyears', 'local_tenantmaster')]
                + ($this->_customdata['years'] ?? []),
        );
        $mform->addElement(
            'select',
            'mastertype',
            get_string('mastertype', 'local_tenantmaster'),
            catalog::localise(catalog::MASTER_TYPES)
        );
        $mform->addElement('text', 'externalid', get_string('externalid', 'local_tenantmaster'), ['size' => 40]);
        $mform->setType('externalid', PARAM_TEXT);
        $mform->addRule('externalid', null, 'required');
        $mform->addElement('text', 'code', get_string('code', 'local_tenantmaster'), ['size' => 30]);
        $mform->setType('code', PARAM_TEXT);
        $mform->addRule('code', null, 'required');
        if ($editing) {
            $mform->hardFreeze(['mastertype', 'externalid', 'code', 'acadyearid']);
        }
        $mform->addElement('text', 'name', get_string('name'), ['size' => 50]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required');
        $mform->addElement(
            'select',
            'parentid',
            get_string('parent', 'local_tenantmaster'),
            $this->_customdata['parents'] ?? [0 => get_string('none')]
        );
        $mform->addElement('textarea', 'description', get_string('description'), ['rows' => 3, 'cols' => 70]);
        $mform->setType('description', PARAM_TEXT);
        $mform->addElement('textarea', 'payloadjson', get_string('configurationjson', 'local_tenantmaster'), [
            'rows' => 8,
            'cols' => 80,
        ]);
        $mform->setType('payloadjson', PARAM_RAW);
        $mform->setDefault('payloadjson', '{}');
        $mform->addElement('advcheckbox', 'active', get_string('active', 'local_tenantmaster'));
        $mform->setDefault('active', 1);
        $mform->addElement('text', 'sortorder', get_string('sortorder', 'local_tenantmaster'), ['size' => 8]);
        $mform->setType('sortorder', PARAM_INT);
        $mform->setDefault('sortorder', 0);
        $mform->addElement('submit', 'submitmaster', get_string('savechanges'));
    }

    /**
     * Validate identifiers and JSON.
     *
     * @param array<string, mixed> $data Data.
     * @param array<string, mixed> $files Files.
     * @return array<string, string>
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (empty($this->_customdata['editing'])) {
            foreach (['externalid', 'code'] as $field) {
                if (!catalog::valid_external_key((string)($data[$field] ?? ''))) {
                    $errors[$field] = get_string('invalidexternalkey', 'local_tenantmaster');
                }
            }
        }
        try {
            json_decode((string)$data['payloadjson'], true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $errors['payloadjson'] = get_string('invalidjson', 'local_tenantmaster');
        }
        return $errors;
    }
}
