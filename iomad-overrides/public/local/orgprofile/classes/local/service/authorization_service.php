<?php
// This file is part of Moodle - https://moodle.org/

namespace local_orgprofile\local\service;

use context_system;
use local_iomad\custom_context\context_company;
use local_iomad\iomad;
use required_capability_exception;

/**
 * Central company-membership and capability policy.
 *
 * IOMAD's check_valid_user() deliberately includes child companies. Profile values are
 * strictly scoped to one concrete company, so this service uses the verified membership
 * table through Moodle DML for exact relationship checks.
 *
 * @package local_orgprofile
 */
final class authorization_service {

    /** Return whether a user has an exact IOMAD company association. */
    public function is_company_member(int $userid, int $companyid, bool $activeonly = false): bool {
        global $DB;
        $conditions = ['userid' => $userid, 'companyid' => $companyid];
        if ($activeonly) {
            $conditions['suspended'] = 0;
        }
        return $DB->record_exists('local_iomad_company_users', $conditions);
    }

    /** Return whether the current actor may view a target profile. */
    public function can_view_profile(int $targetuserid, int $companyid): bool {
        global $USER;
        if (!$this->is_company_member($targetuserid, $companyid)) {
            return false;
        }
        if (has_capability('local/orgprofile:viewall', context_system::instance())) {
            return true;
        }
        $context = context_company::instance($companyid);
        if ((int) $USER->id === $targetuserid) {
            return $this->is_company_member($USER->id, $companyid, true) &&
                iomad::has_capability('local/orgprofile:viewown', $context, $companyid);
        }
        return $this->is_company_member($USER->id, $companyid, true) &&
            iomad::has_capability('local/orgprofile:viewcompany', $context, $companyid);
    }

    /** Return whether the current actor may edit a target profile. */
    public function can_edit_profile(int $targetuserid, int $companyid): bool {
        global $USER;
        if (!$this->is_company_member($targetuserid, $companyid)) {
            return false;
        }
        if (has_capability('local/orgprofile:editall', context_system::instance())) {
            return true;
        }
        $context = context_company::instance($companyid);
        if ((int) $USER->id === $targetuserid) {
            return !empty(get_config('local_orgprofile', 'allowownedit')) &&
                $this->is_company_member($USER->id, $companyid, true) &&
                iomad::has_capability('local/orgprofile:editown', $context, $companyid);
        }
        return $this->is_company_member($USER->id, $companyid, true) &&
            iomad::has_capability('local/orgprofile:editcompany', $context, $companyid);
    }

    /** Return whether the current actor may assign a user type in a company. */
    public function can_assign_user_type(int $targetuserid, int $companyid): bool {
        global $USER;
        if (!$this->is_company_member($targetuserid, $companyid)) {
            return false;
        }
        if (is_siteadmin()) {
            return true;
        }
        $context = context_company::instance($companyid);
        return $this->is_company_member($USER->id, $companyid, true) &&
            iomad::has_capability('local/orgprofile:assignusertype', $context, $companyid);
    }

    /** Return whether the current actor can manage a company mapping. */
    public function can_manage_company_mapping(int $companyid): bool {
        global $USER;
        if (is_siteadmin()) {
            return true;
        }
        return $this->is_company_member($USER->id, $companyid, true) && iomad::has_capability(
            'local/orgprofile:managecompanymapping',
            context_company::instance($companyid),
            $companyid
        );
    }

    /** Return whether the current actor can view sensitive fields in a company. */
    public function can_view_sensitive(int $companyid): bool {
        global $USER;
        if (is_siteadmin()) {
            return true;
        }
        return $this->is_company_member($USER->id, $companyid, true) && iomad::has_capability(
            'local/orgprofile:viewsensitive',
            context_company::instance($companyid),
            $companyid
        );
    }

    /** Return whether the current actor can edit sensitive fields in a company. */
    public function can_edit_sensitive(int $companyid): bool {
        global $USER;
        if (is_siteadmin()) {
            return true;
        }
        return $this->can_view_sensitive($companyid) && iomad::has_capability(
            'local/orgprofile:editsensitive',
            context_company::instance($companyid),
            $companyid
        );
    }

    /** Require profile view authorization. */
    public function require_view_profile(int $targetuserid, int $companyid): void {
        if (!$this->can_view_profile($targetuserid, $companyid)) {
            throw new required_capability_exception(
                $this->safe_company_context($companyid),
                'local/orgprofile:viewcompany',
                'nopermissions',
                'local_orgprofile'
            );
        }
    }

    /** Require profile edit authorization. */
    public function require_edit_profile(int $targetuserid, int $companyid): void {
        if (!$this->can_edit_profile($targetuserid, $companyid)) {
            throw new required_capability_exception(
                $this->safe_company_context($companyid),
                'local/orgprofile:editcompany',
                'nopermissions',
                'local_orgprofile'
            );
        }
    }

    /** Return a valid context even when a crafted company ID does not exist. */
    private function safe_company_context(int $companyid): \context {
        if ($companyid <= 0) {
            return context_system::instance();
        }
        $context = context_company::instance($companyid, IGNORE_MISSING);
        return $context ?: context_system::instance();
    }
}
