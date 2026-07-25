<?php
// This file is part of IOMAD - http://www.iomad.org/

namespace local_institutionpack;

use local_iomad\company;

/**
 * Reconcile one canonical pack user with their native IOMAD company membership.
 *
 * @package    local_institutionpack
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class company_membership_service {
    /**
     * Reconcile and read back the native membership through supported IOMAD APIs.
     *
     * @param int $userid Native user ID.
     * @param int $companyid Native company ID.
     * @param int $departmentid Native department ID.
     * @param int $managertype IOMAD manager type.
     * @param bool $educator Company educator flag.
     * @return object Native company membership.
     */
    public function reconcile(
        int $userid,
        int $companyid,
        int $departmentid,
        int $managertype,
        bool $educator,
    ): object {
        global $DB;

        if (!$DB->record_exists('user', ['id' => $userid, 'deleted' => 0])) {
            throw new \invalid_parameter_exception('The company user does not exist.');
        }
        if (!$DB->record_exists('local_iomad_companies', ['id' => $companyid])) {
            throw new \invalid_parameter_exception('The company does not exist.');
        }
        if (
            !$DB->record_exists('local_iomad_company_departments', [
                'id' => $departmentid,
                'companyid' => $companyid,
            ])
        ) {
            throw new \invalid_parameter_exception('The department does not belong to the company.');
        }
        if (!in_array($managertype, [0, 1, 2, 3, 4], true)) {
            throw new \invalid_parameter_exception('The IOMAD manager type is invalid.');
        }
        $effectiveeducator = $educator
            || (
                in_array($managertype, [1, 2], true)
                    && !empty(get_config('local_iomad', 'autoenrol_managers'))
            );

        $membership = $DB->get_record('local_iomad_company_users', [
            'userid' => $userid,
            'companyid' => $companyid,
            'departmentid' => $departmentid,
        ]);
        if (
            !$membership
                || (int)$membership->managertype !== $managertype
                || (bool)$membership->educator !== $effectiveeducator
                || $DB->count_records('local_iomad_company_users', [
                    'userid' => $userid,
                    'companyid' => $companyid,
                ]) !== 1
        ) {
            company::upsert_company_user(
                $userid,
                $companyid,
                $departmentid,
                $managertype,
                $educator,
                true,
                true,
            );
        }

        $membership = $DB->get_record('local_iomad_company_users', [
            'userid' => $userid,
            'companyid' => $companyid,
            'departmentid' => $departmentid,
        ], '*', MUST_EXIST);
        if (
            (int)$membership->managertype !== $managertype
                || (bool)$membership->educator !== $effectiveeducator
                || $DB->count_records('local_iomad_company_users', [
                    'userid' => $userid,
                    'companyid' => $companyid,
                ]) !== 1
        ) {
            throw new \RuntimeException(sprintf(
                'Unable to reconcile native IOMAD membership (manager=%d, educator=%d, memberships=%d).',
                (int)$membership->managertype,
                (int)(bool)$membership->educator,
                $DB->count_records('local_iomad_company_users', [
                    'userid' => $userid,
                    'companyid' => $companyid,
                ]),
            ));
        }

        return $membership;
    }
}
