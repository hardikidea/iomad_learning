<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_tenantform\local;

use local_iomad\company_user;

/**
 * Optional post-submission workflow actions.
 *
 * @package    mod_tenantform
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class workflow_service {
    /**
     * Apply an explicitly configured enrolment workflow.
     *
     * @param object $form Form.
     * @param object $entry Entry.
     */
    public function apply(object $form, object $entry): void {
        if (empty($form->autoenrol) || empty($form->targetcourseid) || empty($entry->userid)) {
            return;
        }
        if (!tenant_access::user_in_company((int)$entry->userid, (int)$form->companyid)) {
            throw new \coding_exception('The submitting user is outside the form company.');
        }
        if (!tenant_access::course_in_company((int)$form->targetcourseid, (int)$form->companyid)) {
            throw new \coding_exception('The target course is outside the form company.');
        }
        company_user::enrol(
            (int)$entry->userid,
            [(int)$form->targetcourseid],
            (int)$form->companyid,
        );
    }
}
