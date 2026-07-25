<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Tenant form restore structure.
 *
 * @package    mod_tenantform
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Restore tenant form definitions and optional user data.
 */
final class restore_tenantform_activity_structure_step extends restore_activity_structure_step {
    /**
     * Restore paths.
     *
     * @return array
     */
    protected function define_structure(): array {
        $paths = [new restore_path_element('tenantform', '/activity/tenantform')];
        if ($this->get_setting_value('userinfo')) {
            $paths[] = new restore_path_element('tenantform_entry', '/activity/tenantform/entries/entry');
            $paths[] = new restore_path_element(
                'tenantform_audit',
                '/activity/tenantform/entries/entry/audits/audit',
            );
        }
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restore a form into the target course and active company.
     *
     * @param array $data Data.
     */
    protected function process_tenantform(array $data): void {
        global $DB;

        $record = (object)$data;
        $record->course = $this->get_courseid();
        $record->companyid = \mod_tenantform\local\tenant_access::resolve_company_for_course(
            $this->get_courseid(),
            \context_course::instance($this->get_courseid()),
        );
        $record->targetcourseid = $this->get_mappingid('course', $record->targetcourseid, 0);
        if (!$record->targetcourseid) {
            $record->autoenrol = 0;
        }
        $newid = $DB->insert_record('tenantform', $record);
        $this->apply_activity_instance($newid);
    }

    /**
     * Restore an entry.
     *
     * @param array $data Data.
     */
    protected function process_tenantform_entry(array $data): void {
        global $DB;

        $record = (object)$data;
        $oldid = $record->id;
        $record->tenantformid = $this->get_new_parentid('tenantform');
        $record->companyid = \mod_tenantform\local\tenant_access::resolve_company_for_course(
            $this->get_courseid(),
            \context_course::instance($this->get_courseid()),
        );
        $record->userid = $this->get_mappingid('user', $record->userid, 0);
        $record->submissiontoken = hash(
            'sha256',
            $record->submissiontoken . ':' . $this->get_restoreid() . ':' . $oldid,
        );
        $newid = $DB->insert_record('tenantform_entry', $record);
        $this->set_mapping('tenantform_entry', $oldid, $newid, true);
    }

    /**
     * Restore an audit event.
     *
     * @param array $data Data.
     */
    protected function process_tenantform_audit(array $data): void {
        global $DB;

        $record = (object)$data;
        $record->tenantformid = $this->get_new_parentid('tenantform');
        $record->entryid = $this->get_new_parentid('tenantform_entry');
        $record->companyid = \mod_tenantform\local\tenant_access::resolve_company_for_course(
            $this->get_courseid(),
            \context_course::instance($this->get_courseid()),
        );
        $record->userid = $this->get_mappingid('user', $record->userid, 0);
        $DB->insert_record('tenantform_audit', $record);
    }

    /**
     * Restore intro and entry files.
     */
    protected function after_execute(): void {
        $this->add_related_files('mod_tenantform', 'intro', null);
        $this->add_related_files('mod_tenantform', 'entry', 'tenantform_entry');
    }
}
