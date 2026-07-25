<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Preview or apply academic rollover.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class rollover extends \moodleform {
    /**
     * Define form.
     */
    protected function definition(): void {
        $mform = $this->_form;
        $years = $this->_customdata['years'] ?? [];
        $plans = [0 => get_string('none')] + ($this->_customdata['plans'] ?? []);
        $mform->addElement('select', 'rolloperation', get_string('operation', 'local_tenantmaster'), [
            'plan' => get_string('rolloverplan', 'local_tenantmaster'),
            'apply' => get_string('rolloverapply', 'local_tenantmaster'),
        ]);
        $mform->addElement('select', 'fromyearid', get_string('fromyear', 'local_tenantmaster'), $years);
        $mform->addElement('select', 'toyearid', get_string('toyear', 'local_tenantmaster'), $years);
        $mform->addElement('select', 'rolloverid', get_string('existingplan', 'local_tenantmaster'), $plans);
        $mform->addElement('text', 'backupref', get_string('backupreference', 'local_tenantmaster'), ['size' => 60]);
        $mform->setType('backupref', PARAM_TEXT);
        $mform->addElement('submit', 'submitrollover', get_string('runoperation', 'local_tenantmaster'));
    }

    /**
     * Validate operation-specific fields.
     *
     * @param array<string, mixed> $data Data.
     * @param array<string, mixed> $files Files.
     * @return array<string, string>
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if ($data['rolloperation'] === 'plan' && (int)$data['fromyearid'] === (int)$data['toyearid']) {
            $errors['toyearid'] = get_string('differentyearsrequired', 'local_tenantmaster');
        }
        if ($data['rolloperation'] === 'apply') {
            if ((int)$data['rolloverid'] <= 0) {
                $errors['rolloverid'] = get_string('planrequired', 'local_tenantmaster');
            }
            if (trim((string)$data['backupref']) === '') {
                $errors['backupref'] = get_string('backuprequired', 'local_tenantmaster');
            }
        }
        return $errors;
    }
}
