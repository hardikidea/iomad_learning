<?php
// This file is part of Moodle - https://moodle.org/

defined('MOODLE_INTERNAL') || die();

/** Add company-manager configuration links to global navigation. */
function local_orgprofile_extend_navigation(global_navigation $navigation): void {
    global $DB, $USER;
    if (empty($USER->id) || isguestuser()) {
        return;
    }
    $authorization = new \local_orgprofile\local\service\authorization_service();
    $memberships = $DB->get_records('local_iomad_company_users', ['userid' => $USER->id, 'suspended' => 0],
        'companyid ASC', 'id,companyid');
    $seen = [];
    foreach ($memberships as $membership) {
        $companyid = (int) $membership->companyid;
        if (isset($seen[$companyid])) {
            continue;
        }
        $seen[$companyid] = true;
        $company = $DB->get_record('local_iomad_companies', ['id' => $companyid], 'id,name');
        if (!$company) {
            continue;
        }
        if ($authorization->can_manage_company_mapping($companyid)) {
            $navigation->add(
                get_string('companymapping', 'local_orgprofile') . ': ' . format_string($company->name),
                new moodle_url('/local/orgprofile/company.php', ['companyid' => $companyid]),
                navigation_node::TYPE_CUSTOM
            );
        }
        if ($authorization->can_assign_user_type((int) $USER->id, $companyid) &&
                $DB->record_exists('local_orgprofile_company', ['companyid' => $companyid])) {
            $navigation->add(
                get_string('assignments', 'local_orgprofile') . ': ' . format_string($company->name),
                new moodle_url('/local/orgprofile/assignment.php', ['companyid' => $companyid]),
                navigation_node::TYPE_CUSTOM
            );
        }
    }
}

/**
 * Add authorized company-scoped profile links to user navigation.
 *
 * @param navigation_node $navigation User navigation node.
 * @param stdClass $user Target user.
 * @param stdClass|null $course Course, when applicable.
 */
function local_orgprofile_extend_navigation_user(navigation_node $navigation, stdClass $user, $course): void {
    global $DB;
    if (empty(get_config('local_orgprofile', 'showusernavigation'))) {
        return;
    }
    $authorization = new \local_orgprofile\local\service\authorization_service();
    $memberships = $DB->get_records('local_iomad_company_users', ['userid' => $user->id], 'companyid ASC', 'id,companyid');
    $seen = [];
    foreach ($memberships as $membership) {
        $companyid = (int) $membership->companyid;
        if (isset($seen[$companyid]) || !$authorization->can_view_profile((int) $user->id, $companyid) ||
                !$DB->record_exists('local_orgprofile_company', ['companyid' => $companyid]) ||
                !$DB->record_exists('local_orgprofile_user', [
                    'userid' => $user->id,
                    'companyid' => $companyid,
                    'status' => 'active',
                ])) {
            continue;
        }
        $company = $DB->get_record('local_iomad_companies', ['id' => $companyid], 'id,name');
        if (!$company) {
            continue;
        }
        $navigation->add(
            get_string('organizationprofiles', 'local_orgprofile') . ': ' . format_string($company->name),
            new moodle_url('/local/orgprofile/profile.php', ['userid' => $user->id, 'companyid' => $companyid]),
            navigation_node::TYPE_CUSTOM,
            null,
            'local_orgprofile_' . $companyid
        );
        $seen[$companyid] = true;
    }
}
