<?php
// This file is part of Moodle - http://moodle.org/

namespace local_tenantmaster\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Tenant Master import-package upload form.
 *
 * @package    local_tenantmaster
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class import_package extends \moodleform {
    /**
     * Define form.
     */
    protected function definition(): void {
        $mform = $this->_form;
        $mform->addElement('filepicker', 'packagefile', get_string('packagefile', 'local_tenantmaster'), null, [
            'accepted_types' => ['.zip'],
            'maxbytes' => 50 * 1024 * 1024,
        ]);
        $mform->addRule('packagefile', null, 'required');
        $mform->addElement('select', 'importmode', get_string('importmode', 'local_tenantmaster'), [
            'create_only' => get_string('importmode_create_only', 'local_tenantmaster'),
            'merge' => get_string('importmode_merge', 'local_tenantmaster'),
            'update' => get_string('importmode_update', 'local_tenantmaster'),
            'deactivate_missing' => get_string('importmode_deactivate_missing', 'local_tenantmaster'),
        ]);
        $mform->setDefault('importmode', 'merge');
        $mform->addElement('submit', 'submitimportpackage', get_string('inspectpackage', 'local_tenantmaster'));
    }
}
