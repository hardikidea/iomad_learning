<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_tenantform\local;

use local_iomad\company;
use local_iomad\iomad;

/**
 * Tenant-boundary checks for forms and enrolment workflows.
 *
 * @package    mod_tenantform
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class tenant_access {
    /**
     * Resolve an active or uniquely course-associated company.
     *
     * Shared courses assigned to multiple companies require an explicitly active
     * company so the activity cannot silently cross a tenant boundary.
     *
     * @param int $courseid Course.
     * @param \context $context Context.
     * @return int
     */
    public static function resolve_company_for_course(int $courseid, \context $context): int {
        $activecompanyid = iomad::get_my_companyid($context, false);
        if ($activecompanyid > 0 && self::course_in_company($courseid, $activecompanyid)) {
            return $activecompanyid;
        }
        $companyids = [];
        foreach (company::get_departments_by_course($courseid) as $departmentid) {
            $department = company::get_departmentbyid((int)$departmentid);
            if (!empty($department->companyid)) {
                $companyids[(int)$department->companyid] = true;
            }
        }
        if (count($companyids) === 1) {
            return (int)array_key_first($companyids);
        }
        if (!$companyids) {
            return 0;
        }
        throw new \coding_exception(
            'Select an active IOMAD company before creating or restoring a form in a shared course.'
        );
    }

    /**
     * Require an authenticated user to be in the form company.
     *
     * @param object $form Form.
     * @param \context $context Context.
     * @param object $user User.
     */
    public static function require_company(object $form, \context $context, object $user): void {
        if ((int)$form->companyid === 0 || is_siteadmin($user)) {
            return;
        }
        $activecompanyid = iomad::get_my_companyid($context, false);
        if ($activecompanyid !== (int)$form->companyid) {
            throw new \required_capability_exception(
                $context,
                'mod/tenantform:submit',
                'nopermissions',
                ''
            );
        }
    }

    /**
     * Require manager access and the active company boundary.
     *
     * @param object $form Form.
     * @param \context $context Context.
     */
    public static function require_manage(object $form, \context $context): void {
        global $USER;

        require_capability('mod/tenantform:manageentries', $context);
        self::require_company($form, $context, $USER);
    }

    /**
     * Test whether an authenticated user belongs to a company.
     *
     * @param int $userid User.
     * @param int $companyid Company.
     * @return bool
     */
    public static function user_in_company(int $userid, int $companyid): bool {
        global $DB;

        if ($companyid === 0) {
            return true;
        }
        return $DB->record_exists('local_iomad_company_users', [
            'userid' => $userid,
            'companyid' => $companyid,
        ]);
    }

    /**
     * Test whether a course is available to a company through IOMAD.
     *
     * @param int $courseid Course.
     * @param int $companyid Company.
     * @return bool
     */
    public static function course_in_company(int $courseid, int $companyid): bool {
        global $DB;

        if ($courseid === 0) {
            return false;
        }
        if ($companyid === 0) {
            return $DB->record_exists('course', ['id' => $courseid]);
        }
        $company = new company($companyid);
        return array_key_exists(
            $courseid,
            $company->get_menu_courses(
                shared: true,
                default: false,
                includehidden: true,
            )
        );
    }
}
