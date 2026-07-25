<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Tenant form backup structure.
 *
 * @package    mod_tenantform
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Build tenant form backup data.
 */
final class backup_tenantform_activity_structure_step extends backup_activity_structure_step {
    /**
     * Define backup tree.
     *
     * @return backup_nested_element
     */
    protected function define_structure(): backup_nested_element {
        $userinfo = $this->get_setting_value('userinfo');
        $form = new backup_nested_element('tenantform', ['id'], [
            'name', 'intro', 'introformat', 'companyid', 'formtype', 'definitionjson',
            'brandingjson', 'allowguest', 'notify', 'targetcourseid', 'autoenrol',
            'timecreated', 'timemodified',
        ]);
        $entries = new backup_nested_element('entries');
        $entry = new backup_nested_element('entry', ['id'], [
            'companyid', 'userid', 'submissiontoken', 'status', 'datajson', 'checksum',
            'filecount', 'timecreated',
        ]);
        $audits = new backup_nested_element('audits');
        $audit = new backup_nested_element('audit', ['id'], [
            'entryid', 'companyid', 'userid', 'action', 'timecreated',
        ]);
        $form->add_child($entries);
        $entries->add_child($entry);
        $entry->add_child($audits);
        $audits->add_child($audit);
        $form->set_source_table('tenantform', ['id' => backup::VAR_ACTIVITYID]);
        if ($userinfo) {
            $entry->set_source_table('tenantform_entry', ['tenantformid' => backup::VAR_PARENTID]);
            $audit->set_source_table('tenantform_audit', ['entryid' => backup::VAR_PARENTID]);
        }
        $form->annotate_ids('course', 'targetcourseid');
        $entry->annotate_ids('user', 'userid');
        $audit->annotate_ids('user', 'userid');
        $form->annotate_files('mod_tenantform', 'intro', null);
        $entry->annotate_files('mod_tenantform', 'entry', 'id');
        return $this->prepare_activity_structure($form);
    }
}
