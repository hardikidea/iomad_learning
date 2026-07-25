<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Explicit guardian-to-learner native role relationship form.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class guardian_link extends \moodleform {
    /**
     * Define form.
     */
    protected function definition(): void {
        $mform = $this->_form;
        $mform->addElement(
            'select',
            'guardianid',
            get_string('guardian', 'local_tenantmaster'),
            $this->_customdata['users']
        );
        $mform->addElement(
            'select',
            'learnerid',
            get_string('learner', 'local_tenantmaster'),
            $this->_customdata['users']
        );
        $mform->addElement('submit', 'submitguardianlink', get_string('linkguardian', 'local_tenantmaster'));
    }

    /**
     * Guardian and learner must differ.
     *
     * @param array<string, mixed> $data Data.
     * @param array<string, mixed> $files Files.
     * @return array<string, string>
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if ((int)$data['guardianid'] === (int)$data['learnerid']) {
            $errors['learnerid'] = get_string('guardianlearnerdifferent', 'local_tenantmaster');
        }
        return $errors;
    }
}
