<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\form;

use local_tenantmaster\local\catalog;
use local_tenantmaster\local\catalogue_service;
use local_tenantmaster\local\json;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Global academic master catalogue form.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class catalogue_item extends \moodleform {
    /**
     * Define form.
     */
    protected function definition(): void {
        $mform = $this->_form;
        $editing = !empty($this->_customdata['editing']);

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);
        $mform->addElement(
            'select',
            'scope',
            get_string('cataloguescope', 'local_tenantmaster'),
            catalog::localise(catalogue_service::SCOPES),
        );
        $mform->addElement(
            'select',
            'mastertype',
            get_string('mastertype', 'local_tenantmaster'),
            catalog::localise(catalog::MASTER_TYPES),
        );
        $mform->addElement('text', 'externalid', get_string('externalid', 'local_tenantmaster'));
        $mform->setType('externalid', PARAM_ALPHANUMEXT);
        $mform->addRule('externalid', null, 'required');
        $mform->addElement('text', 'code', get_string('code', 'local_tenantmaster'));
        $mform->setType('code', PARAM_ALPHANUMEXT);
        $mform->addRule('code', null, 'required');
        if ($editing) {
            $mform->hardFreeze(['scope', 'mastertype', 'externalid', 'code']);
        }

        $mform->addElement('text', 'name', get_string('name'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required');
        $mform->addElement('textarea', 'description', get_string('description'));
        $mform->setType('description', PARAM_TEXT);
        $mform->addElement(
            'select',
            'parentitemid',
            get_string('parent', 'local_tenantmaster'),
            $this->_customdata['parents'] ?? [0 => get_string('none')],
        );
        $mform->addElement('textarea', 'payloadjson', get_string('payloadjson', 'local_tenantmaster'), [
            'rows' => 6,
            'class' => 'tenantmaster-json-input',
        ]);
        $mform->setType('payloadjson', PARAM_RAW_TRIMMED);
        $mform->setDefault('payloadjson', '{}');
        $mform->addElement('text', 'sortorder', get_string('sortorder', 'local_tenantmaster'));
        $mform->setType('sortorder', PARAM_INT);
        $mform->setDefault('sortorder', 0);
        $mform->addElement('advcheckbox', 'active', get_string('active'));
        $mform->setDefault('active', 1);

        $this->add_action_buttons(true, get_string('savechanges'));
    }

    /**
     * Validate JSON and stable keys.
     *
     * @param array<string, mixed> $data Data.
     * @param array<string, mixed> $files Files.
     * @return array<string, string>
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (empty($data['id']) && (
            !catalog::valid_external_key((string)($data['externalid'] ?? ''))
                || !catalog::valid_external_key((string)($data['code'] ?? ''))
        )) {
            $errors['externalid'] = get_string('invalidstablekey', 'local_tenantmaster');
        }
        try {
            json::decode_object(trim((string)($data['payloadjson'] ?? '{}')) ?: '{}');
        } catch (\Throwable) {
            $errors['payloadjson'] = get_string('invalidjson', 'local_tenantmaster');
        }
        return $errors;
    }
}
